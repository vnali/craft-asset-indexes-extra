<?php
/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\twig;

use vnali\assetindexesextra\AssetIndexesExtra;

use yii\base\Behavior;

class CraftVariableBehavior extends Behavior
{
    /**
     * @var AssetIndexesExtra
     */
    public AssetIndexesExtra $assetIndexesExtra;

    public function init(): void
    {
        parent::init();

        $this->assetIndexesExtra = AssetIndexesExtra::getInstance();
    }
}
