<?php
namespace mdm\admin;

use yii\web\AssetBundle;

class AutocompleteAsset extends AssetBundle
{
    // Hapus path lokal yang lama
    // public $sourcePath = '@bower/jquery-ui';

    public $js = [
        // CDN jQuery UI
        'https://code.jquery.com/ui/1.13.2/jquery-ui.min.js',
    ];

    public $css = [
        'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
    ];

    public $depends = [
        'yii\web\JqueryAsset',
    ];
}
