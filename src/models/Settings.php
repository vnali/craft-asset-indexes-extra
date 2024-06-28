<?php

namespace vnali\assetindexesextra\models;

use craft\base\Model;

/**
 * Asset Indexes Extra settings
 */
class Settings extends Model
{
    public ?string $checkAccessToVolumes = null;

    public ?bool $log = false;

    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['checkAccessToVolumes'], 'in', 'range' => ['view', 'save']];
        $rules[] = [['log'], 'in', 'range' => [0, 1]];
        return $rules;
    }
}
