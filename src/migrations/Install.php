<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\assetindexesextra\migrations;

use Craft;
use craft\db\Migration;

use Yii;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->deleteTables();
        $this->createTables();
        Craft::$app->db->schema->refresh();
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->deleteTables();
        return true;
    }

    /**
     * Creates the tables needed for the plugin.
     *
     * @return void
     */
    private function createTables(): void
    {
        // Create settings table for asset indexes
        if (!$this->tableExists('{{%assetIndexesExtra_options}}')) {
            $this->createTable('{{%assetIndexesExtra_options}}', [
                'id' => $this->primaryKey(),
                'settings' => $this->text(),
                'enable' => $this->boolean()->defaultValue(false),
                'sortOrder' => $this->integer()->defaultValue(-1),
                'userId' => $this->integer(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, '{{%assetIndexesExtra_options}}', ['enable'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_options}}', ['sortOrder'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_options}}', ['userId'], false);
            $this->addForeignKey(null, '{{%assetIndexesExtra_options}}', ['userId'], '{{%users}}', ['id'], 'SET NULL', null);
        }

        // Create settings table for asset indexes
        if (!$this->tableExists('{{%assetIndexesExtra_logs}}')) {
            $this->createTable('{{%assetIndexesExtra_logs}}', [
                'id' => $this->primaryKey(),
                'optionId' => $this->integer(),
                'itemType' => $this->string(),
                'volumeId' => $this->integer(),
                'volumeHandle' => $this->string(),
                'volumeName' => $this->string(),
                'filename' => $this->string(),
                'itemId' => $this->integer(),
                'assetId' => $this->integer(),
                'cli' => $this->boolean(),
                'status' => $this->boolean(),
                'settings' => $this->text(),
                'result' => $this->text(),
                'userId' => $this->integer(),
                'username' => $this->string(),
                'dateCreated' => $this->dateTime()->notNull(),
            ]);

            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['volumeId'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['volumeHandle'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['volumeName'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['optionId'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['itemType'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['filename'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['assetId'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['itemId'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['cli'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['status'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['userId'], false);
            $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['username'], false);
            $this->addForeignKey(null, '{{%assetIndexesExtra_logs}}', ['volumeId'], '{{%volumes}}', ['id'], 'SET NULL', null);
            $this->addForeignKey(null, '{{%assetIndexesExtra_logs}}', ['assetId'], '{{%elements}}', ['id'], 'SET NULL', null);
            $this->addForeignKey(null, '{{%assetIndexesExtra_logs}}', ['userId'], '{{%users}}', ['id'], 'SET NULL', null);
            $this->addForeignKey(null, '{{%assetIndexesExtra_logs}}', ['optionId'], '{{%assetIndexesExtra_options}}', ['id'], 'SET NULL', null);
        }
    }

    /**
     * Delete the plugin's tables.
     *
     * @return void
     */
    protected function deleteTables(): void
    {
        $this->dropTableIfExists('{{%assetIndexesExtra_logs}}');
        $this->dropTableIfExists('{{%assetIndexesExtra_options}}');
    }

    /**
     * Check if a table exists.
     *
     * @param string $table
     * @return boolean
     */
    private function tableExists($table): bool
    {
        return (Yii::$app->db->schema->getTableSchema($table) !== null);
    }
}
