<?php

/**
 * @copyright Copyright © vnali
 */

namespace vnali\assetindexesextra\services;

use Craft;
use craft\base\Component;
use craft\base\ElementInterface;
use craft\commerce\elements\Product;
use craft\commerce\Plugin as PluginCommerce;
use craft\digitalproducts\elements\Product as DigitalProduct;
use craft\digitalproducts\Plugin as PluginDigitalProducts;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\enums\PropagationMethod;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use InvalidArgumentException;
use vnali\assetindexesextra\records\AssetIndexesLogRecord;
use vnali\assetindexesextra\records\AssetIndexesOptionsRecord;

class ImporterService extends Component
{
    /**
     * Create items (currently only entries) from assets index
     *
     * @param ElementInterface $element
     * @param AssetIndexesOptionsRecord $assetIndexesOption
     * @param AssetIndexesLogRecord|null $logRecord
     * @param array $info
     * @return void
     */
    public function importByAssetIndex(ElementInterface $element, AssetIndexesOptionsRecord $assetIndexesOption, ?AssetIndexesLogRecord $logRecord = null, array &$info = []): void
    {
        // PHP Stan fix
        if (!$element instanceof Asset) {
            throw new InvalidArgumentException('Import item can only be used for asset elements.');
        }

        $setting = json_decode($assetIndexesOption->settings, true);

        $itemType = $setting['itemType'];
        $mapping = $setting['mapping'];
        $warning = [];
        $currentUser = Craft::$app->getUser()->getIdentity();
        $cli = null;
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $cli = true;
            $username = null;
        } else {
            $currentUser = Craft::$app->getUser()->getIdentity();
            $username = $currentUser->username;
        }

        try {
            if ($setting['itemType'] == 'Entry') {
                $sectionService = Craft::$app->getEntries();
                $section = $sectionService->getSectionById((int) $setting['sectionId']);

                $propagationMethod = $section->propagationMethod->value;
                $sectionExtraInfo['Section Name'] = $section->name;
                $sectionExtraInfo['Propagation Method'] = $propagationMethod;

                if (!isset($section->uid)) {
                    throw new \Exception('The section is not valid');
                }
                if (!$cli && !$currentUser->can('saveEntries:' . $section->uid)) {
                    throw new \Exception('User cannot create in this section: ' . $section->name);
                }

                $entryType = $sectionService->getEntryTypeById((int) $setting['entryTypeId']);
                if (!$entryType) {
                    throw new \Exception('The entry type is not valid');
                }

                // Create and populate the entry
                $itemElement = new Entry([
                    'sectionId' => $setting['sectionId'],
                    'authorId' => '',
                    'typeId' => $setting['entryTypeId'],
                    'title' => $element->title,
                    'slug' => $element->title,
                    'postDate' => DateTimeHelper::toDateTime('now'),
                ]);

                $siteSettings = $section->getSiteSettings();
            } elseif ($setting['itemType'] == 'Product' && class_exists('craft\commerce\Plugin') && Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
                $productType = PluginCommerce::getInstance()->getProductTypes()->getProductTypeById($setting['productTypeId']);

                if (!isset($productType->uid)) {
                    throw new \Exception('The product type is not valid');
                }

                if (!$cli && !$currentUser->can('commerce-createProducts:' . $productType->uid)) {
                    throw new \Exception('User cannot save in this product type: ' . ($productType->name ?? ''));
                }

                $itemElement = new Product();
                $itemElement->typeId = $setting['productTypeId'];
                $itemElement->title = $element->title;
                $itemElement->slug = $element->title;
                $siteSettings = $itemElement->getType()->getSiteSettings();
            } elseif ($setting['itemType'] == 'Digital Product' && class_exists('craft\digitalproducts\Plugin') && Craft::$app->plugins->isPluginInstalled('digital-products') && Craft::$app->plugins->isPluginEnabled('digital-products')) {
                $productType = PluginDigitalProducts::getInstance()->getProductTypes()->getProductTypeById($setting['digitalProductTypeId']);

                if (!isset($productType->uid)) {
                    throw new \Exception('The product type is not valid');
                }
                if (!$cli && !$currentUser->can('digitalProducts-manageProductType:' . $productType->uid)) {
                    throw new \Exception('User cannot save in this product type.');
                }

                $itemElement = new DigitalProduct();
                $itemElement->typeId = $setting['digitalProductTypeId'];
                $productType = $itemElement->getType();
                $itemElement->title = $element->title;
                $itemElement->slug = $element->title;
                $itemElement->sku = Craft::$app->getView()->renderObjectTemplate($productType->skuFormat, $itemElement);
                $itemElement->taxCategoryId = $setting['taxCategoryId'];
                $itemElement->price = 0;
                $siteSettings = $itemElement->getType()->getSiteSettings();
            } else {
                throw new \Exception('The  ' . $setting['itemType'] . ' item type is not available.');
            }

            // Set site status for item
            // Entry can be propagated to some sites, but product and digital product propagate to all
            $siteFound = false;
            $siteStatus = [];
            $siteIds = $setting['siteIds'] ?? [];
            $siteService = Craft::$app->sites;
            $notAllowedSite = [];

            if (is_array($siteIds) and isset($siteIds[0])) {
                $firstSiteId = $siteIds[0];
            } else {
                $requestedSite = Cp::requestedSite();
                if (!$requestedSite) {
                    throw new \Exception('User has no access to edit any site.');
                }
                $firstSiteId = $requestedSite->id;
            }

            foreach ($siteSettings as $key => $siteSettings) {
                $site = $siteService->getSiteById($key);
                // if it is a deleted site
                if (!$site) {
                    continue;
                }
                if (isset($section)) {
                    $sectionExtraInfo['Supported Sites'][$key] = $site->name;
                }
                if (Craft::$app->getIsMultiSite()) {
                    // Make sure they have access to this site
                    if (!$cli && !$currentUser->can('editSite:' . $site->uid)) {
                        // if user has not access to the first site
                        if ($key == $firstSiteId && (!isset($section) || $section->propagationMethod !== PropagationMethod::Custom)) {
                            throw new \Exception('User has no access to the site ' . $site->name);
                        }
                        // elements are propagated anyway if they are not entries with custom or none propagation method
                        if (!isset($section) || ($section->propagationMethod !== PropagationMethod::None && $section->propagationMethod !== PropagationMethod::Custom)) {
                            $siteFound = true;
                            $siteStatus[$key] = false;
                        } elseif (in_array($key, $siteIds)) {
                            $notAllowedSite[] = $site->name;
                        }
                        continue;
                    }
                }

                // For items that propagate to all or for supported siteIds set site status for false
                if (!isset($section) || ($section->propagationMethod !== PropagationMethod::None && $section->propagationMethod !== PropagationMethod::Custom) || ($section->propagationMethod === PropagationMethod::Custom && in_array($key, $siteIds))) {
                    $siteFound = true;
                    $siteStatus[$key] = false;
                }

                // For none propagation method set status to false for first site and break
                if (isset($section) && $section->propagationMethod === PropagationMethod::None && $key == $firstSiteId) {
                    $siteFound = true;
                    $siteStatus[$key] = false;
                    break;
                }
            }

            if (count($notAllowedSite) > 0) {
                $warning[] = 'Not allowed sites: ' . implode(', ', $notAllowedSite);
            }

            if (!$siteFound || (!in_array($firstSiteId, array_keys($siteStatus)) && (!isset($section) || $section->propagationMethod !== PropagationMethod::Custom))) {
                $site = $siteService->getSiteById($firstSiteId);
                $siteName = 'siteId ' . $firstSiteId . ' Not Found';
                if ($site) {
                    $siteName = $site->name;
                }
                throw new \Exception('Not Supported Site: ' . $siteName);
            }
            // if firstSiteId is not valid, change it to another site for custom propagation method
            if (!in_array($firstSiteId, array_keys($siteStatus)) && isset($section) && $section->propagationMethod === PropagationMethod::Custom) {
                reset($siteStatus);
                // Get the key of the first element
                $firstKey = key($siteStatus);
                $firstSiteId = $firstKey;
            }
            $itemElement->siteId = $firstSiteId;
            $itemElement->setEnabledForSite($siteStatus);

            $itemFieldId = null;
            $itemFieldContainer = null;
            $fieldsService = Craft::$app->getFields();

            if (isset($mapping['mainAsset']['container'])) {
                $itemFieldContainer = $mapping['mainAsset']['container'];
            }
            if (isset($mapping['mainAsset']['field']) && $mapping['mainAsset']['field']) {
                $itemFieldId = $mapping['mainAsset']['field'];
                $itemField = $fieldsService->getFieldByUid($itemFieldId);
                if ($itemField) {
                    $itemFieldHandle = $itemField->handle;
                }
            }

            if (isset($itemFieldHandle)) {

                /** @var string|null $container0Type */
                $container0Type = null;
                /** @var string|null $container0Handle */
                $container0Handle = null;
                /** @var string|null $container1Handle */
                $container1Handle = null;
                if (!$itemFieldContainer) {
                    // TODO: check if we can set an asset to an item field which volume of that asset is not supported by field volume
                    $itemElement->{$itemFieldHandle} = [$element->id];
                } else {
                    $fieldContainers = explode('|', $itemFieldContainer);
                    foreach ($fieldContainers as $key => $fieldContainer) {
                        $containerHandleVar = 'container' . $key . 'Handle';
                        $containerTypeVar = 'container' . $key . 'Type';
                        $container = explode('-', $fieldContainer);
                        if (isset($container[0])) {
                            $$containerHandleVar = $container[0];
                        }
                        if (isset($container[1])) {
                            $$containerTypeVar = $container[1];
                        }
                    }
                }

                $itemEntryType = null;
                if ($container0Handle) {
                    if ($container0Type) {
                        $itemEntryType = $container1Handle;
                    }
                    if (isset($itemEntryType)) {
                        $data = [];
                        $containerFields = [];
                        if (isset($container0Type)) {
                            $containerFields[$itemFieldHandle] = [$element->id];
                        }
                        $data['new1'] = [
                            'type' => $itemEntryType,
                            'fields' => $containerFields,
                        ];
                        $itemElement->setFieldValue($container0Handle, $data);
                    }
                }
            }

            if (!Craft::$app->getElements()->saveElement($itemElement)) {
                if ($setting['log']) {
                    $logRecord->status = (isset($logRecord->status) && $logRecord->status == 1) ? 2 : 0;
                    $logRecord->result = $logRecord->result . ' Error: ' . json_encode($itemElement->getErrors()) . ((count($warning) > 0) ? ' Warning: ' . json_encode($warning) : '');
                }
            } else {
                if ($setting['log']) {
                    $logRecord->status = (count($warning) ? 3 : 1);
                    $logRecord->result = $logRecord->result . ' ' . 'The ' . $itemElement->title . " $itemType" . ' is created by ' . $username . '. ' . (count($warning) ? ' Warning: ' . json_encode($warning) : '');
                    $logRecord->itemId = $itemElement->id;
                }
            }
        } catch (\Exception $e) {
            if ($setting['log']) {
                $logRecord->status = (isset($logRecord->status) && $logRecord->status == 1) ? 2 : 0;
                $logRecord->result = $logRecord->result . ' Error: ' . $e->getMessage() . (count($warning) ? ' Warning: ' . json_encode($warning) : '');
            }
        }

        if ($setting['log']) {
            $logRecord->optionId = $assetIndexesOption->id;
            $logRecord->itemType = $setting['itemType'];
            $info['assetIndexOption']['id'] = $assetIndexesOption->id;
            $info['assetIndexOption']['sortOrder'] = $assetIndexesOption->sortOrder;
            $info['assetIndexOption']['userId'] = $assetIndexesOption->userId;
            $info['assetIndexOption']['dateCreated'] = $assetIndexesOption->dateCreated;
            $info['assetIndexOption']['dateUpdated'] = $assetIndexesOption->dateUpdated;
            $info['assetIndexOption']['settings'] = $setting;
            if (isset($sectionExtraInfo)) {
                $info['assetIndexOption']['Section Info'] = $sectionExtraInfo;
            }
        }
    }
}
