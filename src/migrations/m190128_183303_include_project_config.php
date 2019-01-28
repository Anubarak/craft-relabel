<?php

namespace anubarak\relabel\migrations;

use anubarak\relabel\records\RelabelRecord;
use anubarak\relabel\services\RelabelService;
use Craft;
use craft\db\Migration;
use craft\helpers\Db;

/**
 * m190128_183303_include_project_config migration.
 */
class m190128_183303_include_project_config extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // include all fields to the project config
        /** @var RelabelRecord[] $records */
        $records = RelabelRecord::find()->all();
        $projectConfig = Craft::$app->getProjectConfig();
        foreach ($records as $record){
            $path = RelabelService::CONFIG_RELABEL_KEY . '.' . $record->uid;
            $projectConfig->set(
                $path,
                [
                    'field'        => Db::uidById('{{%fields}}', (int) $record->fieldId),
                    'fieldLayout'  => Db::uidById('{{%fieldlayouts}}', (int) $record->fieldLayoutId),
                    'instructions' => $record->instructions,
                    'name'         => $record->name
                ]
            );
        }

    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190128_183303_include_project_config cannot be reverted.\n";
        return false;
    }
}
