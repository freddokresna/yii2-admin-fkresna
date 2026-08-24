<?php

use mdm\admin\AnimateAsset;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\YiiAsset;

/** @var yii\web\View $this */
/** @var array $routes */

$this->title = Yii::t('rbac-admin', 'Routes');
$this->params['breadcrumbs'][] = $this->title;

AnimateAsset::register($this);
YiiAsset::register($this);
$opts = Json::htmlEncode([
    'routes' => $routes,
]);
$this->registerJs("var _opts = {$opts};");
$this->registerJs($this->render('_script.js'));
$animateIcon = ' <i class="bi bi-arrow-repeat spinner-icon" style="display:none;"></i>';

?>
<h1><?= Html::encode($this->title); ?></h1>
<div class="row">
    <div class="col-12">
        <div class="input-group mb-3">
            <input id="inp-route" type="text" class="form-control"
                placeholder="<?= Yii::t('rbac-admin', 'New route(s)'); ?>">
            <?= Html::a(Yii::t('rbac-admin', 'Add') . $animateIcon, ['create'], [
                    'class' => 'btn btn-success',
                    'id' => 'btn-new',
            ]); ?>
        </div>
    </div>
</div>
<p>&nbsp;</p>
<div class="row">
    <div class="col-md-5">
        <div class="input-group">
            <input class="form-control search" data-target="available"
                placeholder="<?= Yii::t('rbac-admin', 'Search for available'); ?>">
                <?= Html::a('<i class="bi bi-arrow-repeat spinner-icon" aria-hidden="true"></i><span class="visually-hidden">' . Yii::t('rbac-admin', 'Refresh') . '</span>', ['refresh'], [
                    'class' => 'btn btn-outline-secondary icon-only',
                    'id' => 'btn-refresh',
                ]); ?>
        </div>
        <select multiple size="20" class="form-control list" data-target="available"></select>
    </div>
    <div class="col-md-1 d-flex flex-column align-items-center justify-content-center gap-2">
        <?= Html::a('<i class="bi bi-chevron-double-right" aria-hidden="true"></i><span class="visually-hidden">' . Yii::t('rbac-admin', 'Assign') . '</span>' . $animateIcon, ['assign'], [
            'class' => 'btn btn-success btn-assign',
            'data-target' => 'available',
            'title' => Yii::t('rbac-admin', 'Assign'),
        ]); ?>
        <?= Html::a('<i class="bi bi-chevron-double-left" aria-hidden="true"></i><span class="visually-hidden">' . Yii::t('rbac-admin', 'Remove') . '</span>' . $animateIcon, ['remove'], [
            'class' => 'btn btn-danger btn-assign',
            'data-target' => 'assigned',
            'title' => Yii::t('rbac-admin', 'Remove'),
        ]); ?>
    </div>
    <div class="col-md-5">
        <input class="form-control search" data-target="assigned"
            placeholder="<?= Yii::t('rbac-admin', 'Search for assigned'); ?>">
        <select multiple size="20" class="form-control list" data-target="assigned"></select>
    </div>
</div>