<?php

namespace anubarak\relabel;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;
use craft\web\assets\matrix\MatrixAsset;

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
        $this->sourcePath = "@anubarak/relabel/ressources";

        // define the dependencies
        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/main.css'
        ];

        // define the relative path to CSS/JS files that should be registered with the page
        // when this asset bundle is registered
        $this->js = [
            'js/Relabel.js',
        ];

        parent::init();
    }
}
