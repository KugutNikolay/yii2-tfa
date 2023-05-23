<?php
namespace safepartner\tfa\transport;

use Exception;
use safepartner\tfa\interfaces\IdentityInterface;
use safepartner\tfa\Module;
use Yii;
use yii\base\InvalidConfigException;

/**
 * Email Based OTP (One Time Password) Transport
 *
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
class MailTransport extends BaseTransport
{

    /**
     * @var string
     */
    public $service = 'mailer';


    /**
     * @var string
     */
    public $from;


    /**
     * @var string
     */
    public $replyTo;

    /**
     * Initializes the object.
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();
        $this->service = is_object($this->service) ? $this->service : Yii::$app->get($this->service, false);

        if (is_null($this->service)) {
            throw new InvalidConfigException("Service \"$this->service\" not found.");
        }
    }

    /**
     * @inheritdoc
     */
    public function getCode(): string
    {
        return 'mail';
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'Email';
    }

    /**
     * @inheritdoc
     */
    public function obfuscateTo(IdentityInterface $identity): string
    {
        $to = $identity->getEmail();
        return Module::obfuscate($to, 1, strpos($to, '@') - 1);
    }

    /**
     * @inheritdoc
     */
    public function sendOneTimePassword(string $otp, IdentityInterface $identity): bool
    {
        try {
            $message = $this->service->compose()
                ->setTo($identity->getEmail())
                ->setSubject(Yii::t('app', $this->subject))
                ->setTextBody(Yii::t('app', $this->message, ['code' => $otp]));

                if (!empty($this->from)) {
                    $message->setFrom($this->from);
                }

                if (!empty($this->replyTo)) {
                    $message->setReplyTo($this->replyTo);
                }

                return $message->send();
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(IdentityInterface $identity): bool
    {
        return $identity->isTfaEmailEnabled();
    }
}
