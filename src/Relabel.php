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
use craft\base\FieldInterface;
use craft\events\FieldLayoutEvent;
use craft\events\TemplateEvent;
use craft\services\Fields;
use anubarak\relabel\services\RelabelService;
use Craft;
use craft\base\Plugin;
use craft\web\Controller;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use yii\base\ActionEvent;
use yii\base\Event;

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
     * @var Relabel
     */
    public static $plugin;

    /**
     * @var FieldInterface[] $fieldById
     */
    public static $fieldById = [];

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
            if($layout !== null){
                $labelsForLayout = Relabel::getService()->getAllLabelsForLayout($layout->id);
                foreach ($labelsForLayout as $relabel){
                    /** @var Field $originalField */
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
        }

        return $errors;
    }

    /**
     * @param $id
     *
     * @return \craft\base\FieldInterface
     */
    public static function getFieldById($id): FieldInterface
    {
        if(!isset(self::$fieldById[$id])){
            self::$fieldById[$id] = Craft::$app->getFields()->getFieldById($id);
        }

        return self::$fieldById[$id];
    }


    /**
     * @var string
     */
    public $schemaVersion = '1.0.0';


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
            Event::on(
                CraftVariable::class,
                CraftVariable::EVENT_INIT,
                function(Event $event) {
                    /** @var CraftVariable $variable */
                    $variable = $event->sender;
                    $variable->set('relabel', Variable::class);
                }
            );
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
            if ($request->getIsAjax()) {
                self::getService()->handleAjaxRequest();
            } else {
                Event::on(
                    View::class,
                    View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
                    function(TemplateEvent $event){
                        self::getService()->handleGetRequest();
                    }
                );
            }
        }

        return true;
    }

    /**
     * @return RelabelService|object
     * @throws \yii\base\InvalidConfigException
     */
    public static function getService(): RelabelService
    {
        return self::$plugin->get('relabel');
    }
}
