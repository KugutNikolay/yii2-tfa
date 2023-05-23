<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

?>

<h3>Two-factor Authentication</h3>

<?php $form = ActiveForm::begin(); ?>

<?= $form->field($model, 'otp'); ?>

<?= $form->field($model, 'remember')->checkbox(); ?>

<div class="form-group">
    <?= Html::submitButton(Yii::t('app', 'Verify code'), ['class' => 'btn btn-primary btn-block']); ?>
</div>

<div class="form-group">
    <?= Html::a(Yii::t('app', 'Resend code'), Url::toRoute(['code/resend']), ['class' => 'btn btn-warning btn-block']); ?>
</div>

<?php ActiveForm::end(); ?>
