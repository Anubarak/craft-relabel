<?php
/**
 * relabel plugin for Craft CMS 3.x
 *
 * Relabel Plugin Craft
 *
 * @copyright Copyright (c) 2018 anubarak
 */

namespace anubarak\relabel;

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
use craft\services\Plugins;
use craft\services\ProjectConfig;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use yii\base\Event;

/**
 * Class Relabel
 *
 * @author    Robin Schambach
 * @package   Relabel
 * @since     1
 *
 * @property  relabel $relabel
 */
class Relabel extends Plugin
{
    /**
     * @var string
     */
    public $schemaVersion = '1.1.0';

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
     * @var int $_fieldLayoutIndex
     */
    private $_fieldLayoutIndex = 0;

    /**
     * @param ElementInterface $element
     *
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public static function getErrors(ElementInterface $element): array
    {
        /** @var Element $element */
        $errors = $element->getErrors();
        if(!empty($errors) && $element->fieldLayoutId !== null){
            $layout = $element->getFieldLayout();
            if($layout !== null){
                $labelsForLayout = self::getService()->getAllLabelsForLayout($layout->id);
                foreach ($labelsForLayout as $relabel){
                    /** @var Field $originalField */
                    $originalField = self::getFieldById($relabel['fieldId']);
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
     * @inheritdoc
     * @throws \yii\base\InvalidConfigException
     * @return bool
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        /**
         * Register Components
         */
        $this->setComponents(
            [
                'relabel' => RelabelService::class
            ]
        );

        // include project settings events
        Craft::$app->getProjectConfig()
            ->onAdd(RelabelService::CONFIG_RELABEL_KEY . '.{uid}', [$this->relabel, 'handleChangedRelabel'])
            ->onUpdate(RelabelService::CONFIG_RELABEL_KEY . '.{uid}', [$this->relabel, 'handleChangedRelabel'])
            ->onRemove(RelabelService::CONFIG_RELABEL_KEY . '.{uid}', [$this->relabel, 'handleDeletedRelabel']);


        $request = Craft::$app->getRequest();
        if( $request->getIsConsoleRequest() || strpos($request->getFullPath(), 'admin/actions/debug/default') !== false){
            return false;
        }

        // inject the global to use relabel.getErrors(entry) via frontend
        if($request->getIsSiteRequest()){
            Event::on(
                CraftVariable::class,
                CraftVariable::EVENT_INIT,
                static function(Event $event) {
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
                $new = (bool)$event->isNew;

                if($new === true){
                    $index = 'new' . $this->_fieldLayoutIndex;
                    $this->_fieldLayoutIndex++;
                }else{
                    $index = $layout->id;
                }
                /** @var array|null $relabel */
                $relabel = Craft::$app->getRequest()->getBodyParam('relabel');
                $relabelForLayout = $relabel[$index]?? null;
                if($relabelForLayout !== null){
                    Relabel::getInstance()->getService()->saveRelabelsForLayout($layout, $relabelForLayout);
                }
            }
        );

        Event::on(
            ProjectConfig::class,
            ProjectConfig::EVENT_REBUILD,
            [self::getService(), 'rebuildProjectConfig']
        );

        // ugly hacky fix, but I don't know an alternative https://github.com/craftcms/cms/issues/4944
        // TODO: improve this
        $session = Craft::$app->getSession();
        $id = $session->getHasSessionId() || $session->getIsActive() ? $session->get(Craft::$app->getUser()->idParam) : null;
        if($this->isInstalled && $id !== null){
            if ($request->getIsAjax()) {
                Event::on(
                    Plugins::class,
                    Plugins::EVENT_AFTER_LOAD_PLUGINS,
                    static function(Event $event){
                        self::getService()->handleAjaxRequest();
                    }
                );
            } else {
                Event::on(
                    View::class,
                    View::EVENT_BEFORE_RENDER_PAGE_TEMPLATE,
                    static function(TemplateEvent $event){
                        self::getService()->handleGetRequest();
                    }
                );
            }
        }

        //$blockType = Craft::$app->getMatrix()->getBlockTypeById(88);
        //Relabel::getService()->saveLabelForMatrix($blockType, 'firstName', 'new firstName', 'foobar');

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
