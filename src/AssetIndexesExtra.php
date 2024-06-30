<?php

namespace vnali\assetindexesextra;

use Craft;
use craft\base\Plugin;
use craft\elements\Asset;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\UrlHelper;
use craft\services\UserPermissions;
use craft\utilities\AssetIndexes;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use vnali\assetindexesextra\models\Settings;
use vnali\assetindexesextra\records\AssetIndexesLogRecord;
use vnali\assetindexesextra\records\AssetIndexesOptionsRecord;
use vnali\assetindexesextra\services\assetIndexesService;
use vnali\assetindexesextra\services\ImporterService;
use vnali\assetindexesextra\services\logService;
use vnali\assetindexesextra\twig\CraftVariableBehavior;
use yii\base\Event;

/**
 * Asset Indexes Extra plugin
 * @property-read assetIndexesService $assetIndexes
 * @property-read importerService $importer
 * @property-read logService $logs
 * @author vnali <vnali.dev@gmail.com>
 * @copyright vnali
 * @license https://craftcms.github.io/license/ Craft License
 */
class AssetIndexesExtra extends Plugin
{
    /**
     * @var AssetIndexesExtra
     */
    public static AssetIndexesExtra $plugin;

    public string $schemaVersion = '2.0.0-alpha.2';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'assetIndexes' => assetIndexesService::class,
                'importer' => ImporterService::class,
                'logs' => logService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        $this->_registerPermissions();
        $this->_registerRules();
        $this->_registerVariables();

        $settings = AssetIndexesExtra::$plugin->getSettings();
        /** @var Settings $settings */
        if ($settings->checkAccessToVolumes) {
            Event::on(AssetIndexes::class, AssetIndexes::EVENT_LIST_VOLUMES, function($event) use ($settings) {
                $volumes = [];
                $currentUser = Craft::$app->getUser()->getIdentity();
                foreach (Craft::$app->volumes->getAllVolumes() as $volumeItem) {
                    // Allow only volumes that user has access
                    if ($settings->checkAccessToVolumes == 'view') {
                        if ($currentUser->can('viewAssets:' . $volumeItem->uid)) {
                            $volumes[] = $volumeItem;
                        }
                    } elseif ($settings->checkAccessToVolumes == 'save') {
                        if ($currentUser->can('saveAssets:' . $volumeItem->uid)) {
                            $volumes[] = $volumeItem;
                        }
                    }
                }
                $event->volumes = $volumes;
            });
        }

        Event::on(
            \craft\services\Elements::class,
            \craft\services\Elements::EVENT_AFTER_SAVE_ELEMENT,
            function(\craft\events\ElementEvent $event) {
                if (Craft::$app->getRequest()->getIsConsoleRequest()) {
                    $request = Craft::$app->getRequest();
                    $params = $request->getParams();
                }
                if ((!Craft::$app->getRequest()->getIsConsoleRequest() && Craft::$app->request->pathInfo == 'actions/asset-indexes/process-indexing-session') ||
                    Craft::$app->getRequest()->getIsConsoleRequest() && (in_array('index-assets/all', $params) || in_array('index-assets/one', $params))
                ) {
                    // It's coming from asset index utility, try to create items from assets
                    /** @var Asset $element */
                    $element = $event->element;
                    if (
                        is_a($element, Asset::class) && $element->firstSave && !$element->propagating
                    ) {
                        $currentUser = Craft::$app->getUser()->getIdentity();
                        if (!Craft::$app->getRequest()->getIsConsoleRequest()) {
                            $userId = $currentUser->id;
                            $username = $currentUser->username;
                            $cli = false;
                        } else {
                            $userId = null;
                            $cli = true;
                            $username = null;
                        }
                        $pluginSettings = AssetIndexesExtra::$plugin->getSettings();
                        /** @var Settings $pluginSettings */
                        $log = $pluginSettings->log;

                        $info = [];
                        $logRecord = new AssetIndexesLogRecord();

                        if ($log) {
                            $logRecord->result = craft::t('asset-indexes-extra', 'The asset {title} is created in {volume} by {username}.', [
                                'title' => $element->title,
                                'volume' => $element->volume->name,
                                'username' => $username,
                            ]);
                            $logRecord->status = 1;

                            $vol = $element->getVolume();
                            $fs = $vol->getFs();
                            $encodedFs = json_encode($fs, JSON_UNESCAPED_UNICODE);
                            $decodedFs = json_decode($encodedFs, true);
                            $allowedKeys = ['name', 'handle', 'hasUrls', 'url', 'path'];
                            $fsData = [];
                            foreach ($decodedFs as $key => $value) {
                                if (in_array($key, $allowedKeys) && !is_null($value)) {
                                    $fsData[] = $key . ':' . $value;
                                }
                            }
                            $info['assetIndex']['Extra Data']['Log Asset Index'] = $log;
                            $info['assetIndex']['Extra Data']['Asset Fs'] = implode(', ', $fsData);
                            $info['assetIndex']['Extra Data']['volume Subpath'] = $vol->getSubpath();
                            $info['assetIndex']['Extra Data']['Asset Folder'] = $element->getFolder()->path;
                            $info['assetIndex']['Extra Data']['Asset Filename'] = $element->filename;
                        }
                        $optionLog = false;
                        $AssetIndexesOptions = AssetIndexesOptionsRecord::find()->orderBy('sortOrder asc, id desc')->where(['enable' => 1])->all();
                        // Check which option allows for importing elements.
                        /** @var AssetIndexesOptionsRecord $assetIndexesOption */
                        foreach ($AssetIndexesOptions as $assetIndexesOption) {
                            $setting = json_decode($assetIndexesOption->settings, true);
                            if (isset($setting['volumes']) && is_array($setting['volumes']) && in_array($element->volumeId, $setting['volumes'])) {
                                if (Craft::$app->getRequest()->getIsConsoleRequest() || (!isset($setting['userIds']) || !$setting['userIds'] || in_array($currentUser->id, $setting['userIds']))) {
                                    $optionLog = $setting['log'];
                                    AssetIndexesExtra::$plugin->importer->ImportByAssetIndex($element, $assetIndexesOption, $logRecord, $info);
                                }
                                break;
                            }
                        }
                        if ($log || $optionLog) {
                            $logRecord->assetId = $element->id;
                            $logRecord->volumeId = $element->volumeId;
                            $logRecord->filename = $element->filename;
                            $logRecord->userId = $userId;
                            $logRecord->volumeName = $element->volume->name;
                            $logRecord->volumeHandle = $element->volume->handle;
                            $logRecord->username = $username;
                            $logRecord->settings = json_encode($info, JSON_UNESCAPED_UNICODE);
                            $logRecord->cli = $cli;
                            if (!$logRecord->save()) {
                                craft::error('log record cannot be saved' . json_encode($logRecord->getErrors()));
                            }
                        }
                    }
                }
            }
        );
    }

    /**
     * @inheritDoc
     */
    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    public function getSettingsResponse(): mixed
    {
        $url = UrlHelper::cpUrl('asset-indexes-extra/settings');
        return Craft::$app->getResponse()->redirect($url);
    }

    /**
     * @inheritDoc
     */
    public function getCpNavItem(): ?array
    {
        $allowAdminChanges = Craft::$app->getConfig()->getGeneral()->allowAdminChanges;

        $nav = parent::getCpNavItem();

        $nav['label'] = Craft::t('asset-indexes-extra', 'Asset Indexes Extra');

        $user = Craft::$app->getUser();

        // Options
        if ($allowAdminChanges && $user->checkPermission('assetIndexesExtra-viewAssignedOptions')) {
            $nav['subnav']['options'] = [
                'label' => Craft::t('app', 'Options'),
                'url' => 'asset-indexes-extra/asset-indexes/index',
            ];
        }

        // Log
        if ($allowAdminChanges && $user->checkPermission('assetIndexesExtra-viewLogs')) {
            $nav['subnav']['logs'] = [
                'label' => Craft::t('app', 'Logs'),
                'url' => 'asset-indexes-extra/logs/index',
            ];
        }

        // Settings
        if ($allowAdminChanges && $user->checkPermission('assetIndexesExtra-manageSettings')) {
            $nav['subnav']['settings'] = [
                'label' => Craft::t('app', 'Settings'),
                'url' => 'asset-indexes-extra/settings',
            ];
        }

        return $nav;
    }

    /**
     * Register CP Url and site rules.
     *
     * @return void
     */
    private function _registerRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['asset-indexes-extra/default/containers'] = 'asset-indexes-extra/default/containers';
                $event->rules['asset-indexes-extra/default/fields-filter'] = 'asset-indexes-extra/default/fields-filter';
                $event->rules['asset-indexes-extra/default/get-section-options'] = 'asset-indexes-extra/default/get-section-options';
                $event->rules['asset-indexes-extra/asset-indexes/index'] = 'asset-indexes-extra/asset-indexes/index';
                $event->rules['asset-indexes-extra/asset-indexes/delete'] = 'asset-indexes-extra/asset-indexes/delete';
                $event->rules['asset-indexes-extra/asset-indexes/new'] = 'asset-indexes-extra/asset-indexes/edit';
                $event->rules['asset-indexes-extra/asset-indexes/reorder-options'] = 'asset-indexes-extra/asset-indexes/reorder-options';
                $event->rules['asset-indexes-extra/asset-indexes/<recordId:\d+>'] = 'asset-indexes-extra/asset-indexes/edit';
                $event->rules['asset-indexes-extra/logs/index'] = 'asset-indexes-extra/logs/index';
                $event->rules['asset-indexes-extra/logs/delete'] = 'asset-indexes-extra/logs/delete';
                $event->rules['asset-indexes-extra/logs/element-chip'] = 'asset-indexes-extra/logs/element-chip';
                $event->rules['asset-indexes-extra/logs/endpoint'] = 'asset-indexes-extra/logs/endpoint';
                $event->rules['asset-indexes-extra/settings/general'] = 'asset-indexes-extra/settings/general';
            }
        );
    }

    private function _registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function(RegisterUserPermissionsEvent $event) {
                $nestedManagedOptions['assetIndexesExtra-manageOptions'] = [
                    'label' => Craft::t('asset-indexes-extra', 'Manage asset indexes options'),
                ];
                $nestedViewAllOptions['assetIndexesExtra-viewAllOptions'] = [
                    'label' => Craft::t('asset-indexes-extra', 'View all asset indexes options'),
                    'nested' => $nestedManagedOptions,
                ];
                $permissions['assetIndexesExtra-viewAssignedOptions'] = [
                    'label' => Craft::t('asset-indexes-extra', 'View asset index options that the user has access to'),
                    'nested' => $nestedViewAllOptions,
                ];
                $nestedViewLogs['assetIndexesExtra-viewOtherUsersLogs'] = [
                    'label' => Craft::t('asset-indexes-extra', 'View other users logs'),
                ];
                $nestedViewLogs['assetIndexesExtra-deleteLogs'] = [
                    'label' => Craft::t('asset-indexes-extra', 'Delete logs'),
                ];
                $nestedViewLogs['assetIndexesExtra-viewLogsMoreInfo'] = [
                    'label' => Craft::t('asset-indexes-extra', 'View logs more Info'),
                ];
                $permissions['assetIndexesExtra-viewLogs'] = [
                    'label' => Craft::t('asset-indexes-extra', 'View logs'),
                    'nested' => $nestedViewLogs,
                ];
                /*
                $permissions['assetIndexesExtra-canSkipAccessCheck'] = [
                    'label' => Craft::t('asset-indexes-extra', 'User can skip access check'),
                    'info' => 'The user with this permission can create items without having permissions related to creation of those items.',
                    'warning' => 'The user with this permission can create items without having permissions related to creation of those items.',
                ];
                */
                $permissions['assetIndexesExtra-manageSettings'] = [
                    'label' => Craft::t('asset-indexes-extra', 'Manage plugin settings'),
                ];
                $event->permissions[] = [
                    'heading' => Craft::t('asset-indexes-extra', 'Asset Indexes Extra'),
                    'permissions' => $permissions,
                ];
            }
        );
    }

    /**
     * Register plugin services
     *
     * @return void
     */
    private function _registerVariables(): void
    {
        Event::on(CraftVariable::class, CraftVariable::EVENT_INIT, function(Event $event) {
            /** @var CraftVariable $variable */
            $variable = $event->sender;
            $variable->attachBehavior('assetIndexesExtra', CraftVariableBehavior::class);
        });
    }
}
