<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\controllers;

use Craft;
use craft\web\Controller;
use vnali\assetindexesextra\helpers\GeneralHelper;
use yii\web\Response;

class DefaultController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        // Require permission
        $this->requirePermission('assetIndexesExtra-manageOptions');

        return parent::beforeAction($action);
    }

    /**
     * Get entry types.
     *
     * @param int $sectionId
     * @return Response
     */
    public function actionGetSectionOptions(int $sectionId): Response
    {
        $variables['sites'] = [];
        $variables['entryType'][] = ['value' => '', 'label' => craft::t('asset-indexes-extra', 'Select one')];
        if ($sectionId) {
            foreach (Craft::$app->entries->getEntryTypesBySectionId($sectionId) as $entryType) {
                $entryTypes['value'] = $entryType->id;
                $entryTypes['label'] = $entryType->name;
                $variables['entryType'][] = $entryTypes;
            }
            $section = Craft::$app->entries->getSectionById($sectionId);
            $siteSettings = $section->getSiteSettings();
            $siteService = Craft::$app->sites;
            $variables['sites'] = [];
            foreach ($siteSettings as $key => $siteSettings) {
                $site = $siteService->getSiteById($key);
                // if it is a deleted site
                if (!$site) {
                    continue;
                }
                $sites['value'] = $site->id;
                $sites['label'] = $site->name;
                $variables['sites'][] = $sites;
            }
        }
        return $this->asJson(['sites' => $variables['sites'], 'entryType' => $variables['entryType']]);
    }

    /**
     * Filter suggested fields based on type, container and item's field layout.
     *
     * @param string $convertTo
     * @param string $fieldContainer
     * @param string|null $limitFieldsToLayout
     * @param string|null $item
     * @param int|null $itemId
     * @return Response
     */
    public function actionFieldsFilter(string $convertTo, string $fieldContainer, ?string $limitFieldsToLayout = null, ?string $item = null, ?int $itemId = null): Response
    {
        $fieldsArray = GeneralHelper::findField(null, $convertTo, $fieldContainer, $limitFieldsToLayout, $item, $itemId);
        return $this->asJson($fieldsArray);
    }

    /**
     * Get containers (matrixes)
     *
     * @param string|null $item
     * @param int|null $itemId
     * @param bool $onlyContainer
     * @return Response
     */
    public function actionContainers(string $item = null, ?int $itemId = null, bool $onlyContainer = true): Response
    {
        $array = GeneralHelper::containers($item, $itemId, $onlyContainer);
        return $this->asJson($array);
    }
}
