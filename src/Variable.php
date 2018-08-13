<?php
/**
 * Created by PhpStorm.
 * User: scham
 * Date: 31.05.2018
 * Time: 14:00
 */

namespace anubarak\relabel;

use craft\base\Element;
use craft\base\ElementInterface;
use yii\di\ServiceLocator;

class Variable extends ServiceLocator
{

    /**
     * @param ElementInterface $element
     *
     * @return array
     */
    public function getErrors(ElementInterface $element): array
    {
        return Relabel::getErrors($element);
    }
}