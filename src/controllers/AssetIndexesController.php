<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\controllers;

use Craft;
use craft\commerce\Plugin as PluginCommerce;
use craft\db\Table;
use craft\digitalproducts\Plugin as PluginDigitalProducts;
use craft\enums\PropagationMethod;
use craft\helpers\Db;
use craft\web\Controller;
use craft\web\UrlManager;
use vnali\assetindexesextra\AssetIndexesExtra;
use vnali\assetindexesextra\helpers\GeneralHelper;
use vnali\assetindexesextra\models\AssetIndexesOptions;
use vnali\assetindexesextra\models\AssetIndexesOptionSettings;
use vnali\assetindexesextra\models\Mapping;
use vnali\assetindexesextra\models\Settings;
use vnali\assetindexesextra\records\AssetIndexesOptionsRecord;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

class AssetIndexesController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Require permission
        return parent::beforeAction($action);
    }

    /**
     * Asset Indexes options index page
     * @return Response
     */
    public function actionIndex(): Response
    {
        $this->requirePermission('assetIndexesExtra-viewAssignedOptions');
        // Require permission
        return $this->renderTemplate('asset-indexes-extra/asset-indexes/_index.twig');
    }

    /**
     * Edit Asset indexes Options
     *
     * @param int|null $recordId
     * @param AssetIndexesOptionSettings|null $settings
     * @param AssetIndexesOptions|null $options
     * @return Response
     */
    public function actionEdit(?int $recordId = null, ?AssetIndexesOptionSettings $settings = null, ?AssetIndexesOptions $options = null): Response
    {
        $this->requirePermission('assetIndexesExtra-manageOptions');
        if ($recordId) {
            if ($settings === null) {
                $options = AssetIndexesExtra::$plugin->assetIndexes->getAssetIndexesOptionById($recordId);
                if (!$options) {
                    throw new ServerErrorHttpException('Option can not be found');
                }
                $assetIndexesOptionSettings = new AssetIndexesOptionSettings();
                $optionSettings = json_decode($options->settings);
                foreach ($optionSettings as $key => $setting) {
                    // Check if we still have this record property also on model
                    if (property_exists($assetIndexesOptionSettings, $key)) {
                        $assetIndexesOptionSettings->$key = $setting;
                    }
                }
                $settings = $assetIndexesOptionSettings;
            }
            $variables['recordId'] = $recordId;
        } else {
            if ($settings === null) {
                $settings = new AssetIndexesOptionSettings();
            }
            $options = new AssetIndexesOptions();
            $variables['recordId'] = null;
        }

        if ($settings->userIds) {
            $users = [];
            foreach ($settings->userIds as $user) {
                if (Craft::$app->users->getUserById($user)) {
                    $users[] = Craft::$app->users->getUserById($user);
                }
            }
            $settings->userIds = $users;
        }
        $variables['options'] = $options;
        $variables['settings'] = $settings;

        $currentUser = Craft::$app->getUser()->getIdentity();

        $pluginSettings = AssetIndexesExtra::$plugin->getSettings();
        $variables['volumes'][] = ['value' => '', 'label' => Craft::t('asset-indexes-extra', 'Select the volume')];
        foreach (Craft::$app->volumes->getAllVolumes() as $volumeItem) {
            // Allow only volumes that user has access based on the plugin settings
            /** @var Settings $pluginSettings */
            if ($pluginSettings->checkAccessToVolumes == 'view' && !$currentUser->can('viewAssets:' . $volumeItem->uid)) {
                continue;
            } elseif ($pluginSettings->checkAccessToVolumes == 'save' && !$currentUser->can('saveAssets:' . $volumeItem->uid)) {
                continue;
            }
            $volume['value'] = $volumeItem->id;
            $volume['label'] = $volumeItem->name;
            $variables['volumes'][] = $volume;
        }

        $variables['itemTypes'] = $settings->itemTypes();

        $sectionWithSiteIds = [];
        $customSectionsIds = [];
        $propagationMethods = [];
        $variables['sections'][] = ['value' => '', 'label' => Craft::t('asset-indexes-extra', 'Select one')];
        foreach (Craft::$app->entries->getEditableSections() as $section) {
            if ($currentUser->can('viewEntries:' . $section->uid)) {
                if ($section->type == 'structure' || $section->type == 'channel') {
                    $sections['value'] = $section->id;
                    $sections['label'] = $section->name;
                    $variables['sections'][] = $sections;
                    switch ($section->propagationMethod->value) {
                        case 'all':
                            $sectionInstruction = Craft::t('asset-indexes-extra', 'Save entries to this site and propagate to other supported sites');
                            break;
                        case 'none':
                            $sectionInstruction = Craft::t('asset-indexes-extra', 'Only save entries to this site');
                            break;
                        case 'custom':
                            $sectionInstruction = Craft::t('asset-indexes-extra', 'Save entries to these sites');
                            break;
                        case 'siteGroup':
                            $sectionInstruction = Craft::t('asset-indexes-extra', 'Save entries to this site and other sites in the same site group');
                            break;
                        case 'language':
                            $sectionInstruction = Craft::t('asset-indexes-extra', 'Save entries to this site and other sites with the same language');
                            break;
                        default:
                            $sectionInstruction = '';
                            break;
                    }
                    $propagationMethods[$section->id] = $sectionInstruction;
                    $sectionWithSiteIds[] = $section->id;
                    if ($section->propagationMethod === PropagationMethod::Custom) {
                        $customSectionsIds[] = $section->id;
                    }
                }
            }
        }
        $propagationMethods['others'] = Craft::t('asset-indexes-extra', 'Save items to this site and propagate to other supported sites');
        $variables['sectionWithSiteIds'] = json_encode($sectionWithSiteIds);
        $variables['customSectionsIds'] = json_encode($customSectionsIds);
        $variables['propagationMethods'] = json_encode($propagationMethods);

        $variables['entryTypes'][] = ['value' => '', 'label' => Craft::t('asset-indexes-extra', 'Select one')];
        if ($settings->sectionId) {
            foreach (Craft::$app->entries->getEntryTypesBySectionId($settings->sectionId) as $entryType) {
                $entryTypes['value'] = $entryType->id;
                $entryTypes['label'] = $entryType->name;
                $variables['entryTypes'][] = $entryTypes;
            }
        }

        if (class_exists('craft\commerce\Plugin') && Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
            $variables['productTypes'][] = ['value' => '', 'label' => Craft::t('asset-indexes-extra', 'Select one')];
            foreach (PluginCommerce::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
                if ($currentUser->can('commerce-createProducts:' . $productType->uid)) {
                    $productTypes['value'] = $productType->id;
                    $productTypes['label'] = $productType->name;
                    $variables['productTypes'][] = $productTypes;
                }
            }
        }

        if (class_exists('craft\digitalproducts\Plugin') && Craft::$app->plugins->isPluginInstalled('digital-products') && Craft::$app->plugins->isPluginEnabled('digital-products')) {
            $variables['digitalProductTypes'][] = ['value' => '', 'label' => Craft::t('asset-indexes-extra', 'Select one')];
            foreach (PluginDigitalProducts::getInstance()->getProductTypes()->getAllProductTypes() as $productType) {
                if ($currentUser->can('digitalProducts-manageProductType:' . $productType->uid)) {
                    $productTypes['value'] = $productType->id;
                    $productTypes['label'] = $productType->name;
                    $variables['digitalProductTypes'][] = $productTypes;
                }
            }
        }

        $variables['enable'] = $settings->enable;

        $itemType = null;
        $sectionSites = [];
        if (isset($settings->itemType)) {
            $itemType = $settings->itemType;
        }

        if ($itemType == 'Entry' && isset($settings->sectionId) && $settings->sectionId) {
            $sectionService = Craft::$app->getEntries();
            $section = $sectionService->getSectionById((int) $settings->sectionId);
            if ($section) {
                $sectionSites = $section->getSiteSettings();
                $sectionSites = array_keys($sectionSites);
            }
        }
        $allSites = Craft::$app->sites->getAllSiteIds();
        $items = [];
        $encodedSites = [];
        foreach ($allSites as $siteId) {
            // Allow only sites that user has access to
            $siteUid = Db::uidById(Table::SITES, $siteId);
            if (Craft::$app->getIsMultiSite() && !$currentUser->can('editSite:' . $siteUid)) {
                continue;
            }
            $site = Craft::$app->sites->getSiteById($siteId);
            if ($site) {
                $item = [];
                $item['label'] = $site->name;
                $item['value'] = $site->id;
                $encodedSites[] = $item;

                if (!$sectionSites || in_array($siteId, $sectionSites)) {
                    $items[] = $item;
                }
            }
        }
        if (Craft::$app->getIsMultiSite() && !$items) {
            throw new ServerErrorHttpException('User have no access to any any sites');
        }
        $variables['sites'] = $items;
        $variables['encodedSites'] = json_encode($encodedSites);

        // Mapping
        $mappingValues = null;
        $mappings = $settings->mapping ?? [];
        foreach ($mappings as $mappingKey => $mapping) {
            $mappingModel = new Mapping();
            foreach ($mapping as $key => $value) {
                $mappingModel->$key = $value;
            }
            $mappingValues[$mappingKey] = $mappingModel;
        }
        $variables['mappingValues'] = $mappingValues;
        $variables['mapping'] = $settings->mappingAttributes();

        $variables['fields'] = [
            ['value' => '', 'label' => Craft::t('asset-indexes-extra', 'Select field')],
        ];

        if ($settings->itemType) {
            switch ($settings->itemType) {
                case 'Entry':
                    $itemId = $settings->entryTypeId;
                    break;
                case 'Digital Product':
                    $itemId = $settings->digitalProductTypeId;
                    break;
                case 'Product':
                    $itemId = $settings->productTypeId;
                    break;
                default:
                    break;
            }
            if (isset($itemId) && $itemId) {
                $variables['defaultContainers'] = GeneralHelper::containers($settings->itemType, $itemId, false);
            }
        }
        if (!isset($variables['defaultContainers'])) {
            $variables['defaultContainers'] = [['value' => '', 'label' => craft::t('asset-indexes-extra', 'Select one container (Matrix)')]];
        }

        return $this->renderTemplate(
            'asset-indexes-extra/asset-indexes/_edit',
            $variables
        );
    }

    /**
     * Save Import asset indexes options
     *
     * @return Response|null|false
     */
    public function actionSave(): Response|null|false
    {
        $this->requirePermission('assetIndexesExtra-manageOptions');
        $this->requirePostRequest();
        $siteIds = Craft::$app->getRequest()->getRequiredBodyParam('siteIds');
        $recordId = Craft::$app->getRequest()->getRequiredBodyParam('recordId');

        $user = Craft::$app->getUser()->getIdentity();

        $settings = new AssetIndexesOptionSettings();
        if ($recordId) {
            $options = AssetIndexesExtra::$plugin->assetIndexes->getAssetIndexesOptionById($recordId);
            if (!$options) {
                throw new ServerErrorHttpException('Option can not be found');
            }
            $optionSettings = json_decode($options->settings);
            $settings->id = $recordId;
        }

        $settings->volumes = Craft::$app->getRequest()->getBodyParam('volumes');
        $settings->enable = Craft::$app->getRequest()->getBodyParam('enable');
        $settings->itemType = Craft::$app->getRequest()->getBodyParam('itemType', ($optionSettings->itemType ?? null));
        $settings->log = Craft::$app->getRequest()->getBodyParam('log');
        if ($user->can('editUsers')) {
            $settings->userIds = Craft::$app->getRequest()->getBodyParam('userIds');
        }

        if ($settings->itemType == 'Entry') {
            $settings->sectionId = (int)Craft::$app->getRequest()->getBodyParam('sectionId');
            $settings->entryTypeId = (int)Craft::$app->getRequest()->getBodyParam('entryTypeId');
        } elseif ($settings->itemType == 'Product') {
            $settings->productTypeId = (int)Craft::$app->getRequest()->getBodyParam('productTypeId');
        } elseif ($settings->itemType == 'Digital Product') {
            $settings->digitalProductTypeId = (int)Craft::$app->getRequest()->getBodyParam('digitalProductTypeId');
            $settings->taxCategoryId = (int)Craft::$app->getRequest()->getBodyParam('taxCategoryId');
        }
        if ($siteIds && !is_array($siteIds)) {
            $siteIds = [$siteIds];
        }
        $settings->siteIds = $siteIds;

        // Mapping
        $validate = true;
        $mapping = [];
        $attributeValues = $this->request->getBodyParam('itemFields');
        $attributes = $settings->mappingAttributes();
        foreach ($attributes as $attributeKey => $attributeField) {
            if (isset($attributeValues[$attributeKey]) && $attributeValues[$attributeKey]['convertTo'] && $attributeValues[$attributeKey]['craftField']) {
                $mappingModel = new Mapping();
                $mappingModel->container = $attributeValues[$attributeKey]['containerField'];
                $mappingModel->field = $attributeValues[$attributeKey]['craftField'];
                $mappingModel->type = $attributeValues[$attributeKey]['convertTo'];
                $validate = $validate && $mappingModel->validate();
                $mapping[$attributeKey] = $mappingModel;
            }
        }
        $settings->mapping = $mapping;

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('asset-indexes-extra', 'Couldn’t save settings.'));

            /** @var UrlManager $urlManager */
            $urlManager = Craft::$app->getUrlManager();
            $urlManager->setRouteParams([
                'options' => $options ?? null,
                'settings' => $settings,
                'recordId' => $recordId,
            ]);

            return null;
        }

        // Save it
        $userId = $user->id;
        foreach ($settings as $key => $setting) {
            if (is_null($setting) || $key == 'id') {
                unset($settings->$key);
            }
        }

        if ($recordId) {
            Db::update(
                '{{%assetIndexesExtra_options}}',
                [
                    'userId' => $userId,
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                    'enable' => $settings->enable,
                ],
                ['id' => $recordId]
            );
        } else {
            Db::insert('{{%assetIndexesExtra_options}}', [
                'userId' => $userId,
                'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                'enable' => $settings->enable,
            ]);
        }

        if ($recordId) {
            Craft::$app->getSession()->setNotice(Craft::t('asset-indexes-extra', 'The asset indexes option #{option} saved.', ['option' => $recordId]));
        } else {
            Craft::$app->getSession()->setNotice(Craft::t('asset-indexes-extra', 'The asset indexes option saved.'));
        }

        return $this->redirectToPostedUrl();
    }

    /**
     * Deletes an asset index options item
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePermission('assetIndexesExtra-manageOptions');
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $assetIndexId = $this->request->getRequiredBodyParam('id');
        $record = AssetIndexesOptionsRecord::find()->where(['id' => $assetIndexId])->one();
        $record->delete();
        return $this->asSuccess();
    }

    /**
     * Reorder Asset indexes options
     *
     * @return Response
     */
    public function actionReorderOptions(): Response
    {
        $this->requirePermission('assetIndexesExtra-manageOptions');
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $ids = json_decode($this->request->getRequiredBodyParam('ids'));
        AssetIndexesExtra::$plugin->getInstance()->assetIndexes->reorderOptions($ids);

        return $this->asSuccess();
    }
}
