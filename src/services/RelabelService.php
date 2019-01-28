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

use anubarak\relabel\events\RegisterLabelEvent;
use anubarak\relabel\Relabel;
use anubarak\relabel\RelabelAsset;
use craft\base\Element;
use craft\db\Query;
use anubarak\relabel\records\RelabelRecord;
use craft\base\Component;
use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\ConfigEvent;
use craft\helpers\Db;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;

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

    /**
     * @return RelabelRecord[]
     */
    public function getAllLabels(): array
    {
        return RelabelRecord::find()->all();
    }

    /**
     * @param int $layoutId
     *
     * @return array
     */
    public function getAllLabelsForLayout(int $layoutId): array
    {
        return (new Query())->select(
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
        if (\count($segments) >= 1) {
            switch ($segments[0]) {
                case 'entries':
                    if (\count($segments) <= 1) {
                        return null;
                    }
                    $lastSegment = $segments[\count($segments) - 1];
                    $id = explode('-', $lastSegment)[0];
                    if ($id && strpos($lastSegment, '-')) {
                        /** @var Element $element */
                        $element = Craft::$app->getElements()->getElementById($id, Entry::class);
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
                    if (\count($segments) <= 1) {
                        return null;
                    }
                    if ($groupHandle = $segments[1]) {
                        if ($group = Craft::$app->getCategories()->getGroupByHandle($groupHandle)) {
                            $layout = $group->getFieldLayout();
                        }
                    }
                    break;
                case 'globals':
                    if (\count($segments) <= 1) {
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
                case 'gift-voucher':
                    if (\count($segments) <= 2) {
                        return null;
                    }

                    // check for an id
                    if (\count($segments) === 4) {
                        if (\is_numeric($segments[3])) {
                            $element = Craft::$app->getElements()->getElementById(
                                (int) $segments[3],
                                'verbb\\giftvoucher\\elements\\Voucher'
                            );
                            $layout = $element->getFieldLayout();
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
                case 'commerce':
                    if (\count($segments) <= 2) {
                        return null;
                    }

                    if ($segments[1] === 'orders') {
                        $lastSegment = $segments[\count($segments) - 1];
                        if (\is_numeric($lastSegment)) {
                            $element = Craft::$app->getElements()->getElementById(
                                $lastSegment,
                                'craft\\commerce\\elements\\Order'
                            );
                            $layout = $element->getFieldLayout();
                        }
                    }

                    if ($segments[1] === 'products') {
                        // unfortunately we can't just use Commerce classes since we can't make
                        // sure they exists and the plugin should run even without it :(
                        $lastSegment = $segments[\count($segments) - 1];
                        $id = explode('-', $lastSegment)[0];
                        if ($id && strpos($lastSegment, '-')) {
                            /** @var Element $element */
                            $element = Craft::$app->getElements()->getElementById(
                                $id,
                                'craft\\commerce\\elements\\Product'
                            );
                            $layout = $element->getFieldLayout();
                        } else {
                            $productGroup = $segments[2];
                            // query for it
                            $fieldLayoutId = (new Query())->select(['fieldLayoutId'])->from(
                                    '{{%commerce_producttypes}}'
                                )->where(['handle' => $productGroup])->scalar();

                            /** @var \craft\models\Section $section */
                            if ($fieldLayoutId !== false) {
                                $layout = Craft::$app->getFields()->getLayoutById((int) $fieldLayoutId);
                            }
                        }
                    }
                    break;
            }
        }

        return $layout;
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
        $actionSegment = $segments[\count($segments) - 1];
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
                $element = new $elementType();
                Craft::configure($element, $attributes);
            }

            if ($element !== null && $element::hasContent()) {
                $layout = $element->getFieldLayout();
            }
        }

        $event = new RegisterLabelEvent(
            [
                'fieldLayoutId' => $layout !== null ? $layout->id : null
            ]
        );
        $this->trigger(self::EVENT_REGISTER_LABELS, $event);

        if ($event->fieldLayoutId !== null) {
            $labelsForLayout = $this->getAllLabelsForLayout($event->fieldLayoutId);

            if ($actionSegment === 'switch-entry-type') {
                Craft::$app->getView()->registerJs(
                    'Craft.relabel.changeEntryType(' . Json::encode($labelsForLayout) . ');'
                );
            } else {
                Craft::$app->getView()->registerJs(
                    'Craft.relabel.initElementEditor(' . Json::encode($labelsForLayout) . ');'
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
        $labelsForLayout = [];
        // try to grab the current field layout from request by request params
        $layout = $this->getLayoutFromRequest();

        // fire an event, so others may include custom labels
        $event = new RegisterLabelEvent(
            [
                'fieldLayoutId' => $layout !== null ? $layout->id : null
            ]
        );
        $this->trigger(self::EVENT_REGISTER_LABELS, $event);

        // if there is a field layout, grab new labels
        if ($event->fieldLayoutId !== null) {
            $labelsForLayout = $this->getAllLabelsForLayout($event->fieldLayoutId);
        }

        Craft::$app->getView()->registerAssetBundle(RelabelAsset::class);
        $data = Json::encode(
            [
                'labels'          => $this->_getLabels(),
                'labelsForLayout' => $labelsForLayout
            ]
        );

        $view = Craft::$app->getView();
        $view->registerTranslations('relabel', ['new label', 'new description']);
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
        if ($relabel !== null && \is_array($relabel)) {
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
                } else if (!$record->uid) {
                    $record->uid = Db::uidById('{{%relabel}}', $record->id);
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
     */
    public function handleChangedRelabel(ConfigEvent $event)
    {
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

        $record->save();
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
}
