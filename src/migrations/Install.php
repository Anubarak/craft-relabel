<?php
/**
 * relabel plugin for Craft CMS 3.x
 *
 * Relabel Plugin Craft
 *
 * @link      www.anubarak.de
 * @copyright Copyright (c) 2018 anubarak
 */

namespace anubarak\relabel\migrations;

use anubarak\relabel\Relabel;

use Craft;
use craft\config\DbConfig;
use craft\db\Migration;

/**
 * @author    anubarak
 * @package   Relabel
 * @since     1
 */
class Install extends Migration
{
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->addForeignKeys();
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    // Protected Methods
    // =========================================================================

    /**
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        //$tableSchema = Craft::$app->db->schema->getTableSchema('{{%relabel}}');
        if (true) {
            $tablesCreated = true;
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

        return $tablesCreated;
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
        $this->addForeignKey(null, '{{%relabel}}', ['fieldLayoutId'], '{{%fieldlayouts}}', ['id'], 'CASCADE', null);
    }

    /**
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists('{{%relabel}}');
    }
}
