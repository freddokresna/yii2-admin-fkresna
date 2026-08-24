<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/**
 * @var yii\web\View $this
 * @var mdm\admin\models\searchs\Menu $model
 * @var yii\widgets\ActiveForm $form
 */
?>

<div class="menu-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'name') ?>

    <?= $form->field($model, 'parent') ?>

    <?= $form->field($model, 'route') ?>

    <?= $form->field($model, 'data') ?>

    <div class="mb-3">
        <?= Html::submitButton(Yii::t('rbac-admin', 'Search'), ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton(Yii::t('rbac-admin', 'Reset'), ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
