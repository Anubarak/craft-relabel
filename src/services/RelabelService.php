<?php
/**
 * relabel plugin for Craft CMS 3.x
 *
 * Relabel Plugin Craft
 *
 * @link      www.anubarak.de
 * @copyright Copyright (c) 2018 anubarak
 */

namespace anubarak\relabel\services;

use anubarak\relabel\Relabel;
use craft\base\Element;
use craft\db\Query;
use anubarak\relabel\records\RelabelRecord;
use craft\base\Component;
use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Json;
use craft\models\FieldLayout;
use yii\web\NotFoundHttpException;

/**
 * @author    anubarak
 * @package   Relabel
 * @since     1
 *
 * @property \yii\db\ActiveQuery|\anubarak\relabel\records\RelabelRecord[]|array $allLabels
 */
class RelabelService extends Component
{
    // Public Methods
    // =========================================================================


    /**
     * @return RelabelRecord[]
     */
    public function getAllLabels(): array
    {
        return RelabelRecord::find()->all();
    }

    /**
     * @param $layoutId
     *
     * @return array
     */
    public function getAllLabelsForLayout($layoutId): array
    {
        return (new Query())->select(
                [
                    'relabel.id',
                    'relabel.name',
                    'relabel.instructions',
                    'relabel.fieldId',
                    'relabel.fieldLayoutId',
                    'fields.handle',
                ]
            )->from('{{%relabel}}')
            ->where(['fieldLayoutId' => $layoutId])
            ->leftJoin('fields', 'fields.id = relabel.fieldId')
            ->all();
    }

    /**
     * Get the layout
     *
     * @return \craft\models\FieldLayout|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getLayoutFromRequest(){
        $request = Craft::$app->getRequest();
        $segments = $request->segments;
        $layout = null;
        if (\count($segments) >= 1) {
            switch ($segments[0]) {
                case 'entries':
                    if(\count($segments) <= 1){
                        return null;
                    }
                    $lastSegment = $segments[\count($segments) - 1];
                    $id = explode('-', $lastSegment)[0];
                    if ($id && strpos($lastSegment, '-')) {
                        /** @var Element $element */
                        $element = Craft::$app->getElements()->getElementById($id);
                        $layout = $element->getFieldLayout();
                    } else {
                        $sectionHandle = $segments[1];
                        /** @var \craft\models\Section $section */
                        if ($section = Craft::$app->getSections()->getSectionByHandle($sectionHandle)) {
                            $entryTypes = $section->getEntryTypes();
                            $layout = $entryTypes[0]->getFieldLayout();
                        }
                    }

                    break;
                case 'categories':
                    if(\count($segments) <= 1){
                        return null;
                    }
                    if ($groupHandle = $segments[1]) {
                        if ($group = Craft::$app->getCategories()->getGroupByHandle($groupHandle)) {
                            $layout = $group->getFieldLayout();
                        }
                    }
                    break;
                case 'globals':
                    if(\count($segments) <= 1){
                        return null;
                    }
                    $handle = $segments[\count($segments) - 1];
                    if ($globals = Craft::$app->getGlobals()->getSetByHandle($handle)) {
                        $layout = $globals->getFieldLayout();
                    }
                    break;
                case 'myaccount':
                    $layout = Craft::$app->getFields()->getLayoutByType(User::class);
                    break;
                case 'users':
                    $layout = Craft::$app->getFields()->getLayoutByType(User::class);
                    break;
            }
        }

        return $layout;
    }

    /**
     * @return bool
     * @throws NotFoundHttpException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\BadRequestHttpException
     */
    public function handleAjaxRequest(){
        $request = Craft::$app->getRequest();

        $segments = $request->segments;
        $actionSegment = $segments[\count($segments) - 1];
        if ($actionSegment !== 'get-editor-html' && $actionSegment !== 'switch-entry-type') {
            return false;
        }

        if ($actionSegment === 'switch-entry-type') {
            $element = $this->_getEntryModel();
            $layout = $element->getType()->getFieldLayout();
        } else {
            $attributes = $request->getBodyParam('attributes');
            $elementId = $request->getBodyParam('elementId');
            $elementType = $request->getBodyParam('elementType');
            $siteId = (int)$request->getBodyParam('siteId');
            if ($elementId) {
                $element = Craft::$app->getElements()->getElementById((int)$elementId, $elementType, $siteId);
            } else {
                $element = new $elementType();
                Craft::configure($element, $attributes);
            }
            $layout = $element->getFieldLayout();
        }

        if ($layout) {
            $labelsForLayout = Relabel::$plugin->getService()->getAllLabelsForLayout($layout->id);

            if ($actionSegment === 'switch-entry-type') {
                Craft::$app->getView()->registerJs(
                    'Craft.Relabel.changeEntryType(' . json_encode($labelsForLayout) . ');'
                );
            } else {
                Craft::$app->getView()->registerJs(
                    'Craft.Relabel.initElementEditor(' . json_encode($labelsForLayout) . ');'
                );
            }
        }
    }

    /**
     * @param $layout
     * @param $relabel
     * @throws \Exception
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     */
    public function saveRelabelsForLayout(FieldLayout $layout, $relabel)
    {
        if ($relabel !== null && \is_array($relabel)) {
            foreach ($relabel as $fieldId => $values) {
                $record = RelabelRecord::find()->where(
                    [
                        'fieldId'       => $fieldId,
                        'fieldLayoutId' => $layout->id
                    ]
                )->one();

                if ($record && !$values['name'] && !$values['instructions']) {
                    $record->delete();
                    continue;
                }

                if ($record === null) {
                    $record = new RelabelRecord();
                }
                $record->fieldId = $fieldId;
                $record->name = $values['name'];
                $record->instructions = $values['instructions'];
                $record->fieldLayoutId = $layout->id;
                if (!$record->save()) {
                    Craft::error('[Relabel] could not store field layout ' . Json::encode($record->getErrors()), Relabel::class);
                }
            }
        }

        $fieldIds = $layout->getFieldIds();
        $unusedLabels = RelabelRecord::find()->where(['not in', 'fieldId', $fieldIds])->andWhere(
            ['fieldLayoutId' => $layout->id]
        )->all();
        foreach ($unusedLabels as $record) {
            $record->delete();
        }
    }


    /**
     * @return \craft\elements\Entry
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\web\NotFoundHttpException
     * @throws \yii\base\InvalidConfigException
     */
    private function _getEntryModel(): Entry
    {
        // TOOD: heavy as shit... make this more change this to craft\db\Query
        $entryId = Craft::$app->getRequest()->getBodyParam('entryId');
        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');

        if ($entryId) {
            $entry = Craft::$app->getEntries()->getEntryById($entryId, $siteId);

            if (!$entry) {
                throw new NotFoundHttpException('Entry not found');
            }
        } else {
            $entry = new Entry();
            $entry->sectionId = Craft::$app->getRequest()->getRequiredBodyParam('sectionId');

            if ($siteId) {
                $entry->siteId = $siteId;
            }
        }
        $entry->typeId = Craft::$app->getRequest()->getBodyParam('typeId', $entry->typeId);
        if (!$entry->typeId) {
            $entry->typeId = $entry->getSection()->getEntryTypes()[0]->id;
        }

        return $entry;
    }
}
