<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

?>

<h3>Two-factor Authentication</h3>

<?php $form = ActiveForm::begin(); ?>

<?= $form->field($model, 'method')->radioList($model->getVerificationMethods(), ['separator' => '<br>']); ?>

<div class="form-group">
    <?= Html::submitButton(Yii::t('app', 'Send code'), ['class' => 'btn btn-primary btn-block']); ?>
</div>

<?php ActiveForm::end(); ?>
