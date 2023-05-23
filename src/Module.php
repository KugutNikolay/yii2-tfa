<?php
namespace safepartner\tfa;

use DateTime;
use safepartner\tfa\behaviors\TfaBehavior;
use safepartner\tfa\events\TfaEvent;
use safepartner\tfa\interfaces\IdentityInterface;
use safepartner\tfa\transport\FileTransport;
use safepartner\tfa\transport\MailTransport;
use safepartner\tfa\transport\SmsTransport;
use safepartner\tfa\transport\TransportInterface;
use Yii;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;
use yii\base\InvalidValueException;
use yii\web\Cookie;
use yii\web\User;
use yii\web\UserEvent;

/**
 * To use this module, configure the [[User::$identityClass]] property which should specify class implemented [[\safepartner\tfa\interfaces\IdentityInterface]].
 *
 * For example,
 *
 * ```php
 *
 * class User extends yii\db\ActiveRecord implements \safepartner\tfa\interfaces\IdentityInterface
 *
 *    public function getEmail()
 *    {
 *        return 'email@domain.com';
 *    }
 *
 *    public function getPhone()
 *    {
 *        return '+111111111111';
 *    }
 *
 *    public function isTfaEmailEnabled()
 *    {
 *        return true;
 *    }
 *
 *    public function isTfaSMSEnabled()
 *    {
 *        return true;
 *    }
 *
 * }
 * ```
 *
 * To use Two-factor authentication, you should configure it in the application configuration like the following:
 *
 * ```php
 * [
 *     'bootstrap' => ['tfa'],
 *     // ...
 *     'modules' => [
 *         'tfa' => [
 *             'class' => safepartner\tfa\Tfa::class,
 *             'transport' => [
 * 					// Use `mailer` application component, make sure it is configured as an application component
 * 					[
 * 						'class' => safepartner\tfa\transport\MailTransport::class,
 * 						'subject' => 'Title Message Template',
 * 						'message' => 'Message Template with one-time password {code}',
 * 					]
 *
 * 					// Use `sms` application component, make sure it is configured as an application component
 * 					[
 * 						'class' => safepartner\tfa\transport\SmsTransport::class,
 * 						'subject' => 'Title Message Template',
 * 						'message' => 'Message Template with one-time password {code}',
 * 					]
 *
 * 					//['class' => safepartner\tfa\transport\FileTransport::class]
 *             ],
 *         ],
 *         // ...
 *     ],
 *     // ...
 * ],
 * ```
 *
 * You may also skip the configuration of the [[transport]] property. In that case, the default `mail` transport will be used to send emails.
 *
 * ```php
 * [
 *     'bootstrap' => ['tfa'],
 *     // ...
 *     'modules' => [
 *         'tfa' => [
 *             'class' => safepartner\tfa\Tfa::class,
 *             // 'transport' => [], // use defail `mail` transport
 *         ],
 *         // ...
 *     ],
 *     // ...
 * ],
 * ```
 *
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
class Module extends \yii\base\Module implements BootstrapInterface
{

    /**
     * @event TfaEvent an event raised right before send.
     * You may set [[TfaEvent::isValid]] to be false to cancel the send.
     */
    const EVENT_BEFORE_SEND = 'beforeSend';

    /**
     * @event TfaEvent an event raised right after send.
     */
    const EVENT_AFTER_SEND = 'afterSend';

    /**
     * Number of seconds that the user can enter one-time password
     * @var integer
     */
    public $otpLifetime = 5 * 60;

    /**
     * The length of the generated one-time passwords, can be 4 - 9 symbols
     * @var integer
     */
    public $otpLength = 6;

    /**
     * The length of the generated one-time passwords, can be 4 - 9 symbols
     * @var integer
     */
    public $otpKeyspace = '0123456789';

    /**
     * @var array the configuration of the module cookie.
     * @see Cookie
     */
    public $cookie = ['name' => '_tfa', 'httpOnly' => true];

    /**
     *
     * @var int $cookieDuration number of seconds that the user can remain in logged-in status.
     */
    public $cookieDuration = 3600 * 24 * 30;

    /**
     * @var bool whether to automatically renew the identity cookie each time a page is requested.
     * This property is effective only when [[enableAutoLogin]] is `true`.
     * When this is `false`, the identity cookie will expire after the specified duration since the user
     * is initially logged in. When this is `true`, the identity cookie will expire after the specified duration
     * since the user visits the site the last time.
     * @see enableAutoLogin
     */
    public $cookieAutoRenew = true;

    /**
     * @var string
     */
    public $errorSendOneTimePassword = 'Unable to send authorization code. Please contact System Administrator';

    /**
     * @var TransportInterface[]
     */
    protected $transports;
	
	
	/**
	 *
	 * @var IdentityInterface
	 */
	protected $identity = null;
	
	/**
	 *
	 * @var integer
	 */
	protected $duration = 0;

    /**
     * Allowed transport
     * @var array
     */
    protected static $allowedTransport = [
        'file' => FileTransport::class,
        'mail' => MailTransport::class,
        'sms' => SmsTransport::class,
    ];

    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {
        $app->user->attachBehavior('on tfa', [
            'class' => TfaBehavior::class,
            'module' => $this
        ]);
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $transports = $this->getTransports();
        if (empty($transports)) {
            throw new InvalidConfigException('Transport can not be empty');
        }

        $allowed = array_values(self::$allowedTransport);
        foreach ($transports as $transport) {
            $transport_name = get_class($transport);
            if (!in_array($transport_name, $allowed)) {
                throw new InvalidConfigException(sprintf('Transport "%s" doesn\'t support.', $transport_name));
            }
        }

        if ($this->otpLength < 4 && $this->otpLength > 9) {
            throw new InvalidConfigException("Wrong length of the generated one-time passwords, can be 4 - 9 numbers");
        }
    }

    /**
     * @param array $transports
     * @throws InvalidConfigException on invalid argument.
     */
    public function setTransports($transports)
    {
        if (!is_array($transports)) {
            throw new InvalidConfigException('"' . get_class($this) . '::transport" should be either array, "' . gettype($transports) . '" given.');
        }
        $this->transports = $transports;
    }

    /**
     * @return TransportInterface[]
     */
    public function getTransports()
    {
        $transports = [];
        foreach ($this->transports as $transport) {
            if (!is_object($transport)) {
                $transport = $this->createTransport($transport);
            }

            $transports[$transport->getCode()] = $transport;
        }
        $this->transports = $transports;
        return $this->transports;
    }

    /**
     * @return TransportInterface[]
     */
    public function getEnabledTransports()
    {
        $data = $this->getIdentityLoggedIn();
        if (!$data && !isset($data['identity'])) {
            throw InvalidValueException('Invalid identity');
        }
		
        $transports = [];
        foreach ($this->transports as $transport) {
            if ($transport->isEnabled($data['identity'])) {
                $transports[$transport->getCode()] = $transport;
            }
        }
        return $transports;
    }

    /**
     * Get firs available transport.
     * @param string $transport name
     * @return TransportInterface|false
     */
    public function getDefaultTransport($name = null)
    {
        $transports = $this->getEnabledTransports();

        if (!empty($name) && isset($transports[$name])) {
            return $transports[$name];
        }

        return reset($transports);
    }

    /**
     * Sends a one-time password via transport. If no transport is set, then the first available transport will be used.
     * @param string $transport name
     * @return bool
     * @throws InvalidValueException
     */
    public function sendOneTimePassword($transport = null): bool
    {
        $data = $this->getIdentityLoggedIn();
        if (!$data && !isset($data['identity'])) {
            throw InvalidValueException('Invalid identity');
        }

        // Generate OTP
        $otp = $this->generateOneTimePassword($this->otpLength);

        // Save OTP and expire date to session
        Yii::$app->session->set('tfa-otp', [
            'otp' => $otp,
            'expire' => (new DateTime())->modify('+ ' . $this->otpLifetime . ' seconds'),
        ]);

        $transport = $this->getDefaultTransport($transport);
        if (!$transport instanceof TransportInterface) {
            throw InvalidValueException('Transport not found');
        }

        if (!$this->beforeSend($data['identity'], $transport, $otp)) {
            return false;
        }

        $isSuccessful = $transport->sendOneTimePassword($otp, $data['identity']);

        $this->afterSend($data['identity'], $transport, $otp, $isSuccessful, empty($transport->error) ? $this->errorSendOneTimePassword : $transport->error);

        return $isSuccessful;
    }

    /**
     * Validate an OTP (One Time Password) is not expired.
     *
     * @return bool weather an otp given is not expired
     */
    public function validateOneTimePasswordNotExpire()
    {
        $now = new DateTime();
        $data = Yii::$app->session->get('tfa-otp');
        if ($now > $data['expire']) {
            return false;
        }
        return true;
    }

    /**
     * Validate an OTP (One Time Password).
     *
     * @param string $otp need to verify
     * @return bool weather an otp given is valid
     */
    public function validateOneTimePassword(string $otp)
    {
        $data = Yii::$app->session->get('tfa-otp');
        if ((string) $otp !== (string) $data['otp']) {
            return false;
        }
        return true;
    }

    /**
     * Set the user identity logged in object when an identity need to verify (without save to session).
     *
     * @param IdentityInterface|null $identity the identity object associated with the currently logged user.
     * @param int $duration number of seconds that the user can remain in logged-in status.
     */
    public function setIdentityLoggedIn(IdentityInterface $identity, int $duration)
    {
        $this->identity = $identity;
		$this->duration = $duration;
    }
	
    /**
     * Save the user identity logged in object when an identity need to verify.
     */
    public function saveIdentityLoggedIn()
    {
        Yii::$app->session->set('tfa-identity', [
            'identity' => $this->identity->getId(),
            'duration' => $this->duration,
        ]);
    }

    /**
     * Switches to a logged in identity for the current user.
     *
     * @see User::switchIdentity()
     */
    public function switchIdentityLoggedIn($remember = false)
    {
        $data = $this->getIdentityLoggedIn();
        if ($data === null) {
            return;
        }

        /* Ensure any existing identity cookies are removed. */
        if ($this->cookieAutoRenew) {
            $this->renewCookie();
        }

        if ($remember) {
            $this->rememberIdentityLoggedIn($data['identity']);
        }

        $user = Yii::$app->user;
        $user->switchIdentity($data['identity'], $data['duration']);
        $user->trigger(User::EVENT_AFTER_LOGIN, new UserEvent([
                'identity' => $data['identity'],
                'cookieBased' => false,
                'duration' => $data['duration'],
        ]));
    }

    /**
     * Get an identity logged in.
     *
     * @return array|null Returns an array of 'identity' and 'duration' if valid, otherwise null.
     * @see saveIdentityLoggedIn()
     */
    public function getIdentityLoggedIn()
    {
		if ($this->identity !== null) {
			return [
				'identity' => $this->identity,
				'duration' => $this->duration,
			];
		}
		
        $data = Yii::$app->session->get('tfa-identity');

        if ($data === null) {
            return null;
        }

        if (is_array($data) && isset($data['identity']) && isset($data['duration'])) {
            /** @var IdentityInterface $identity */
            $class = Yii::$app->user->identityClass;
            $identity = $class::findIdentity($data['identity']);
            $data['identity'] = $identity;

            return $data;
        }

        $this->removeIdentityLoggedIn();

        return null;
    }

    /**
     * Removes the identity logged in.
     */
    public function removeIdentityLoggedIn()
    {
        Yii::$app->session->remove('tfa-identity');
    }

    /**
     * Remember O
     * @param IdentityInterface $identity
     */
    public function rememberIdentityLoggedIn($identity)
    {
        $this->sendCookie($identity, $this->cookieDuration);
		$identity->setTfaNeedCookieReset(false);
    }

    public function isRemembered()
    {
		if ($this->identity->isTfaNeedCookieReset()) {
			$this->removeIdentityLoggedIn();
			$this->removeCookie();
			return false;
		}

        $data = $this->getIdentityAndDurationFromCookie();

        if (isset($data['identity'], $data['duration'])) {
            return true;
        }
        return false;
    }

    /**
     * Creates transport instance by its array configuration.
     * @param array $config transport configuration.
     * @throws InvalidConfigException on invalid transport configuration.
     * @return TransportInterface transport instance.
     */
    protected function createTransport(array $config)
    {
        if (!isset($config['class'])) {
            $config['class'] = MailTransport::class;
        }


        $transport = Yii::createObject($config);

        return $transport;
    }

    /**
     * Renews the module cookie.
     * This method will set the expiration time of the identity cookie to be the current time
     * plus the originally specified cookie duration.
     */
    protected function renewCookie()
    {
        $name = $this->getCookieName();
        $value = Yii::$app->getRequest()->getCookies()->getValue($name);
        if ($value !== null) {
            $data = json_decode($value, true);
            if (is_array($data) && isset($data[2])) {
                $cookie = Yii::createObject(array_merge($this->cookie, [
						'name' => $name,
                        'class' => Cookie::class,
                        'value' => $value,
                        'expire' => time() + (int) $data[2],
                ]));
                Yii::$app->getResponse()->getCookies()->add($cookie);
            }
        }
    }

    /**
     * Sends an module cookie.
     * It saves [[id]], [[IdentityInterface::getAuthKey()|auth key]], and the duration of cookie-based login
     * information in the cookie.
     * @param IdentityInterface $identity
     * @param int $duration number of seconds that the user can remain in logged-in status.
     */
    protected function sendCookie($identity, $duration)
    {
		$name = $this->getCookieName();
        $cookie = Yii::createObject(array_merge($this->cookie, [
				'name' => $name,
                'class' => Cookie::class,
                'value' => json_encode([
                    $identity->getId(),
                    $identity->getAuthKey(),
                    $duration,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'expire' => time() + $duration,
        ]));
        Yii::$app->getResponse()->getCookies()->add($cookie);
    }

    /**
     * Determines if an module cookie has a valid format and contains a valid auth key.
     * This method is used when [[enableAutoLogin]] is true.
     * This method attempts to authenticate a user using the information in the identity cookie.
     * @return array|null Returns an array of 'identity' and 'duration' if valid, otherwise null.
     */
    protected function getIdentityAndDurationFromCookie()
    {
		$name = $this->getCookieName();
        $value = Yii::$app->getRequest()->getCookies()->getValue($name);
        if ($value === null) {
            return null;
        }
        $data = json_decode($value, true);
        if (is_array($data) && count($data) == 3) {
            list($id, $authKey, $duration) = $data;
            /* @var $class IdentityInterface */
            $class = Yii::$app->user->identityClass;
            $identity = $class::findIdentity($id);
            if ($identity !== null) {
                if (!$identity instanceof IdentityInterface) {
                    throw new InvalidValueException("$class::findIdentity() must return an object implementing IdentityInterface.");
                } elseif (!$identity->validateAuthKey($authKey)) {
                    Yii::warning("Invalid auth key attempted for user '$id': $authKey", __METHOD__);
                } else {
                    return ['identity' => $identity, 'duration' => $duration];
                }
            }
        }
        $this->removeCookie();
        return null;
    }

    /**
     * Removes the module cookie.
     */
    protected function removeCookie()
    {
		$name = $this->getCookieName();
		Yii::$app->getResponse()->getCookies()->remove($name);

		/*
        Yii::$app->getResponse()->getCookies()->remove(Yii::createObject(array_merge($this->cookie, [
                'class' => Cookie::class,
        ])));
		*/
    }

    /**
     * Return cookie name.
	 * @return string
     */
    protected function getCookieName()
    {
        $data = $this->getIdentityLoggedIn();
        if (!$data && !isset($data['identity'])) {
            throw InvalidValueException('Invalid identity');
        }

		return $this->cookie['name'] . $data['identity']->getId();
    }

    /**
     * Generate one-time password
     * $param integer $length one-time password
     * @return string generated one-time password
     */
    protected function generateOneTimePassword($length)
    {
        $otp = '';
        $size = strlen($this->otpKeyspace);
        for ($i = 0; $i < $length; $i++) {
            $otp .= $this->otpKeyspace[random_int(0, $size - 1)];
        }
        return $otp;
    }

    /**
     * Hide part string with stars
     * Returns the obfuscate <code>string</code> specified by the <code>start</code> and <code>end</code> parameters.
     * @param string $string the input string.
     * @param int $start
     * @param int $end
     * @param string $mask replacement value, default '*'
     * @return string
     */
    public static function obfuscate($string, $start, $end, $mask = '*'): string
    {
        $ob = '';
        for ($i = $start; $i < $end; $i++) {
            $ob .= $mask;
        }
        return substr($string, 0, $start) . $ob . substr($string, $end);
    }

    /**
     * This method is called before send one time pasword.
     * The default implementation will trigger the [[EVENT_BEFORE_SEND]] event.
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     * @param IdentityInterface $identity the user identity information
     * @param TransportInterface $transport the transport information
     * @param string $otp one time password.
     * @return bool whether the one time password should continue to be send
     */
    protected function beforeSend($identity, $transport, $otp)
    {
        $event = new TfaEvent([
            'identity' => $identity,
            'transport' => $transport,
            'otp' => $otp,
        ]);
        $this->trigger(self::EVENT_BEFORE_SEND, $event);

        return $event->isValid;
    }

    /**
     * This method is called after send one time password.
     * The default implementation will trigger the [[EVENT_AFTER_SEND]] event.
     * If you override this method, make sure you call the parent implementation
     * so that the event is triggered.
     * @param IdentityInterface $identity the user identity information
     * @param TransportInterface $transport the transport information
     * @param string $otp one time password.
     * @param bool $isSuccessful
     * @param string $error
     */
    protected function afterSend($identity, $transport, $otp, $isSuccessful, $error)
    {
        $this->trigger(self::EVENT_AFTER_SEND, new TfaEvent([
                'identity' => $identity,
                'transport' => $transport,
                'otp' => $otp,
                'isSuccessful' => $isSuccessful,
                'error' => $error,
        ]));
    }
}
