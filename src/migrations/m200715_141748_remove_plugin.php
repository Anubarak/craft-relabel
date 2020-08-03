<?php

namespace anubarak\relabel\migrations;

use anubarak\relabel\records\RelabelRecord;
use anubarak\relabel\Relabel;
use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;

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
            $newLayouts = Relabel::getService()->getAllAlteredLayouts();

            // alter them...
            $fieldLayouts = Craft::$app->getFields();
            echo PHP_EOL .  "Relabel: start altering Fields for Craft 3.5" . PHP_EOL;
            foreach ($newLayouts as $layout){

                $realLayout = $fieldLayouts->getLayoutById((int)$layout['id']);
                echo "grabbing layout {$layout['id']}" . PHP_EOL;
                //            echo "<pre>";
                //            var_dump($layout['link']);
                //            var_dump($realLayout->getTabs()[0]->elements);
                //            echo "</pre>";
                //            die();

                $saveIt = false;
                if($realLayout){
                    $tabsById = ArrayHelper::index($layout['tabs'], 'id');
                    $tabsByName = ArrayHelper::index($layout['tabs'], 'name');
                    foreach ($realLayout->getTabs() as $tab){
                        $relabelTab = $tabsById[(int)$tab->id]?? $tabsByName[$tab->name] ?? null;
                        if($relabelTab !== null){
                            $fields = $relabelTab['relabels']?? [];
                            if(empty($fields) === false){
                                $fieldsById = ArrayHelper::index($fields, 'fieldId');

                                foreach ($tab->elements as $element){

                                    if($element instanceof CustomField && method_exists($element, 'getField') && $element->getField()){
                                        $realField = $element->getField();
                                        $id = $realField->id;

                                        $relabel = $fieldsById[$id]?? null;
                                        if($relabel !== null){

                                            $element->instructions = $relabel['instructions'];
                                            $element->label = $relabel['name'];
                                            $saveIt = true;
                                            echo "Renaming Field {$realField->name} to {$element->label}" . PHP_EOL;
                                        }else{
                                            echo ("relabel not found for field with ID {$id} and name {$realField->name}, please make sure to check if this is correct") . PHP_EOL;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if($saveIt === true){
                        $success = $fieldLayouts->saveLayout($realLayout);
                        if($success === false){
                            echo "error saving {$realLayout->id}" . PHP_EOL;
                            echo "errors: " . Json::encode($realLayout->getErrors()) . PHP_EOL;
                            echo "please go to the index page and try to set them manually" . PHP_EOL;

                        }else{
                            echo "saved {$realLayout->id} successfully" . PHP_EOL;
                        }
                    }else{
                        echo "No changed labels found for Layout with id {$realLayout->id} this is likely be an error.. please check it manually" . PHP_EOL;
                    }
                }else{
                    echo "Could not find the field-layout for relabeled fields.. the migration won't run for layout with id {$layout['id']} please check everything manually" . PHP_EOL;
                }
            }

            echo "Relabel migration finished..." . PHP_EOL;
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
