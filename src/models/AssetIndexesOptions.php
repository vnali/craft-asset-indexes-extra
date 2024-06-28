<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\models;

use craft\base\Model;

class AssetIndexesOptions extends Model
{
    public ?int $id = null;
    /**
     * @var bool|null this option is checked when asset indexes happen.
     */
    public ?bool $enable = null;

    public array|string $settings;

    /**
     * The last user who saved the option. it can be null because the user might be deleted.
     *
     * @var int|null
     */
    public ?int $userId = null;

    public mixed $dateUpdated = null;
}
