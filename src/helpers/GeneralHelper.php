<?php

/**
 * @copyright Copyright © vnali
 */

namespace vnali\assetindexesextra\helpers;

use Craft;
use craft\commerce\Plugin as PluginCommerce;
use craft\digitalproducts\Plugin as PluginDigitalProducts;
use craft\fields\Matrix;
use yii\web\ServerErrorHttpException;

class GeneralHelper
{
    /**
     * Get containers
     *
     * @param string $item
     * @param int $itemId
     * @param bool $onlyContainer
     * @return array
     */
    public static function containers(string $item = null, int $itemId, bool $onlyContainer = true): array
    {
        $fields = [];
        $containers = [['value' => '', 'label' => craft::t('asset-indexes-extra', 'Select one container (Matrix)')]];
        switch ($item) {
            case 'Entry':
                $entryType = Craft::$app->entries->getEntryTypeById($itemId);
                if ($entryType) {
                    $fields = $entryType->getCustomFields();
                }
                break;
            case 'Product':
                if (class_exists('craft\commerce\Plugin') && Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
                    $productType = PluginCommerce::getInstance()->getProductTypes()->getProductTypeById($itemId);
                    if ($productType) {
                        $fields = $productType->getCustomFields();
                    }
                }
                break;
            case 'Digital Product':
                if (class_exists('craft\digitalproducts\Plugin') && Craft::$app->plugins->isPluginInstalled('digital-products') && Craft::$app->plugins->isPluginEnabled('digital-products')) {
                    $digitalProductType = PluginDigitalProducts::getInstance()->getProductTypes()->getProductTypeById($itemId);
                    if ($digitalProductType) {
                        $fields = $digitalProductType->getCustomFields();
                    }
                }
                break;
            default:
                throw new ServerErrorHttpException('getting container for this item is not supported yet');
        }

        foreach ($fields as $field) {
            if (get_class($field) == 'craft\fields\Matrix') {
                if ($onlyContainer) {
                    $containers[] = ['value' => $field->handle, 'label' => $field->name];
                } else {
                    $types = GeneralHelper::getContainerInside($field);
                    foreach ($types as $type) {
                        $containers[] = $type;
                    }
                }
            }
        }
        return $containers;
    }

    /**
     * Get matrix, nested entries and tables.
     *
     * @return array
     */
    public static function getContainerInside($field): array
    {
        $containers = [];
        if (get_class($field) == 'craft\fields\Matrix') {
            $entryTypes = $field->getEntryTypes();
            foreach ($entryTypes as $entryType) {
                $containers[] = ['value' => $field->handle . '-Matrix|' . $entryType->handle . '-EntryType', 'label' => $field->name . '(M) | ' . $entryType->name . '(ET)'];
                $entryTypeFields = $entryType->getCustomFields();
                foreach ($entryTypeFields as $entryTypeField) {
                    if (get_class($entryTypeField) == 'craft\fields\Table') {
                        $containers[] = ['value' => $field->handle . '-Matrix|' . $entryType->handle . '-EntryType|' . $entryTypeField->handle . '-Table', 'label' => $field->name . '(M) | ' . $entryType->name . '(ET) | ' . $entryTypeField->name . ' (T)'];
                    }
                }
            }
        }
        return $containers;
    }

    /**
     * Filter fields based on field handle, field type, field container and field layout of plugin elements.
     *
     * @param string|null $fieldHandle
     * @param string|null $fieldType
     * @param string $fieldContainer
     * @param string $limitFieldsToLayout
     * @param string $item
     * @return array
     */
    public static function findField(string $fieldHandle = null, string $fieldType = null, string $fieldContainer, string $limitFieldsToLayout = null, string $item = null, $itemId): array
    {
        $fieldsArray = [
            ['value' => '', 'label' => 'Select field'],
        ];
        $fieldsArray = [];
        if ($limitFieldsToLayout == 'true') {
            switch ($item) {
                case 'Entry':
                    $entryType = Craft::$app->entries->getEntryTypeById($itemId);
                    if ($entryType) {
                        $fieldLayout = $entryType->getFieldLayout();
                    }
                    break;
                case 'Product':
                    if (class_exists('craft\commerce\Plugin') && Craft::$app->plugins->isPluginInstalled('commerce') && Craft::$app->plugins->isPluginEnabled('commerce')) {
                        $productType = PluginCommerce::getInstance()->getProductTypes()->getProductTypeById($itemId);
                        if ($productType) {
                            $fieldLayout = $productType->getFieldLayout();
                        }
                    }
                    break;
                case 'Digital Product':
                    if (class_exists('craft\digitalproducts\Plugin') && Craft::$app->plugins->isPluginInstalled('digital-products') && Craft::$app->plugins->isPluginEnabled('digital-products')) {
                        $digitalProductType = PluginDigitalProducts::getInstance()->getProductTypes()->getProductTypeById($itemId);
                        if ($digitalProductType) {
                            $fieldLayout = $digitalProductType->getFieldLayout();
                        }
                    }
                    break;
                default:
                    throw new ServerErrorHttpException('finding field for this item is not supported yet');
            }
        }
        if (empty($fieldContainer)) {
            if ($fieldHandle) {
                if ($limitFieldsToLayout == 'true' && isset($fieldLayout)) {
                    $fieldItem = $fieldLayout->getFieldByHandle($fieldHandle);
                } else {
                    $fieldItem = Craft::$app->getFields()->getFieldByHandle($fieldHandle);
                }
                if ($fieldItem) {
                    if ($fieldType) {
                        if (get_class($fieldItem) == $fieldType) {
                            $fieldsArray[] = ['type' => 'field', 'field' => $fieldItem, 'value' => $fieldItem->uid, 'label' => $fieldItem->handle];
                        }
                    } else {
                        $fieldsArray[] = ['type' => 'field', 'field' => $fieldItem, 'value' => $fieldItem->uid, 'label' => $fieldItem->handle];
                    }
                }
            } else {
                if ($limitFieldsToLayout == 'true' && isset($fieldLayout)) {
                    $fieldItems = $fieldLayout->getCustomFields();
                } else {
                    $fieldItems = Craft::$app->fields->getAllFields();
                }
                foreach ($fieldItems as $key => $fieldItem) {
                    if ($fieldType) {
                        if (get_class($fieldItem) == $fieldType) {
                            $fieldsArray[] = ['type' => 'field', 'field' => $fieldItem, 'value' => $fieldItem->uid, 'label' => $fieldItem->handle];
                        }
                    } else {
                        $fieldsArray[] = ['type' => 'field', 'field' => $fieldItem, 'value' => $fieldItem->uid, 'label' => $fieldItem->handle];
                    }
                }
            }
        } else {
            /** @var string|null $container0Type */
            $container0Type = null;
            /** @var string|null $container0Handle */
            $container0Handle = null;
            /** @var string|null $container1Type */
            $container1Type = null;
            /** @var string|null $container1Handle */
            $container1Handle = null;
            /** @var string|null $container2Type */
            $container2Type = null;
            /** @var string|null $container2Handle */
            $container2Handle = null;
            $fieldContainers = explode('|', $fieldContainer);

            foreach ($fieldContainers as $key => $fieldContainer) {
                $containerHandleVar = 'container' . $key . 'Handle';
                $containerTypeVar = 'container' . $key . 'Type';
                $container = explode('-', $fieldContainer);
                if (isset($container[0])) {
                    $$containerHandleVar = $container[0];
                }
                if (isset($container[1])) {
                    $$containerTypeVar = $container[1];
                }
            }

            if ($container0Type == 'Matrix' && $container1Type == 'EntryType') {
                $matrixField = Craft::$app->fields->getFieldByHandle($container0Handle);
                if ($matrixField) {
                    /** @var Matrix $matrixField */
                    $entryTypes = $matrixField->getEntryTypes();
                    foreach ($entryTypes as $key => $entryType) {
                        if ($entryType->handle == $container1Handle) {
                            $entryTypeFields = $entryType->getCustomFields();
                            foreach ($entryTypeFields as $entryTypeField) {
                                if (!$container2Type) {
                                    if ($fieldType) {
                                        if (get_class($entryTypeField) != $fieldType) {
                                            continue;
                                        }
                                    }
                                    if ($fieldHandle) {
                                        if ($entryTypeField->handle != $fieldHandle) {
                                            continue;
                                        }
                                    }
                                    $fieldsArray[] = ['type' => 'field', 'field' => $entryTypeField, 'value' => $entryTypeField->uid, 'label' => $entryTypeField->name];
                                } elseif ($container2Type == 'Table') {
                                    if (get_class($entryTypeField) == 'craft\fields\Table' && $entryTypeField->handle == $container2Handle) {
                                        foreach ($entryTypeField->columns as $key => $tableColumn) {
                                            if ($fieldType) {
                                                if ($tableColumn['type'] != TableHelper::fieldType2ColumnType($fieldType)) {
                                                    continue;
                                                }
                                            }
                                            if ($fieldHandle) {
                                                if ($tableColumn['handle'] != $fieldHandle) {
                                                    continue;
                                                }
                                            }
                                            if (!empty($tableColumn['handle'])) {
                                                $fieldsArray[] = ['type' => 'column', 'column' => $tableColumn, 'value' => $tableColumn['handle'], 'label' => $tableColumn['handle']];
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
            } elseif ($container0Type == 'Table') {
                $fields = Craft::$app->fields->getAllFields();
                foreach ($fields as $field) {
                    if (get_class($field) == 'craft\fields\Table' && $container0Handle == $field->handle) {
                        // $fieldsArray = ['type' => 'field', 'field' => $fieldItem, 'value' => $field->handle, 'label' => $field->name];
                        foreach ($field->columns as $key => $tableColumn) {
                            if ($fieldType) {
                                if ($tableColumn['type'] != TableHelper::fieldType2ColumnType($fieldType)) {
                                    continue;
                                }
                            }
                            if ($fieldHandle) {
                                if ($tableColumn['handle'] != $fieldHandle) {
                                    continue;
                                }
                            }
                            if (!empty($tableColumn['handle'])) {
                                $fieldsArray[] = ['type' => 'column', 'column' => $tableColumn, 'value' => $tableColumn['handle'], 'label' => $tableColumn['handle']];
                            }
                        }
                        break;
                    }
                }
            }
        }
        return $fieldsArray;
    }
}
