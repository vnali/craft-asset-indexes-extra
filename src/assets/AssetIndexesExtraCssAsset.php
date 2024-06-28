<?php
/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * AssetIndexesExtraCssAsset Bundle
 */
class AssetIndexesExtraCssAsset extends AssetBundle
{
    /**
    * @inheritdoc
    */
    public function init()
    {
        $this->sourcePath = '@vnali/assetindexesextra/resources';

        $this->depends = [
            CpAsset::class,
        ];

        $this->css = [
            'css/custom.css',
        ];

        parent::init();
    }
}
