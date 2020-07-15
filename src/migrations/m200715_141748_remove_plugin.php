<?php

namespace anubarak\relabel\migrations;

use anubarak\relabel\records\RelabelRecord;
use anubarak\relabel\Relabel;
use Craft;
use craft\db\Migration;
use craft\db\Query;

/**
 * m200715_141748_remove_plugin migration.
 */
class m200715_141748_remove_plugin extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (Craft::$app->getConfig()->getGeneral()->allowAdminChanges === true) {
            $fieldLayoutsById = [];

            $newLayouts = Relabel::getService()->getAllAlteredLayouts();
            // TODO apply changes...
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m200715_141748_remove_plugin cannot be reverted.\n";

        return false;
    }
}
