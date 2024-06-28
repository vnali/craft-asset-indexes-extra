<?php
/**
 * @copyright Copyright © vnali
 */

namespace vnali\assetindexesextra\records;

use craft\db\ActiveRecord;

/**
 * Asset indexes log record.
 *
 * @property int $id
 * @property int $volumeId
 * @property string $itemType
 * @property string $filename
 * @property bool $cli
 * @property int $status
 * @property string $result
 * @property int $optionId
 * @property int $assetId
 * @property int $itemId
 * @property string $settings
 * @property int $userId
 * @property string $username
 * @property string $volumeName
 * @property string $volumeHandle
 * @property mixed $dateCreated
 */
class AssetIndexesLogRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%assetIndexesExtra_logs}}';
    }
}
