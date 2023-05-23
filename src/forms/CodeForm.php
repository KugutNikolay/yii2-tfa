<?php
namespace safepartner\tfa\forms;

use safepartner\tfa\Module;
use Yii;

/**
 * OTP (One Time Password) input form.
 *
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
class CodeForm extends \yii\base\Model
{

    /**
     * @var string OTP (One Time Password)
     */
    public $otp;

    /**
     * @var string Whether to remember the user
     */
    public $remember;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['otp', 'trim'],
            ['otp', 'required'],
            ['otp', 'validateCode'],
            ['remember', 'boolean']
        ];
    }

    public function validateCode($attribute)
    {
        if ($this->hasErrors()) {
            return;
        }

        /** @var Module $module */
        $module = Yii::$app->controller->module;

        if (!$module->validateOneTimePasswordNotExpire($this->otp)) {
            $this->addError($attribute, Yii::t('app', 'Authentication code has expired. Please re-send the verification code to try again.'));
            return;
        }

        if (!$module->validateOneTimePassword($this->otp)) {
            $this->addError($attribute, Yii::t('app', 'Invalid authentication code'));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'otp' => Yii::t('app', 'Authentication code'),
            'remember' => Yii::t('app', 'Trust this computer'),
        ];
    }
}
