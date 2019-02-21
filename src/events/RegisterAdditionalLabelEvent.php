<?php
/**
 * Relabel for Craft CMS 3.x
 *
 * Created with PhpStorm.
 *
 * @link      https://github.com/Anubarak/
 * @email     anubarak1993@gmail.com
 * @copyright Copyright (c) 2019 Robin Schambach
 */

namespace anubarak\relabel\events;

use yii\base\Event;

class RegisterAdditionalLabelEvent extends Event
{
    /**
     * @var int $fieldLayoutId
     */
    public $fieldLayoutId;
    /**
     * @var array $labels
     */
    public $labels = [];
}
