<?php
/**
 * relabel plugin for Craft CMS 3.x
 *
 * Relabel Plugin Craft
 *
 * @copyright Copyright (c) 2018 anubarak
 */

namespace anubarak\relabel;

use anubarak\relabel\events\RegisterLabelEvent;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Entry;
use craft\events\FieldLayoutEvent;
use craft\helpers\Json;
use craft\services\Fields;
use anubarak\relabel\records\RelabelRecord;
use anubarak\relabel\services\RelabelService;
use Craft;
use craft\base\Plugin;
use yii\base\Event;
use yii\web\NotFoundHttpException;

/**
 * Class Relabel
 *
 * @author    Robin Schambach
 * @package   Relabel
 * @since     1
 *
 * @property  relabel $relabelService
 */
class Relabel extends Plugin
{
    // Static Properties
    // =========================================================================

    /**
     * Event to register a field layout ID for custom elements
     */
    const EVENT_REGISTER_LABELS = 'eventRegisterLabels';

    /**
     * @param \craft\models\FieldLayout $fieldLayout
     * @param \craft\base\Field         $field
     *
     * @return null|string
     */
    public static function getLabelForField($fieldLayout, $field){
        /** @var RelabelRecord $record */
        $record = RelabelRecord::find()->where(
            [
                'fieldId'       => $field->id,
                'fieldLayoutId' => $fieldLayout->id
            ]
        )->one();

        if($record !== null){
            return $record->name;
        }

        return $field->name;
    }

    /**
     * @param ElementInterface $element
     *
     * @return array
     */
    public static function getErrors(ElementInterface $element): array
    {
        /** @var Element $element */
        $errors = $element->getErrors();
        if(!empty($errors)){
            $layout = $element->getFieldLayout();
            $labelsForLayout = Relabel::getService()->getAllLabelsForLayout($layout->id);
            foreach ($labelsForLayout as $relabel){
                $originalField = Relabel::getFieldById($relabel['fieldId']);
                if(isset($errors[$relabel['handle']])){
                    /** @var array $messages */
                    $messages = $errors[$relabel['handle']];
                    foreach ($messages as $key => $message){

                        $str = preg_replace('/^'.$originalField->name.'/',$relabel['name'],$message);
                        if($str){
                            $errors[$relabel['handle']][$key] = $str;
                        }
                    }
                }
            }
        }

        return $errors;
    }

    public static $fieldById = [];

    /**
     * @param $id
     *
     * @return \craft\base\Field
     */
    public static function getFieldById($id): Field
    {
        if(!isset(self::$fieldById[$id])){
            self::$fieldById[$id] = Craft::$app->getFields()->getFieldById($id);
        }

        return self::$fieldById[$id];
    }

    /**
     * @var Relabel
     */
    public static $plugin;
    // Public Properties
    // =========================================================================

    /**
     * @var string
     */
    public $schemaVersion = '1';
    // Public Methods
    // =========================================================================


    /**
     * @inheritdoc
     * @throws \yii\web\NotFoundHttpException
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\db\StaleObjectException
     * @throws \Throwable
     * @throws \Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;
        $request = Craft::$app->getRequest();
        if( $request->getIsConsoleRequest() || strpos($request->getFullPath(), 'admin/actions/debug/default') !== false){
            return false;
        }

        if($request->getIsSiteRequest()){
            Craft::$app->getView()->getTwig()->addGlobal('relabel', new Variable());
            return false;
        }

        Craft::info(
            Craft::t(
                'relabel',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );

        Event::on(
            Fields::class,
            Fields::EVENT_AFTER_SAVE_FIELD_LAYOUT,
            function(FieldLayoutEvent $event) {
                $layout = $event->layout;
                /** @var array|null $relabel */
                $relabel = Craft::$app->getRequest()->getParam('relabel');

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
        );


        /**
         * Register Components
         */
        $this->setComponents(
            [
                'relabel' => RelabelService::class
            ]
        );

        if($this->isInstalled){
            $this->includeResources();
        }
    }

    /**
     * @throws \yii\web\NotFoundHttpException
     * @throws \yii\web\BadRequestHttpException
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\web\NotFoundHttpException
     */
    protected function includeResources()
    {
        $request = Craft::$app->getRequest();

        // just in case someone has the idea to call this manually...
        if (!$request->getIsCpRequest() || $request->getIsConsoleRequest()) {
            return false;
        }
        if(strpos(Craft::$app->getRequest()->getFullPath(), 'admin/actions/debug/default') !== false){
            return false;
        }

        if ($request->getIsAjax()) {
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
                $siteId = $request->getBodyParam('siteId');
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
        } else {
            $labelsForLayout = [];
            $segments = $request->segments;
            $layout = null;
            if (\count($segments) >= 2) {
                switch ($segments[0]) {
                    case 'entries':
                        $lastSegment = $segments[\count($segments) - 1];
                        $id = explode('-', $lastSegment)[0];
                        if ($id && strpos($lastSegment, '-')) {
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
                        if ($groupHandle = $segments[1]) {
                            if ($group = Craft::$app->getCategories()->getGroupByHandle($groupHandle)) {
                                $layout = $group->getFieldLayout();
                            }
                        }
                        break;
                    case 'globals':
                        $handle = $segments[\count($segments) - 1];
                        if ($globals = Craft::$app->getGlobals()->getSetByHandle($handle)) {
                            $layout = $globals->getFieldLayout();
                        }

                        break;
                    case 'settings':
                        // TODO include Users and custom event for custom field layout
                        break;
                }
            }
            if($layout !== null){
                $event = new RegisterLabelEvent([
                    'fieldLayoutId' => $layout->id
                ]);
                $this->trigger(self::EVENT_REGISTER_LABELS, $event);
                $labelsForLayout = Relabel::getService()->getAllLabelsForLayout($event->fieldLayoutId);
            }

            Craft::$app->getView()->registerAssetBundle(RelabelAsset::class);
            $data = json_encode(
                [
                    'labels'          => $this->_getLabels(),
                    'labelsForLayout' => $labelsForLayout
                ]
            );

            $view = Craft::$app->getView();
            $view->registerTranslations('relabel', ['new label', 'new description']);
            $view->registerJs('Craft.Relabel.init(' . $data . ');');
        }
    }

    /**
     * @return array
     */
    private function _getLabels(): array
    {
        $labels = self::$plugin->getService()->getAllLabels();
        $output = [];
        foreach ($labels as $label) {
            $output[] = $label->getAttributes();
        }

        return $output;
    }

    /**
     * @return RelabelService
     */
    public static function getService(): RelabelService
    {
        return self::$plugin->get('relabel');
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
