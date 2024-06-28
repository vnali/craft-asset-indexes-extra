<?php
/**
 * @copyright Copyright © vnali
 */

namespace vnali\assetindexesextra\records;

use craft\db\ActiveRecord;

/**
 * Asset indexes options record.
 *
 * @property int $id
 * @property bool $enable
 * @property string $settings
 * @property int $sortOrder
 * @property int $userId
 */
class AssetIndexesOptionsRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%assetIndexesExtra_options}}';
    }
}
