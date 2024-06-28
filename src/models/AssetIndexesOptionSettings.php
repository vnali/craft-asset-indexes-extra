<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\models;

use Craft;
use craft\base\Model;
use craft\commerce\Plugin as PluginCommerce;
use craft\db\Table;
use craft\digitalproducts\Plugin as PluginDigitalProducts;
use craft\enums\PropagationMethod;
use craft\helpers\Db;
use craft\validators\SiteIdValidator;
use vnali\assetindexesextra\AssetIndexesExtra;
use vnali\assetindexesextra\records\AssetIndexesOptionsRecord;

class AssetIndexesOptionSettings extends Model
{
    /**
     * Id of asset index option settings
     *
     * @var int|null
     */
    public ?int $id = null;

    public ?string $itemType = null;

    /**
     * @var int[]|string Volumes for importing items
     */
    public array|string $volumes = [];

    /**
     * @var int[]|string|null Site Ids to save/propagate to
     */
    public array|string|null $siteIds = null;

    public ?int $sectionId = null;

    public ?int $entryTypeId = null;

    public ?int $productTypeId = null;

    public ?int $taxCategoryId = null;

    public ?int $digitalProductTypeId = null;

    public ?string $description = null;

    public mixed $mapping = null;

    public mixed $userIds = null;

    public ?bool $log = null;

    /**
     * @var bool|null If this option is checked when asset indexes happen
     */
    public ?bool $enable = null;

    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['volumes', 'itemType', 'siteIds'], 'required'];
        $rules[] = [['enable'], 'in', 'range' => [0, 1]];
        $rules[] = [['siteIds'], 'each', 'rule' => [SiteIdValidator::class]];
        $rules[] = [['siteIds'], function($attribute, $params, $validator) {
            if (Craft::$app->getIsMultiSite()) {
                if ($this->sectionId) {
                    $section = Craft::$app->entries->getSectionById($this->sectionId);
                }
                if (is_array($this->siteIds) && (!isset($section) || $section->propagationMethod !== PropagationMethod::Custom) && count($this->siteIds) > 1) {
                    $this->addError($attribute, craft::t('asset-indexes-extra', 'The only one site should be selected for this item'));
                } else {
                    if (isset($section)) {
                        $siteSettings = $section->getSiteSettings();
                        $sites = array_keys($siteSettings);
                    } else {
                        $sites = [];
                    }
                    if (is_array($this->siteIds)) {
                        $siteService = Craft::$app->sites;
                        foreach ($this->siteIds as $siteId) {
                            if (count($sites) > 0 && !in_array($siteId, $sites)) {
                                $site = $siteService->getSiteById($siteId) ?? '';
                                $this->addError($attribute, 'The site ' . $site . ' cannot be selected for the selected section');
                                break;
                            }
                            $siteUid = Db::uidById(Table::SITES, $siteId);
                            $currentUser = Craft::$app->getUser()->getIdentity();
                            if (!$currentUser->can('editSite:' . $siteUid)) {
                                $site = $siteService->getSiteById($siteId) ?? '';
                                $this->addError($attribute, 'The user cannot access site: ' . $site);
                                break;
                            }
                        }
                    }
                }
            }
        }, 'skipOnEmpty' => false];
        $rules[] = [['volumes'], function($attribute, $params, $validator) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            $pluginSettings = AssetIndexesExtra::$plugin->getSettings();
            // Allow only sites that user has access
            if (is_array($this->$attribute)) {
                foreach ($this->$attribute as $key => $volumeId) {
                    $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);
                    /** @var Settings $pluginSettings */
                    if ($pluginSettings->checkAccessToVolumes == 'view' && !$currentUser->can('viewAssets:' . $volume->uid)) {
                        $this->addError($attribute, 'The user cannot view the volume');
                        break;
                    } elseif ($pluginSettings->checkAccessToVolumes == 'save' && !$currentUser->can('saveAssets:' . $volume->uid)) {
                        $this->addError($attribute, 'The user cannot save the volume');
                        break;
                    }
                }
            }
        }, 'skipOnEmpty' => false];
        $rules[] = [['itemType'], function($attribute, $params, $validator) {
            if ($this->id) {
                $option = AssetIndexesOptionsRecord::findOne($this->id);
                if ($option) {
                    $optionSettings = json_decode($option->settings, true);
                    if (!$optionSettings['itemType'] || ($optionSettings['itemType'] != $this->itemType)) {
                        if (AssetIndexesExtra::$plugin->logs->getOneLog($this->id)) {
                            $this->addError($attribute, 'The item type cannot be changed.');
                        }
                    }
                }
            }

            $settings = new AssetIndexesOptionSettings();
            $itemTypes = array_keys($settings->itemTypes());
            if (!in_array($this->$attribute, $itemTypes)) {
                $this->addError($attribute, 'item type is not valid');
            }
        }, 'skipOnEmpty' => false];
        $rules[] = [['sectionId'], function($attribute, $params, $validator) {
            $currentUser = Craft::$app->getUser()->getIdentity();
            $section = Craft::$app->entries->getSectionById($this->sectionId);
            if (!$section || !$currentUser->can('viewEntries:' . $section->uid)) {
                $this->addError($attribute, 'section is not valid');
            }
        }, 'skipOnEmpty' => true];
        $rules[] = [['entryTypeId'], function($attribute, $params, $validator) {
            $entryTypes = Craft::$app->entries->getEntryTypesBySectionId($this->sectionId);
            $found = false;
            foreach ($entryTypes as $entryType) {
                if ($this->entryTypeId == $entryType->id) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $this->addError($attribute, 'entry type is not valid');
            }
        }, 'skipOnEmpty' => true];
        $rules[] = [['productTypeId'], function($attribute, $params, $validator) {
            if ($this->productTypeId) {
                $currentUser = Craft::$app->getUser()->getIdentity();
                $productType = PluginCommerce::getInstance()->getProductTypes()->getProductTypeById($this->productTypeId);
                if (!$productType || !$currentUser->can('commerce-createProducts:' . $productType->uid)) {
                    $this->addError($attribute, 'product type is not valid');
                }
            } elseif ($this->itemType == 'Product') {
                $this->addError($attribute, 'Product type is required');
            }
        }, 'skipOnEmpty' => true];
        $rules[] = [['digitalProductTypeId'], function($attribute, $params, $validator) {
            if ($this->digitalProductTypeId) {
                $currentUser = Craft::$app->getUser()->getIdentity();
                $digitalProductType = PluginDigitalProducts::getInstance()->getProductTypes()->getProductTypeById($this->digitalProductTypeId);
                if (!$digitalProductType || !$currentUser->can('digitalProducts-manageProductType:' . $digitalProductType->uid)) {
                    $this->addError($attribute, 'digital product type is not valid');
                }
            } elseif ($this->itemType == 'Digital Product') {
                $this->addError($attribute, 'Digital Product type Id is required');
            }
        }, 'skipOnEmpty' => true];
        $rules[] = [['taxCategoryId'], function($attribute, $params, $validator) {
            if ($this->itemType == 'Digital Product') {
                if (!$this->taxCategoryId) {
                    $this->addError($attribute, 'Tax category Id is required');
                } elseif (!PluginCommerce::getInstance()->getTaxCategories()->getTaxCategoryById($this->taxCategoryId)) {
                    $this->addError($attribute, 'Tax category Id is not valid');
                };
            }
        }, 'skipOnEmpty' => true];
        $rules[] = [['userIds'], function($attribute, $params, $validator) {
            if (Craft::$app->edition->name == 'Solo') {
                $this->addError($attribute, 'You cannot specify user Ids in Craft Solo');
            }
            $currentUser = Craft::$app->getUser()->getIdentity();
            if ($this->userIds && !$currentUser->can('editUsers')) {
                $this->addError($attribute, 'User has not access to select users');
            }
            if ($this->userIds) {
                foreach ($this->userIds as $userId) {
                    $user = Craft::$app->users->getUserById($userId);
                    if (!$user) {
                        $this->addError($attribute, 'User is not found.');
                    }
                    if (!$user->can('utility:asset-indexes')) {
                        $this->addError($attribute, 'User ' . $user->username . ' has not access to asset indexes utility');
                    }
                }
            }
        }, 'skipOnEmpty' => true];
        return $rules;
    }

    /**
     * Mapping attributes
     *
     * @return array
     */
    public function mappingAttributes(): array
    {
        return [
            'mainAsset' => [
                'label' => 'Main Asset',
                'handle' => 'mainAsset',
                'convertTo' => [
                    '' => craft::t('asset-indexes-extra', 'Select one'),
                    'craft\fields\Assets' => 'asset',
                ],
            ],
        ];
    }

    /**
     * Return all Item Types
     *
     * @return array
     */
    public function itemTypes(): array
    {
        $items = ['Entry'];
        if (Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
            $items[] = 'Product';
        }
        if (Craft::$app->plugins->isPluginInstalled('digital-products') && Craft::$app->plugins->isPluginEnabled('digital-products')) {
            $items[] = 'Digital Product';
        }
        $itemType = [];
        $itemType['value'] = '';
        $itemType['label'] = craft::t('asset-indexes-extra', 'Select one');
        $itemTypes[] = $itemType;
        foreach ($items as $item) {
            $itemType = [];
            $itemType['value'] = $item;
            $itemType['label'] = craft::t('asset-indexes-extra', $item);
            $itemTypes[$item] = $itemType;
        }
        return $itemTypes;
    }
}
