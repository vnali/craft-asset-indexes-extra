<?php
/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\models;

use craft\base\Model;
use craft\fields\Assets;

class Mapping extends Model
{
    /**
     * @var string|null Type
     */
    public ?string $type = null;

    /**
     * @var string|null Matrix/entry type Container
     */
    public ?string $container = null;

    /**
     * @var string|null Field
     */
    public ?string $field = null;

    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [['container', 'field'], 'safe'];
        $rules[] = ['type', 'in', 'range' => [
            Assets::class,
        ]];
        return $rules;
    }
}
