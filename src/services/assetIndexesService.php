<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\services;

use Craft;
use craft\commerce\Plugin as PluginCommerce;
use craft\digitalproducts\Plugin as PluginDigitalProducts;
use craft\helpers\Db;
use DateTime;
use DateTimeZone;
use vnali\assetindexesextra\models\AssetIndexesOptions;
use vnali\assetindexesextra\records\AssetIndexesOptionsRecord;
use yii\base\Component;

/**
 * Asset Indexes Service class
 */
class assetIndexesService extends Component
{
    /**
     * Return all asset index option items
     *
     * @return array
     */
    public function getAllAssetIndexOptions(): array
    {
        $assetIndexesOptions = [];
        $assetIndexesRecords = AssetIndexesOptionsRecord::find()->orderBy('sortOrder asc, id desc')->all();
        /** @var AssetIndexesOptionsRecord $assetIndexesRecord */
        foreach ($assetIndexesRecords as $assetIndexesRecord) {
            $assetIndexesOption = new AssetIndexesOptions();
            $assetIndexesOption->setAttributes($assetIndexesRecord->getAttributes(), false);
            $settings = json_decode($assetIndexesRecord->settings, true);
            $currentUser = Craft::$app->getUser()->getIdentity();
            if (!$currentUser->can('assetIndexesExtra-viewAllOptions')) {
                if (is_array($settings['userIds']) && !in_array($currentUser->id, $settings['userIds'])) {
                    continue;
                }
            }
            $assetIndexesOption->settings = $settings;
            $vols = [];
            foreach ($settings['volumes'] as $volume) {
                $vol = Craft::$app->volumes->getVolumeById($volume);
                // Check if volume is still available
                if ($vol) {
                    $vols[] = $vol->name;
                }
            }
            $assetIndexesOption->settings['volumes'] = $vols;
            $sites = [];
            foreach ($settings['siteIds'] as $site) {
                $siteItem = Craft::$app->sites->getSiteById($site);
                // Check if site is still available
                if ($siteItem) {
                    $sites[] = $siteItem->name;
                }
            }
            $assetIndexesOption->settings['sites'] = $sites;
            $description = '';
            if ($settings['itemType'] == 'Entry') {
                $section = Craft::$app->entries->getSectionById($settings['sectionId']);
                if ($section) {
                    $description = $section->name;
                }
                $entryType = Craft::$app->entries->getEntryTypeById($settings['entryTypeId']);
                if ($entryType) {
                    $description = $description . ' - ' . $entryType->name;
                }
            } elseif (Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce') && $settings['itemType'] == 'Product') {
                $productType = PluginCommerce::getInstance()->getProductTypes()->getProductTypeById($settings['productTypeId']);
                if ($productType) {
                    $description = $productType->name;
                }
            } elseif (Craft::$app->plugins->isPluginInstalled('digital-products') && Craft::$app->plugins->isPluginEnabled('digital-products') && $settings['itemType'] == 'Digital Product') {
                $digitalProductType = PluginDigitalProducts::getInstance()->getProductTypes()->getProductTypeById($settings['digitalProductTypeId']);
                if ($digitalProductType) {
                    $description = $digitalProductType->name;
                }
            }
            $assetIndexesOption->settings['description'] = $description;
            $assetIndexesOptions[] = $assetIndexesOption;
        }
        return $assetIndexesOptions;
    }

    /**
     * Get Asset Indexes options by Id
     *
     * @param int $recordId
     * @return AssetIndexesOptions|null
     */
    public function getAssetIndexesOptionById(int $recordId): ?AssetIndexesOptions
    {
        $record = AssetIndexesOptionsRecord::find()
            ->where(['id' => $recordId])
            ->one();
        if ($record) {
            $AssetIndexesOption = new AssetIndexesOptions();
            /** @var AssetIndexesOptionsRecord $record */
            $tz = Craft::$app->getTimeZone();
            $dateUpdated = new DateTime($record->dateUpdated, new \DateTimeZone("UTC"));
            $tzTime = new DateTimeZone($tz);
            $dateUpdated->setTimezone($tzTime);
            $AssetIndexesOption->dateUpdated = $dateUpdated;
            $AssetIndexesOption->userId = $record->userId;
            $AssetIndexesOption->settings = $record->settings;
            return $AssetIndexesOption;
        } else {
            return null;
        }
    }

    /**
     * Reorder Asset indexes option
     * @param array $optionIds
     * @return bool
     */
    public function reorderOptions(array $optionIds): bool
    {
        foreach ($optionIds as $optionOrder => $optionId) {
            Db::update(
                '{{%assetIndexesExtra_options}}',
                [
                    'sortOrder' => $optionOrder,
                ],
                ['id' => $optionId]
            );
        }

        return true;
    }
}
