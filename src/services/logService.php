<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\services;

use Craft;
use craft\db\Query;
use craft\helpers\AdminTable;
use craft\helpers\Cp;
use DateTime;
use DateTimeZone;
use vnali\assetindexesextra\records\AssetIndexesLogRecord;
use yii\base\Component;

/**
 * Asset Indexes Service class
 */
class logService extends Component
{
    /**
     * Get one log record
     *
     * @param int|null $optionId
     * @return AssetIndexesLogRecord|null
     */
    public function getOneLog(?int $optionId = null): ?AssetIndexesLogRecord
    {
        $log = AssetIndexesLogRecord::find();
        if ($optionId) {
            $log->where(['optionId' => $optionId]);
        }
        $logRecord = $log->one();
        /** @var AssetIndexesLogRecord $logRecord */
        return $logRecord;
    }

    /**
     * Returns data for the Logs index page
     *
     * @param int $page
     * @param int $limit
     * @param string|null $searchTerm
     * @param array|null $sort
     * @param int|null $optionId
     * @return array
     */
    public function getTableData(int $page, int $limit, ?string $searchTerm, ?array $sort, ?int $optionId = null): array
    {
        $searchTerm = $searchTerm ? trim($searchTerm) : $searchTerm;

        $offset = ($page - 1) * $limit;
        $query = (new Query())
            ->select(['logs.*'])
            ->from(['logs' => '{{%assetIndexesExtra_logs}}']);

        if ($optionId) {
            $query->andWhere(['optionId' => $optionId]);
        }

        $currentUser = Craft::$app->getUser()->getIdentity();
        if (!$currentUser->can('assetIndexesExtra-viewOtherUsersLogs')) {
            $query->andWhere(['userId' => $currentUser->id]);
        }

        if ($searchTerm !== null && $searchTerm !== '') {
            $searchParams = $this->_getSearchParams($searchTerm);
            if (!empty($searchParams)) {
                $query->andWhere(['or', ...$searchParams]);
            }
        }

        if ($sort) {
            foreach ($sort as $sortItem) {
                if ($sortItem['sortField'] == 'statusCode') {
                    if ($sortItem['direction'] == 'asc') {
                        $query->orderBy('status asc, id desc');
                    } else {
                        $query->orderBy('status desc, id desc');
                    }
                } elseif ($sortItem['sortField'] == 'id') {
                    if ($sortItem['direction'] == 'asc') {
                        $query->orderBy('id asc');
                    } else {
                        $query->orderBy('id desc');
                    }
                } elseif ($sortItem['sortField'] == 'cli') {
                    if ($sortItem['direction'] == 'asc') {
                        $query->orderBy('cli asc');
                    } else {
                        $query->orderBy('cli desc');
                    }
                } elseif ($sortItem['sortField'] == 'optionId') {
                    if ($sortItem['direction'] == 'asc') {
                        $query->orderBy('optionId asc');
                    } else {
                        $query->orderBy('optionId desc');
                    }
                } elseif ($sortItem['sortField'] == 'volumeId') {
                    if ($sortItem['direction'] == 'asc') {
                        $query->orderBy('volumeName asc');
                    } else {
                        $query->orderBy('volumeName desc');
                    }
                } elseif ($sortItem['sortField'] == 'filename') {
                    if ($sortItem['direction'] == 'asc') {
                        $query->orderBy('filename asc');
                    } else {
                        $query->orderBy('filename desc');
                    }
                } elseif ($sortItem['sortField'] == 'username') {
                    if ($sortItem['direction'] == 'asc') {
                        $query->orderBy('username asc');
                    } else {
                        $query->orderBy('username desc');
                    }
                } elseif ($sortItem['sortField'] == 'itemType') {
                    if ($sortItem['direction'] == 'asc') {
                        $query->orderBy('itemType asc');
                    } else {
                        $query->orderBy('itemType desc');
                    }
                }
            }
        } else {
            $query->orderBy('id desc');
        }
        $total = $query->count();

        $query->limit($limit);
        $query->offset($offset);

        $result = $query->all();
        $tableData = [];

        $tz = Craft::$app->getTimeZone();
        $tzTime = new DateTimeZone($tz);


        foreach ($result as $item) {
            $dateCreated = new DateTime($item['dateCreated'], new \DateTimeZone("UTC"));
            $dateCreated->setTimezone($tzTime);

            $escapedLogSettings = null;
            $logSettings = json_decode($item['settings'], true);
            if ($currentUser->can('assetIndexesExtra-viewLogsMoreInfo')) {
                $logSettingsPretty = json_encode($logSettings, JSON_PRETTY_PRINT);
                $escapedLogSettings = htmlspecialchars($logSettingsPretty, ENT_QUOTES, 'UTF-8');
            }

            $itemId = null;
            if (isset($logSettings['assetIndexOption']['settings']['log'])) {
                $itemId = $item['itemId'];
            }

            $assetId = null;
            if (isset($logSettings['assetIndex']['Extra Data']['Log Asset Index'])) {
                $assetId = $item['assetId'];
            }

            switch ($item['status']) {
                case 1:
                    $iconColor = 'green';
                    break;
                case 2:
                    $iconColor = 'orange';
                    break;
                case 0:
                    $iconColor = 'red';
                    break;
                case 3:
                    $iconColor = 'purple';
                    break;
                default:
                    $iconColor = 'white';
                    break;
            }

            $detail = [];
            $detail['content'] = $item['result'] . "<div class='record' data-log-options='" . $escapedLogSettings . "' data-item-type=" . $item['itemType'] . ' data-item-id="' . $itemId . '" data-asset-id="' . $assetId . '"><span class="loading">Loading ...</span></div>';
            $tableData[] = [
                'id' => $item['id'],
                'title' => '#' . $item['id'],
                'icon' => Cp::iconSvg('info'),
                'iconColor' => $iconColor,
                'volume' => $item['volumeName'] . ($item['volumeId'] ? ' (#' . $item['volumeId'] . ')' : ''),
                'filename' => $item['filename'],
                'itemType' => $item['itemType'] . ($item['optionId'] ? ' (#' . $item['optionId'] . ')' : ''),
                'dateCreated' => $dateCreated->format('Y-m-d H:i:s'),
                'cli' => $item['cli'] ? 'yes' : '',
                'user' => $item['username'] . ($item['userId'] ? ' (#' . $item['userId'] . ')' : ''),
                'detail' => $detail,
                'statusCode' => $item['status'],
            ];
        }

        $pagination = AdminTable::paginationLinks($page, $total, $limit);

        return [$pagination, $tableData];
    }

    /**
     * Returns the array of sql "like" params to be used in the 'where' param for the query.
     *
     * @param string $term
     * @return array
     */
    private function _getSearchParams(string $term): array
    {
        $searchParams = ['filename', 'itemType', 'username', 'volumeName'];
        $searchQueries = [];

        if ($term !== '') {
            foreach ($searchParams as $param) {
                $searchQueries[] = ['like', $param, '%' . $term . '%', false];
            }
        }

        return $searchQueries;
    }
}
