<?php

namespace mdm\admin;

use yii\web\AssetBundle;

/**
 * AutocompleteAsset
 *
 * @author Misbahul D Munir <misbahuldmunir@gmail.com>
 * @since 1.0
 */
class AutocompleteAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public $sourcePath = '@mdm/admin/assets';
    /**
     * @inheritdoc
     */
     public $css = [
        // '@bower/jquery-ui/themes/base/all.css',  // hapus
        'https://code.jquery.com/ui/1.13.3/themes/base/jquery-ui.css',
    ];

    public $js = [
        // '@bower/jquery/dist/jquery.js',         // hapus
        // '@bower/jquery-ui/jquery-ui.js',        // hapus
        'https://code.jquery.com/jquery-3.7.1.min.js',
        'https://code.jquery.com/ui/1.13.3/jquery-ui.min.js',
    ];
 
    /**
     * @inheritdoc
     */
    public $depends = [
        'yii\web\JqueryAsset',
    ];

}
