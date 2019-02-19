<?php
/**
 * relabel plugin for Craft CMS 3.x
 *
 * Relabel Plugin Craft
 *
 * @copyright Copyright (c) 2018 anubarak
 */

namespace anubarak\relabel\migrations;

use craft\db\Migration;

/**
 * @author    anubarak
 * @package   Relabel
 * @since     1
 */
class Install extends Migration
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================


    protected function createTables()
    {
        // no migration yet... maybe if there is a feature request...
        $this->dropTableIfExists('{{%relabel}}');
        $this->createTable(
            '{{%relabel}}',
            [
                'id'            => $this->primaryKey(),
                'name'          => $this->string()->notNull(),
                'instructions'  => $this->string()->notNull(),
                'fieldId'       => $this->integer()->notNull(),
                'fieldLayoutId' => $this->integer()->notNull(),
                'dateCreated'   => $this->dateTime()->notNull(),
                'dateUpdated'   => $this->dateTime()->notNull(),
                'uid'           => $this->uid(),
            ]
        );
    }

    /**
     * @return void
     */
    protected function addForeignKeys()
    {
        $this->addForeignKey(
            null,
            '{{%relabel}}',
            ['fieldId'],
            '{{%fields}}',
            ['id'],
            'CASCADE',
            null
        );

        $this->addForeignKey(
            null,
            '{{%relabel}}',
            ['fieldLayoutId'],
            '{{%fieldlayouts}}',
            ['id'],
            'CASCADE'
        );
    }

    /**
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%relabel}}');
    }
}
