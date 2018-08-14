<?php
/**
 * relabel plugin for Craft CMS 3.x
 *
 * Relabel Plugin Craft
 *
 * @copyright Copyright (c) 2018 anubarak
 */
namespace anubarak\relabel\events;

use yii\base\Event;

class RegisterLabelEvent extends Event{

    /**
     * @var int $fieldLayoutId
     */
    public $fieldLayoutId;
}