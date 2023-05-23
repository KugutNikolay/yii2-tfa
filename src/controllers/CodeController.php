<?php
namespace safepartner\tfa\controllers;

use safepartner\tfa\forms\CodeForm;
use safepartner\tfa\forms\MethodForm;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;

/**
 * @author Sergey Mazurenko <zerg3000@gmail.com>
 * @author Nikolay Kugut <nikolay.kugut@gmail.com>
 * @since 1.0
 */
class CodeController extends Controller
{

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['verify', 'resend', 'via-transport'],
                        'allow' => true,
                        'roles' => ['?'],
                    ]
                ],
            ],
        ];
    }

    public function actionVerify()
    {
        $model = new CodeForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $this->module->switchIdentityLoggedIn((bool) $model->remember);
            return $this->redirect($this->user->returnUrl);
        }
        return $this->render('verify', ['model' => $model]);
    }

    public function actionResend()
    {
        $transports = $this->module->getEnabledTransports();
        if (is_countable($transports) && count($transports) > 1) {
            return $this->redirect(['code/via-transport']);
        }

        if (!$this->module->sendOneTimePassword()) {
            Yii::$app->session->setFlash('error', Yii::t('app', $this->module->errorSendOneTimePassword));
        }

        return $this->redirect(['code/verify']);
    }

    public function actionViaTransport()
    {
        $model = new MethodForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($this->module->sendOneTimePassword($model->method)) {
                return $this->redirect(['code/verify']);
            }
            Yii::$app->session->setFlash('error', Yii::t('app', $this->module->errorSendOneTimePassword));
        }
        return $this->render('via-transport', ['model' => $model]);
    }
}
