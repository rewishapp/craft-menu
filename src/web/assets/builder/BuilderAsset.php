<?php

namespace rewish\craft\menu\web\assets\builder;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class BuilderAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__.'/dist';
        $this->depends = [CpAsset::class];
        $this->js = ['builder.js'];
        $this->css = ['builder.css'];
        parent::init();
    }
}
