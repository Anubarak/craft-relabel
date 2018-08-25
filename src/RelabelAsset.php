<?php
/**
 * relabel plugin for Craft CMS 3.x
 *
 * Relabel Plugin Craft
 *
 * @copyright Copyright (c) 2018 anubarak
 */

namespace anubarak\relabel;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class RelabelAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    /**
     * Initializes the bundle.
     */
    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = "@anubarak/relabel/resources";

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];
        $this->css = [
            'css/main.css'
        ];
        $this->js = [
            'js/Relabel.js',
        ];

        parent::init();
    }
}
