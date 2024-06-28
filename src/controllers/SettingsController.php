<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\controllers;

use Craft;
use craft\web\Controller;

use vnali\assetindexesextra\AssetIndexesExtra;
use vnali\assetindexesextra\models\Settings;

use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Set settings for the plugin
 */
class SettingsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            throw new ForbiddenHttpException(Craft::t('asset-indexes-extra', 'Administrative changes are disallowed in this environment.'));
        }

        // Require permission
        $this->requirePermission('assetIndexesExtra-manageSettings');

        return parent::beforeAction($action);
    }

    /**
     * Return general settings template
     *
     * @param Settings|null $settings
     * @return Response
     */
    public function actionGeneral(?Settings $settings = null): Response
    {
        if ($settings === null) {
            $settings = AssetIndexesExtra::$plugin->getSettings();
        }
        $variables['options'] = [];
        $variables['checkAccessToVolumesOptions'] = [];
        $checkAccessToVolumesOptions = [];
        $checkAccessToVolumesOption['value'] = '';
        $checkAccessToVolumesOption['label'] = craft::t('asset-indexes-extra', 'Select one');
        $checkAccessToVolumesOptions[] = $checkAccessToVolumesOption;
        $checkAccessToVolumesOption = [];
        $checkAccessToVolumesOption['value'] = 'view';
        $checkAccessToVolumesOption['label'] = craft::t('asset-indexes-extra', 'View assets');
        $checkAccessToVolumesOptions[] = $checkAccessToVolumesOption;
        $checkAccessToVolumesOption = [];
        $checkAccessToVolumesOption['value'] = 'save';
        $checkAccessToVolumesOption['label'] = craft::t('asset-indexes-extra', 'Save assets');
        $checkAccessToVolumesOptions[] = $checkAccessToVolumesOption;
        $variables['checkAccessToVolumesOptions'] = $checkAccessToVolumesOptions;
        $variables['settings'] = $settings;
        return $this->renderTemplate(
            'asset-indexes-extra/settings/_general',
            $variables
        );
    }

    /**
     * Save general settings
     *
     * @param Settings|null $settings
     * @return Response|null
     */
    public function actionGeneralSave(Settings $settings = null): ?Response
    {
        $this->requirePostRequest();

        /** @var Settings $settings */
        $settings = AssetIndexesExtra::$plugin->getSettings();
        $settings->checkAccessToVolumes = $this->request->getBodyParam('checkAccessToVolumes', $settings->checkAccessToVolumes);
        $settings->log = $this->request->getBodyParam('log', $settings->log);
        // Save it
        if (!Craft::$app->getPlugins()->savePluginSettings(AssetIndexesExtra::$plugin, $settings->getAttributes())) {
            return $this->asModelFailure($settings, Craft::t('asset-indexes-extra', 'Couldn’t save general settings.'), 'settings');
        }

        return $this->asSuccess(Craft::t('asset-indexes-extra', 'General settings saved.'));
    }
}
