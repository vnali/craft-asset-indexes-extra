<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\controllers;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Cp;
use craft\web\Controller;
use vnali\assetindexesextra\AssetIndexesExtra;
use vnali\assetindexesextra\records\AssetIndexesLogRecord;
use yii\web\Response;

class LogsController extends Controller
{
    /**
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        return parent::beforeAction($action);
    }

    /**
     * Index page for logs
     *
     * @param int|null $optionId
     * @return Response
     */
    public function actionIndex(?int $optionId = null): Response
    {
        $this->requirePermission('assetIndexesExtra-viewLogs');
        $variables = [];
        if ($optionId) {
            $variables['optionId'] = $optionId;
        }
        return $this->renderTemplate('asset-indexes-extra/logs/_index.twig', $variables);
    }

    /**
     * Deletes an asset index item
     *
     * @return Response
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requirePermission('assetIndexesExtra-deleteLogs');
        $optionId = (int)$this->request->getParam('optionId');
        $currentUser = Craft::$app->getUser()->getIdentity();
        if ($optionId) {
            $where = [];
            $where['optionId'] = $optionId;
            if (!$currentUser->can('assetIndexesExtra-viewOtherUsersLogs')) {
                $where['userId'] = $currentUser->id;
            }
            AssetIndexesLogRecord::deleteAll($where);
            Craft::$app->getSession()->setNotice(Craft::t('asset-indexes-extra', 'Logs for {option} deleted successfully.', ['option' => '#' . $optionId]));
        } else {
            $where = [];
            if (!$currentUser->can('assetIndexesExtra-viewOtherUsersLogs')) {
                $where['userId'] = $currentUser->id;
            }
            AssetIndexesLogRecord::deleteAll($where);
            Craft::$app->getSession()->setNotice(Craft::t('asset-indexes-extra', 'Logs deleted successfully.'));
        }
        return $this->asSuccess();
    }

    /**
     * Logs endpoint
     *
     * @return Response
     */
    public function actionEndpoint(): Response
    {
        $this->requirePermission('assetIndexesExtra-viewLogs');
        $this->requireAcceptsJson();

        $page = (int)$this->request->getParam('page', 1);
        $limit = (int)$this->request->getParam('per_page', 100);
        $searchTerm = $this->request->getParam('search');
        $sort = $this->request->getParam('sort');
        $optionId = $this->request->getParam('optionId');

        [$pagination, $tableData] = AssetIndexesExtra::$plugin->logs->getTableData($page, $limit, $searchTerm, $sort, $optionId);

        return $this->asSuccess(data: [
            'pagination' => $pagination,
            'data' => $tableData,
        ]);
    }

    /**
     * Return element chip for assets and other items in preferred sites
     *
     * @return string
     */
    public function actionElementChip(): string
    {
        $this->requirePermission('assetIndexesExtra-viewLogs');
        $assetId = (int)$this->request->getParam('assetId');
        $itemId = (int)$this->request->getParam('itemId');
        $itemType = (int)$this->request->getParam('itemType');
        $chip = '';
        $requestedSite = Cp::requestedSite();
        $sites = [];
        if ($requestedSite) {
            $sites[] = $requestedSite->handle;
            $requestedSiteId = $requestedSite->id;
        }
        $primarySite = Craft::$app->sites->getPrimarySite();
        $sites[] = $primarySite->handle;
        $primarySiteId = $primarySite->id;
        if ($assetId) {
            $asset = Asset::find()->status(null)->id($assetId)->site('*')->unique()->preferSites($sites)->one();
            if ($asset && Craft::$app->getElements()->canView($asset)) {
                $chip = '&nbsp;' . Cp::elementChipHtml($asset, [
                    'size' => Cp::CHIP_SIZE_SMALL,
                ]) . '&nbsp;';
            }
        }

        if ($itemId) {
            if ($itemType == 'Entry') {
                $element = Entry::find()->status(null)->id($itemId)->site('*')->unique()->preferSites($sites)->one();
            } else {
                $element = Craft::$app->elements->getElementById($itemId, null, ($requestedSiteId ?? $primarySiteId));
            }
            if ($element && Craft::$app->getElements()->canView($element)) {
                $chip = $chip . Cp::elementChipHtml($element, [
                    'size' => Cp::CHIP_SIZE_SMALL,
                ]);
            }
        }
        return $chip;
    }
}
