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
        'dist/themes/base/jquery-ui.css',
    ];
    /**
     * @inheritdoc
     */
    public $js = [
        'dist/jquery-ui.js',
    ];
    /**
     * @inheritdoc
     */
    public $depends = [
        'yii\web\JqueryAsset',
    ];

}
