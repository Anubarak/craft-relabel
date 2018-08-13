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

        // inject the global to use relabel.getErrors(entry) via frontend
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


        /**
         * Register field layout saves
         */
        Event::on(
            Fields::class,
            Fields::EVENT_AFTER_SAVE_FIELD_LAYOUT,
            function(FieldLayoutEvent $event){
                $layout = $event->layout;
                /** @var array|null $relabel */
                $relabel = Craft::$app->getRequest()->getBodyParam('relabel');
                Relabel::getInstance()->getService()->saveRelabelsForLayout($layout, $relabel);
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

        return true;
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
            Relabel::getService()->handleAjaxRequest();
        } else {
            $labelsForLayout = [];
            $layout = self::$plugin->getService()->getLayoutFromRequest();

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
     * @return RelabelService|object
     */
    public static function getService(): RelabelService
    {
        return self::$plugin->get('relabel');
    }
}
