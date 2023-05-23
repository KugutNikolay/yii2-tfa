<?php
namespace safepartner\tfa\behaviors;

use safepartner\tfa\interfaces\IdentityInterface;
use safepartner\tfa\Module;
use Yii;
use yii\base\Behavior;
use yii\base\InvalidValueException;
use yii\web\User;
use yii\web\UserEvent;

/**
 * TfaBehavior automatically redirect to url when identity tfa enabled and verify OTP (One Time Password).
 *
 * To use TfaBehavior, configure the [[User::$identityClass]] property which should specify class implemented [[\safepartner\tfa\interfaces\IdentityInterface]].
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
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
class TfaBehavior extends Behavior
{

    /**
     * @var Module
     */
    public $module;

    /**
     * @inheritdoc
     */
    public function events()
    {
        return [
            User::EVENT_BEFORE_LOGIN => 'beforeLogin',
        ];
    }

    /**
     * Event trigger when before user log in to system. It will be require an user verify otp digits except when user logged in via cookie base.
     * @param UserEvent $event
     * @throws InvalidValueException
     */
    public function beforeLogin(UserEvent $event)
    {
        if (!$event->isValid) {
            return;
        }

        if (!$this->module instanceof Module) {
            throw new InvalidValueException(get_class($this->module) . ' must return an object implementing ' . Module::class);
        }

        if (!$event->identity instanceof IdentityInterface) {
            throw new InvalidValueException(get_class($event->identity) . ' must return an object implementing ' . IdentityInterface::class);
        }

        if (!($event->identity->isTfaEmailEnabled() || $event->identity->isTfaSMSEnabled())) {
            return;
        }
		
		$this->module->setIdentityLoggedIn($event->identity, $event->duration);

        if (!$this->module->isRemembered()) {
            $event->isValid = false;
            $this->module->saveIdentityLoggedIn();

            $transports = $this->module->getEnabledTransports();
            if (is_countable($transports) && count($transports) > 1) {
                Yii::$app->response->redirect(['tfa/code/via-transport']);
                Yii::$app->end();
                return;
            }

            if (!$this->module->sendOneTimePassword()) {
                Yii::$app->session->setFlash('error', Yii::t('app', $this->module->errorSendOneTimePassword));
            }

            Yii::$app->response->redirect(['tfa/code/verify']);
            Yii::$app->end();
        }
    }
}
