<?php

namespace vnali\assetindexesextra\migrations;

use craft\db\Migration;

/**
 * m240630_223459_text_columns_to_string migration.
 */
class m240630_223459_text_columns_to_string extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Get the indexes for the table
        $indexes = $this->db->createCommand("SHOW INDEX FROM {{%assetIndexesExtra_logs}}")->queryAll();

        // Find the index name for the specified column
        $columns = ['volumeHandle', 'volumeName', 'username'];
        foreach ($indexes as $index) {
            if (in_array($index['Column_name'], $columns)) {
                // Drop the index
                $this->dropIndex($index['Key_name'], '{{%assetIndexesExtra_logs}}');
            }
        }

        // Alter column from TEXT to STRING
        $this->alterColumn('{{%assetIndexesExtra_logs}}', 'volumeHandle', $this->string());
        $this->alterColumn('{{%assetIndexesExtra_logs}}', 'volumeName', $this->string());
        $this->alterColumn('{{%assetIndexesExtra_logs}}', 'username', $this->string());

        // Re-create the index on the column
        $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['volumeHandle']);
        $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['volumeName']);
        $this->createIndex(null, '{{%assetIndexesExtra_logs}}', ['username']);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m240630_223459_text_columns_to_string cannot be reverted.\n";
        return false;
    }
}
