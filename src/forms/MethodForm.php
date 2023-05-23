<?php
namespace safepartner\tfa\forms;

use safepartner\tfa\Module;
use Yii;

/**
 * Select verification methods input form.
 *
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
class MethodForm extends \yii\base\Model
{

    /**
     * @var string verification method
     */
    public $method;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['method', 'required'],
            ['method', 'validateMethod'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'method' => Yii::t('app', 'Verification method'),
        ];
    }

    /**
     * Validates the selected method.
     * This method serves as the inline validation for method.
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validateMethod($attribute, $params)
    {
        if (!$this->hasErrors()) {
            /** @var Module $module */
            $module = Yii::$app->controller->module;
            $transports = array_keys($module->getTransports());

            if (!in_array($this->method, $transports)) {
                $this->addError($attribute, 'Incorrect method.');
            }
        }
    }

    public function getVerificationMethods()
    {
        /** @var Module $module */
        $module = Yii::$app->controller->module;
        $transports = $module->getTransports();

        $list = [];
        foreach ($transports as $name => $transport) {
            if ($transport instanceof \safepartner\tfa\transport\FileTransport) {
                continue;
            }

            $data = $module->getIdentityLoggedIn();
            if (!$data && !isset($data['identity'])) {
                continue;
            }

            $list[$name] = Yii::t('app', '{name} ({to})', ['name' => $transport->getName(), 'to' => $transport->obfuscateTo($data['identity'])]);
        }
        return $list;
    }
}
