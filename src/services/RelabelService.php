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

use anubarak\relabel\events\RegisterAdditionalLabelEvent;
use anubarak\relabel\events\RegisterLabelEvent;
use anubarak\relabel\records\RelabelRecord;
use anubarak\relabel\RelabelAsset;
use function count;
use Craft;
use craft\base\Component;
use craft\base\Element;
use craft\base\Field;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\ConfigEvent;
use craft\events\RebuildConfigEvent;
use craft\fields\Matrix;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\ProjectConfig;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\MatrixBlockType;
use craft\web\View;
use function is_array;
use function is_numeric;
use yii\base\Event;
use yii\base\Exception;
use yii\helpers\Markdown;

/**
 * @author    anubarak
 * @package   Relabel
 * @since     1
 *
 * @property null|\craft\models\FieldLayout                                      $layoutFromRequest
 * @property null|\craft\models\FieldLayout                                      $layoutByTypeId
 * @property \yii\db\ActiveQuery|\anubarak\relabel\records\RelabelRecord[]|array $allLabels
 */
class RelabelService extends Component
{
    const CONFIG_RELABEL_KEY = 'relabel';
    /**
     * Event to register a field layout ID for custom elements
     */
    const EVENT_REGISTER_LABELS = 'eventRegisterLabels';
    const EVENT_REGISTER_ADDITIONAL_LABELS = 'eventRegisterAdditionalLabels';

    /**
     * @return RelabelRecord[]
     */
    public function getAllLabels(): array
    {
        return RelabelRecord::find()->all();
    }

    /**
     * @param int    $layoutId
     * @param string $context
     *
     * @return array
     */
    public function getAllLabelsForLayout(int $layoutId, string $context = ''): array
    {
        $relabels = (new Query())->select(
            [
                'relabel.id',
                'relabel.name',
                'relabel.instructions',
                'relabel.fieldId',
                'relabel.fieldLayoutId',
                'fields.handle',
                'fields.name as oldName'
            ]
        )->from('{{%relabel}} as relabel')->where(['fieldLayoutId' => $layoutId])->leftJoin(
            '{{%fields}} as fields',
            '[[fields.id]] = [[relabel.fieldId]]'
        )->all();

        // markdown support
        foreach ($relabels as $key => $relabel) {
            $instruction = $relabel['instructions'];
            // make sure there is no HTML in it
            if ($instruction === strip_tags($instruction)) {
                // no html, process markdown
                $relabels[$key]['instructions'] = Markdown::process($instruction);
            }

            if(empty($relabel['name'])){
                /** @var \craft\base\Field $field */
                $field = Craft::$app->getFields()->getFieldById((int)$relabel['fieldId']);
                if($field !== null){
                    $relabels[$key]['name'] = $field->name;
                }
            }

            // possible Neo support
            if ($context !== '') {
                $relabels[$key]['handle'] = $context . '.' . $relabels[$key]['handle'];
            }
        }

        return $relabels;
    }

    /**
     * Get the layout
     *
     * @return \craft\models\FieldLayout|null
     * @throws \yii\base\InvalidConfigException
     */
    public function getLayoutFromRequest()
    {
        $request = Craft::$app->getRequest();
        $segments = $request->segments;
        $layout = null;
        if (count($segments) >= 1) {
            switch ($segments[0]) {
                case 'entries':
                    if (count($segments) <= 1) {
                        return null;
                    }
                    $lastSegment = $segments[count($segments) - 1];
                    $id = explode('-', $lastSegment)[0];
                    if ($id && strpos($lastSegment, '-')) {
                        /** @var Element $element */
                        $element = Craft::$app->getElements()->getElementById($id, Entry::class);
                        if($element !== null && $element->typeId !== null){
                            $layout = $element->getFieldLayout();
                        }
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
                    if (count($segments) <= 1) {
                        return null;
                    }
                    if ($groupHandle = $segments[1]) {
                        if ($group = Craft::$app->getCategories()->getGroupByHandle($groupHandle)) {
                            $layout = $group->getFieldLayout();
                        }
                    }
                    break;
                case 'globals':
                    if (count($segments) <= 1) {
                        return null;
                    }
                    $handle = $segments[count($segments) - 1];
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
                case 'gift-voucher':
                    if (count($segments) <= 2) {
                        return null;
                    }

                    // check for an id
                    if (count($segments) === 4) {
                        if (is_numeric($segments[3])) {
                            $element = Craft::$app->getElements()->getElementById(
                                (int) $segments[3],
                                'verbb\\giftvoucher\\elements\\Voucher'
                            );
                            if($element !== nulL){
                                $layout = $element->getFieldLayout();
                            }
                        } else {
                            // unfortunately we can't just use Commerce classes since we can't make
                            // sure they exists and the plugin should run even without it :(
                            $type = $segments[2];
                            $fieldLayoutId = (new Query())->select(['fieldLayoutId'])->from(
                                '{{%giftvoucher_vouchertypes}}'
                            )->where(['handle' => $type])->scalar();
                            /** @var \craft\models\Section $section */
                            if ($fieldLayoutId !== false) {
                                $layout = Craft::$app->getFields()->getLayoutById((int) $fieldLayoutId);
                            }
                        }
                    }

                    break;
                case 'calendar':
                    if ($segments[1] === 'events' && count($segments) >= 2) {
                        $id = $segments[2] ?? null;
                        if ($id !== null) {
                            /** @var \craft\base\Element $element */
                            $element = Craft::$app->getElements()->getElementById((int) $id);
                            if ($element !== null && $element->fieldLayoutId && $element::hasContent()) {
                                $fieldLayoutId = $element->fieldLayoutId;
                                $layout = Craft::$app->getFields()->getLayoutById((int) $fieldLayoutId);
                            } else if ($id === 'new' && count($segments) >= 3) {
                                // seems to be a new one :)
                                // I know this isn't required but I like to double check
                                $handle = $segments[3] ?? null;
                                if ($handle !== null) {
                                    $fieldLayoutId = (new Query())->select(['fieldLayoutId'])->from(
                                            '{{%calendar_calendars}}'
                                        )->where(['handle' => $handle])->scalar();
                                    if ($fieldLayoutId && is_numeric($fieldLayoutId)) {
                                        $layout = Craft::$app->getFields()->getLayoutById((int) $fieldLayoutId);
                                    }
                                }
                            }
                        }
                    }
                    break;
                case 'commerce':
                    if (count($segments) <= 2) {
                        return null;
                    }

                    if ($segments[1] === 'orders') {
                        $lastSegment = $segments[count($segments) - 1];
                        if (is_numeric($lastSegment)) {
                            $element = Craft::$app->getElements()->getElementById(
                                $lastSegment,
                                'craft\\commerce\\elements\\Order'
                            );
                            if($element !== null){
                                $layout = $element->getFieldLayout();
                            }
                        }
                    }

                    if ($segments[1] === 'products') {
                        // unfortunately we can't just use Commerce classes since we can't make
                        // sure they exists and the plugin should run even without it :(
                        $lastSegment = $segments[count($segments) - 1];
                        $id = explode('-', $lastSegment)[0];
                        $variantLayoutId = null;
                        if ($id && strpos($lastSegment, '-')) {
                            /** @var Element $element */
                            $element = Craft::$app->getElements()->getElementById(
                                $id,
                                'craft\\commerce\\elements\\Product'
                            );
                            if($element !== null){
                                $layout = $element->getFieldLayout();
                                // grab the variants
                                $variantLayoutId = (new Query())->select(['variantFieldLayoutId'])->from(
                                    '{{%commerce_producttypes}} products'
                                )->where(['products.id' => $element->typeId])->scalar();
                            }
                        } else {
                            $productGroup = $segments[2];
                            // query for it
                            $data = (new Query())->select(['fieldLayoutId', 'variantFieldLayoutId'])->from(
                                '{{%commerce_producttypes}}'
                            )->where(['handle' => $productGroup])->one();

                            /** @var \craft\models\Section $section */
                            if ($data !== null && isset($data['fieldLayoutId'])) {
                                $layout = Craft::$app->getFields()->getLayoutById((int) $data['fieldLayoutId']);
                                $variantLayoutId = $data['variantFieldLayoutId'];
                            }
                        }
                        if (empty($variantLayoutId) === false) {
                            Event::on(
                                self::class,
                                self::EVENT_REGISTER_ADDITIONAL_LABELS,
                                function(RegisterAdditionalLabelEvent $event) use ($variantLayoutId) {
                                    $labelsForVariant = $this->getAllLabelsForLayout(
                                        (int) $variantLayoutId,
                                        'variants'
                                    );
                                    foreach ($labelsForVariant as $label) {
                                        $event->labels[] = $label;
                                    }
                                }
                            );
                        }
                    }
                    break;
            }
        }

        return $layout;
    }

    /**
     * sourceFieldsFromRequest
     *
     * @return array
     *
     * @author Robin Schambach
     * @since  18.04.2019
     */
    public function sourceFieldsFromRequest(): array
    {
        $sourceFields = [];

        $groups = Craft::$app->getCategories()->getAllGroups();
        foreach ($groups as $group){
            $key = "group:{$group->uid}";
            $sourceFields[$key] = [];
            $fieldLayoutId = $group->fieldLayoutId;
            $labelsForFieldLayout = (new Query())
                ->select(['fieldId', 'name'])
                ->from('{{%relabel}}')
                ->where(['fieldLayoutId' => $fieldLayoutId])
                ->all();
            foreach ($labelsForFieldLayout as $label){
                $sourceFields[$key]["field:{$label['fieldId']}"] = [
                    'label' => $label['name']
                ];
            }
        }

        // get all category groups and their relabels...
        $sections = Craft::$app->getSections()->getAllSections();
        foreach ($sections as $section){
            $key = "section:{$section->uid}";
            $sourceFields[$key] = [];
            $entryTypes = $section->getEntryTypes();
            foreach ($entryTypes as $entryType){
                $fieldLayoutId = $entryType->fieldLayoutId;
                $labelsForFieldLayout = (new Query())
                    ->select(['fieldId', 'name'])
                    ->from('{{%relabel}}')
                    ->where(['fieldLayoutId' => $fieldLayoutId])
                    ->all();
                foreach ($labelsForFieldLayout as $label){
                    $sourceFields[$key]["field:{$label['fieldId']}"] = [
                        'label' => $label['name']
                    ];
                }
            }
        }

        return $sourceFields;
    }

    /**
     * Handle a switch-entry-type or element get-editor-html event
     *
     * @return bool
     */
    public function handleAjaxRequest(): bool
    {
        $request = Craft::$app->getRequest();

        $segments = $request->segments;
        $actionSegment = $segments[count($segments) - 1];
        if ($actionSegment !== 'get-editor-html' && $actionSegment !== 'switch-entry-type') {
            return false;
        }
        $layout = null;
        if ($actionSegment === 'switch-entry-type') {
            $layout = $this->getLayoutByTypeId();
        } else {
            $attributes = $request->getBodyParam('attributes');
            $elementId = $request->getBodyParam('elementId');
            $elementType = $request->getBodyParam('elementType');
            $siteId = (int) $request->getBodyParam('siteId');
            if ($elementId) {
                $element = Craft::$app->getElements()->getElementById((int) $elementId, $elementType, $siteId);
            } else {
                $element = new $elementType($attributes);
            }

            /** @var Element $element */
            if ($element !== null && $element::hasContent()) {
                if (property_exists($element, 'fieldLayoutId') && $element->fieldLayoutId !== null) {
                    $fieldLayoutId = (int) $element->fieldLayoutId;
                    $layout = Craft::$app->getFields()->getLayoutById($fieldLayoutId);
                } elseif (method_exists($element, 'getFieldLayout')) {
                    try {
                        $layout = $element->getFieldLayout();
                    } catch (Exception $exception) {
                        // fail silently
                        $layout = null;
                    }
                }
            }
        }

        $event = new RegisterLabelEvent(
            [
                'fieldLayoutId' => $layout !== null ? (int) $layout->id : null
            ]
        );
        $this->trigger(self::EVENT_REGISTER_LABELS, $event);

        if ($event->fieldLayoutId !== null) {
            $labelsForLayout = $this->getAllLabelsForLayout($event->fieldLayoutId);
            //$this->_includeMatrixBlocks($labelsForLayout, (int) $event->fieldLayoutId);

            $additionalEvent = new RegisterAdditionalLabelEvent(
                [
                    'fieldLayoutId' => (int) $event->fieldLayoutId,
                    'labels'        => $labelsForLayout
                ]
            );

            $this->trigger(self::EVENT_REGISTER_ADDITIONAL_LABELS, $additionalEvent);
            $allLabels = $additionalEvent->labels;

            if ($actionSegment === 'switch-entry-type') {
                Craft::$app->getView()->registerJs(
                    'Craft.relabel.changeEntryType(' . Json::encode($allLabels) . ');'
                );
            } else {
                Craft::$app->getView()->registerJs(
                    'Craft.relabel.initElementEditor(' . Json::encode($allLabels) . ');'
                );
            }
        }

        return true;
    }

    /**
     * Handle all normal HTTP GET requests
     *
     * @throws \yii\base\InvalidConfigException
     */
    public function handleGetRequest()
    {
        // try to grab the current field layout from request by request params
        $layout = $this->getLayoutFromRequest();
        $sourceFields = $this->sourceFieldsFromRequest();

        // fire an event, so others may include custom labels
        $event = new RegisterLabelEvent(
            [
                'fieldLayoutId' => $layout !== null ? $layout->id : null
            ]
        );
        $this->trigger(self::EVENT_REGISTER_LABELS, $event);

        // if there is a field layout, grab new labels
        $allLabels = [];
        if ($event->fieldLayoutId !== null) {
            $labelsForLayout = $this->getAllLabelsForLayout($event->fieldLayoutId);
            //$this->_includeMatrixBlocks($labelsForLayout, (int) $event->fieldLayoutId);

            $additionalEvent = new RegisterAdditionalLabelEvent(
                [
                    'fieldLayoutId' => (int) $event->fieldLayoutId,
                    'labels'        => $labelsForLayout
                ]
            );

            $this->trigger(self::EVENT_REGISTER_ADDITIONAL_LABELS, $additionalEvent);
            $allLabels = $additionalEvent->labels;
        }

        Craft::$app->getView()->registerAssetBundle(RelabelAsset::class);
        $data = Json::encode(
            [
                'labels'          => $this->_getLabels(),
                'labelsForLayout' => $allLabels
            ]
        );

        $view = Craft::$app->getView();
        $view->registerTranslations('relabel', ['new label', 'new description']);
        $view->registerJs('RelabelSourceFields = ' . Json::encode($sourceFields), View::POS_HEAD);
        $view->registerJs('Craft.relabel = new Craft.Relabel(' . $data . ');');
    }

    /**
     * @param \craft\models\FieldLayout $layout
     * @param                           $relabel
     *
     * @throws \yii\base\ErrorException
     * @throws \yii\base\Exception
     * @throws \yii\base\NotSupportedException
     * @throws \yii\web\ServerErrorHttpException
     */
    public function saveRelabelsForLayout(FieldLayout $layout, $relabel)
    {
        if ($relabel !== null && is_array($relabel)) {
            foreach ($relabel as $fieldId => $values) {
                /** @var RelabelRecord $record */
                $record = RelabelRecord::find()->where(
                    [
                        'fieldId'       => $fieldId,
                        'fieldLayoutId' => $layout->id
                    ]
                )->one();

                $isNew = $record === null;

                if ($isNew === false && !$values['name'] && !$values['instructions']) {
                    // remove it
                    $path = self::CONFIG_RELABEL_KEY . '.' . $record->uid;
                    Craft::$app->getProjectConfig()->remove($path);
                    //$record->delete();
                    continue;
                }

                if ($record === null) {
                    $record = new RelabelRecord();
                    $record->uid = StringHelper::UUID();
                } else {
                    if (!$record->uid) {
                        $record->uid = Db::uidById('{{%relabel}}', $record->id);
                    }
                }

                $record->fieldId = $fieldId;
                $record->name = $values['name'];
                $record->instructions = $values['instructions'];
                $record->fieldLayoutId = $layout->id;
                $path = self::CONFIG_RELABEL_KEY . '.' . $record->uid;

                Craft::$app->getProjectConfig()->set(
                    $path,
                    [
                        'field'        => Db::uidById('{{%fields}}', (int) $fieldId),
                        'fieldLayout'  => $layout->uid,
                        'instructions' => $values['instructions'],
                        'name'         => $values['name']
                    ]
                );

                //if (!$record->save()) {
                //    Craft::error('[Relabel] could not store field layout ' . Json::encode($record->getErrors()), Relabel::class);
                //}
            }
        }

        // delete old unused records
        $fieldIds = $layout->getFieldIds();
        $unusedLabels = RelabelRecord::find()->where(['not in', 'fieldId', $fieldIds])->andWhere(
            ['fieldLayoutId' => $layout->id]
        )->all();
        foreach ($unusedLabels as $record) {
            $path = self::CONFIG_RELABEL_KEY . '.' . $record->uid;
            Craft::$app->getProjectConfig()->remove($path);
        }
    }

    /**
     * saveLabelForMatrix
     *
     * @param \craft\models\MatrixBlockType $matrixBlockType
     * @param string|int|\craft\base\Field  $field
     * @param string                        $name
     * @param string                        $instructions
     *
     *
     * @throws \yii\base\ErrorException
     * @throws \yii\base\Exception
     * @throws \yii\base\NotSupportedException
     * @throws \yii\web\ServerErrorHttpException
     * @author Robin Schambach
     * @since  02.04.2019
     */
    public function saveLabelForMatrix(MatrixBlockType $matrixBlockType, $field, string $name, string $instructions = '')
    {
        // grab the correct field
        $fieldId = null;
        if (is_numeric($field)) {
            $fieldId = (int) $field;
        } elseif (is_string($field)) {
            $index = 'matrixBlockType:' . $matrixBlockType->uid;
            $field = ArrayHelper::firstWhere(Craft::$app->getFields()->getAllFields($index), 'handle', $field, true);
            if ($field !== null) {
                $fieldId = (int) $field->id;
            }
        } elseif ($field instanceof Field) {
            $fieldId = (int) $field->id;
        }

        // check if there is already a record for this field and layoutId
        $record = RelabelRecord::find()->where(
            [
                'and',
                'fieldLayoutId' => $matrixBlockType->fieldLayoutId,
                'fieldId'       => $fieldId
            ]
        )->one();

        if ($record === null) {
            $record = new RelabelRecord();
            $record->uid = StringHelper::UUID();
        }

        // store it to the config :)
        $path = self::CONFIG_RELABEL_KEY . '.' . $record->uid;
        Craft::$app->getProjectConfig()->set(
            $path,
            [
                'field'        => Db::uidById('{{%fields}}', (int) $fieldId),
                'fieldLayout'  => $matrixBlockType->getFieldLayout()->uid,
                'instructions' => $instructions,
                'name'         => $name
            ]
        );
    }

    /**
     * deleteLabelFromMatrix
     *
     * @param \craft\models\MatrixBlockType $matrixBlockType
     * @param                               $field
     *
     * @return bool
     *
     * @author Robin Schambach
     * @since  02.04.2019
     */
    public function deleteLabelFromMatrix(MatrixBlockType $matrixBlockType, $field): bool
    {
        // grab the correct field
        $fieldId = null;
        if (is_numeric($field)) {
            $fieldId = (int) $field;
        } elseif (is_string($field)) {
            $index = 'matrixBlockType:' . $matrixBlockType->uid;
            $field = ArrayHelper::firstWhere(Craft::$app->getFields()->getAllFields($index), 'handle', $field, true);
            if ($field !== null) {
                $fieldId = (int) $field->id;
            }
        } elseif ($field instanceof Field) {
            $fieldId = (int) $field->id;
        }

        // check if there is already a record for this field and layoutId
        $record = RelabelRecord::find()->where(
            [
                'and',
                'fieldLayoutId' => $matrixBlockType->fieldLayoutId,
                'fieldId'       => $fieldId
            ]
        )->one();

        // no record found ¯\_(ツ)_/¯
        if ($record === null) {
            return true;
        }

        $path = self::CONFIG_RELABEL_KEY . '.' . $record->uid;
        Craft::$app->getProjectConfig()->remove($path);

        return true;
    }

    /**
     * @param \craft\events\ConfigEvent $event
     *
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * since 28.01.2019
     */
    public function handleDeletedRelabel(ConfigEvent $event)
    {
        // Get the UID that was matched in the config path
        $uid = $event->tokenMatches[0];

        // Get the product type
        /** @var RelabelRecord $record */
        $record = RelabelRecord::findOne(['uid' => $uid]);

        // If that came back empty, we're done!
        if ($record === null) {
            return;
        }

        $record->delete();
    }

    /**
     * @param \craft\events\ConfigEvent $event
     * since 28.01.2019
     *
     * @return bool
     */
    public function handleChangedRelabel(ConfigEvent $event): bool
    {
        // make sure all fields are there
        ProjectConfig::ensureAllFieldsProcessed();

        // Get the UID that was matched in the config path
        $uid = $event->tokenMatches[0];
        $record = RelabelRecord::findOne(['uid' => $uid]);

        if ($record === null) {
            $record = new RelabelRecord();
        }

        $record->uid = $uid;
        $record->fieldId = Db::idByUid('{{%fields}}', $event->newValue['field']);
        $record->fieldLayoutId = Db::idByUid('{{%fieldlayouts}}', $event->newValue['fieldLayout']);
        $record->instructions = $event->newValue['instructions'];
        $record->name = $event->newValue['name'];

        return $record->save();
    }

    /**
     * Rebuild the project config
     *
     * @param \craft\events\RebuildConfigEvent $e
     *
     * @author Robin Schambach
     * @since  29.03.2019
     */
    public function rebuildProjectConfig(RebuildConfigEvent $e)
    {
        /** @var RelabelRecord[] $records */
        $records = RelabelRecord::find()->all();
        foreach ($records as $record) {
            $e->config[self::CONFIG_RELABEL_KEY][$record->uid] = [
                'field'        => Db::uidById(Table::FIELDS, (int) $record->fieldId),
                'fieldLayout'  => Db::uidById(Table::FIELDLAYOUTS, (int) $record->fieldLayoutId),
                'instructions' => $record->instructions,
                'name'         => $record->name
            ];
        }
    }

    /**
     * @param $relabel
     * since 28.01.2019
     */
    public function deleteRelabel($relabel)
    {
        // Remove it from the project config
        $path = self::CONFIG_RELABEL_KEY . ".{$relabel->uid}";
        Craft::$app->projectConfig->remove($path);
    }

    /**
     * @return FieldLayout|null
     */
    public function getLayoutByTypeId()
    {
        $typeId = Craft::$app->getRequest()->getBodyParam('typeId');
        $fieldLayoutId = (new Query())->select(['fieldLayoutId'])
            ->from('{{%entrytypes}}')
            ->where(['id' => $typeId])
            ->scalar();
        $layout = null;
        if ($fieldLayoutId !== null && $fieldLayoutId !== false) {
            $layout = Craft::$app->getFields()->getLayoutById((int) $fieldLayoutId);
        }

        return $layout;
    }

    /**
     * @return array
     */
    private function _getLabels(): array
    {
        $labels = $this->getAllLabels();
        $output = [];
        foreach ($labels as $label) {
            $output[] = $label->getAttributes();
        }

        return $output;
    }

    /**
     * _includeMatrixBlocks
     *
     * @param array    $labels
     * @param int|null $fieldLayoutId
     *
     *
     * @author Robin Schambach
     * @since  02.04.2019
     */
    private function _includeMatrixBlocks(array &$labels, int $fieldLayoutId = null)
    {
        if ($fieldLayoutId === null) {
            return;
        }

        $layout = Craft::$app->getFields()->getLayoutById($fieldLayoutId);
        if ($layout !== null) {
            $fields = $layout->getFields();
            foreach ($fields as $field) {
                if ($field instanceof Matrix) {
                    /** @var \craft\fields\Matrix $field */
                    $blocks = $field->getBlockTypes();
                    foreach ($blocks as $block) {
                        $context = $field->handle . '.' . $block->handle;
                        $relabels = [];
                        if($block->fieldLayoutId !== null){
                            $relabels = $this->getAllLabelsForLayout($block->fieldLayoutId, $context);
                        }
                        foreach ($relabels as $relabel) {
                            $labels[] = $relabel;
                        }
                    }
                }
            }
        }
    }
}
