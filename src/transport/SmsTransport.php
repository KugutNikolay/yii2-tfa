<?php
namespace safepartner\tfa\transport;

use Exception;
use safepartner\tfa\interfaces\IdentityInterface;
use safepartner\tfa\Module;
use Yii;
use yii\base\InvalidConfigException;

/**
 * Sms Based OTP (One Time Password) Transport
 *
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
class SmsTransport extends BaseTransport
{

    /**
     *
     * @var string
     */
    public $service = 'sms';

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
        return 'sms';
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return 'SMS';
    }

    /**
     * @inheritdoc
     */
    public function obfuscateTo(IdentityInterface $identity): string
    {
        $to = $identity->getPhone();
        return Module::obfuscate($to, 0, strlen($to) - 4);
    }

    /**
     * @inheritdoc
     */
    public function sendOneTimePassword(string $otp, IdentityInterface $identity): bool
    {
        try {
            return $this->service->compose()
                ->setTo($identity->getPhone())
                ->setTextBody(Yii::t('app', $this->message, ['code' => $otp]))
                ->send();
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
        return $identity->isTfaSmsEnabled();
    }
}
