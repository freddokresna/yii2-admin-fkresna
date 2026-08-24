<?php

use yii\bootstrap5\NavBar;
use yii\bootstrap5\Nav;
use yii\bootstrap5\BootstrapIconAsset;
use yii\helpers\Html;

/** @var \yii\web\View $this */
/** @var string $content */

list(,$url) = Yii::$app->assetManager->publish('@mdm/admin/assets');
$this->registerCssFile($url.'/main.css');
BootstrapIconAsset::register($this);

?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?= Html::csrfMetaTags() ?>
        <title><?= Html::encode($this->title) ?></title>
        <?php $this->head() ?> 
    </head>
    <body>
        <?php $this->beginBody() ?>
        <?php
        NavBar::begin([
            'brandLabel' => false,
            'options' => ['class' => 'navbar-dark bg-dark fixed-top shadow-sm'],
        ]);

        if (!empty($this->params['top-menu']) && isset($this->params['nav-items'])) {
            echo Nav::widget([
                'options' => ['class' => 'navbar-nav me-auto'],
                'items' => $this->params['nav-items'],
            ]);
        }

        echo Nav::widget([
            'options' => ['class' => 'navbar-nav ms-auto'],
            'items' => $this->context->module->navbar,
         ]);
        NavBar::end();
        ?>

        <div class="container">
            <?= $content ?>
        </div>

        <footer class="footer">
            <div class="container">
                <p class="text-end mb-0"><?= Yii::powered() ?></p>
            </div>
        </footer>

        <?php $this->endBody() ?>
    </body>
</html>
<?php $this->endPage() ?>
