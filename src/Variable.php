<?php
/**
 * Created by PhpStorm.
 * User: scham
 * Date: 31.05.2018
 * Time: 14:00
 */

namespace anubarak\relabel;

use anubarak\relabel\records\RelabelRecord;
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

    /**
     * find
     *
     *
     * @return \yii\db\ActiveQuery
     *
     * @author Robin Schambach
     * @since  15.07.2020
     */
    public function find()
    {
        return RelabelRecord::find();
    }

    /**
     * alteredLayouts
     *
     *
     * @return array
     *
     * @author Robin Schambach
     * @since  15.07.2020
     * @throws \yii\base\InvalidConfigException
     */
    public function alteredLayouts()
    {
        return Relabel::getService()->getAllAlteredLayouts();
    }
}