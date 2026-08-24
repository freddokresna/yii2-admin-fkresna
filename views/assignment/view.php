<?php

use mdm\admin\AnimateAsset;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\web\YiiAsset;

/** @var yii\web\View $this */
/** @var mdm\admin\models\Assignment $model */
/** @var string $usernameField */
/** @var string $fullnameField */

$userName = $model->{$usernameField};
if (!empty($fullnameField)) {
    $userName .= ' (' . ArrayHelper::getValue($model, $fullnameField) . ')';
}
$userName = Html::encode($userName);

$this->title = Yii::t('rbac-admin', 'Assignment') . ' : ' . $userName;

$this->params['breadcrumbs'][] = ['label' => Yii::t('rbac-admin', 'Assignments'), 'url' => ['index']];
$this->params['breadcrumbs'][] = $userName;

AnimateAsset::register($this);
YiiAsset::register($this);
$opts = Json::htmlEncode([
    'items' => $model->getItems(),
]);
$this->registerJs("var _opts = {$opts};");
$this->registerJs($this->render('_script.js'));
$animateIcon = ' <i class="bi bi-arrow-repeat refresh-icon"></i>';
?>
<div class="assignment-index">
    <h1><?=$this->title;?></h1>

    <div class="row">
        <div class="col-md-5">
            <input class="form-control search" data-target="available"
                   placeholder="<?=Yii::t('rbac-admin', 'Search for available');?>">
            <select multiple size="20" class="form-control list" data-target="available">
            </select>
        </div>
        <div class="col-md-1 d-flex flex-column align-items-center justify-content-center gap-2">
            <br><br>
            <?=Html::a('<i class="bi bi-chevron-double-right" aria-hidden="true"></i><span class="visually-hidden">' . Yii::t('rbac-admin', 'Assign') . '</span>' . $animateIcon, ['assign', 'id' => (string) $model->id], [
    'class' => 'btn btn-success btn-assign',
    'data-target' => 'available',
    'title' => Yii::t('rbac-admin', 'Assign'),
]);?><br><br>
            <?=Html::a('<i class="bi bi-chevron-double-left" aria-hidden="true"></i><span class="visually-hidden">' . Yii::t('rbac-admin', 'Remove') . '</span>' . $animateIcon, ['revoke', 'id' => (string) $model->id], [
    'class' => 'btn btn-danger btn-assign',
    'data-target' => 'assigned',
    'title' => Yii::t('rbac-admin', 'Remove'),
]);?>
        </div>
        <div class="col-md-5">
            <input class="form-control search" data-target="assigned"
                   placeholder="<?=Yii::t('rbac-admin', 'Search for assigned');?>">
            <select multiple size="20" class="form-control list" data-target="assigned">
            </select>
        </div>
    </div>
</div>
