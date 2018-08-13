<?php
/**
 * Created by PhpStorm.
 * User: anuba
 * Date: 05.08.2018
 * Time: 17:22
 */

namespace anubarak\relabel\events;

use Craft;
use yii\base\Event;

class RegisterLabelEvent extends Event{

    public $fieldLayoutId;
}