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

use craft\db\Query;
use anubarak\relabel\records\RelabelRecord;
use craft\base\Component;

/**
 * @author    anubarak
 * @package   Relabel
 * @since     1
 *
 * @property \yii\db\ActiveQuery|\anubarak\relabel\records\RelabelRecord[]|array $allLabels
 */
class RelabelService extends Component
{
    // Public Methods
    // =========================================================================


    /**
     * @return RelabelRecord[]
     */
    public function getAllLabels(): array
    {
        return RelabelRecord::find()->all();
    }

    /**
     * @param $layoutId
     *
     * @return array
     */
    public function getAllLabelsForLayout($layoutId): array
    {
        return (new Query())->select(
                [
                    'relabel.id',
                    'relabel.name',
                    'relabel.instructions',
                    'relabel.fieldId',
                    'relabel.fieldLayoutId',
                    'fields.handle',
                ]
            )->from('{{%relabel}}')
            ->where(['fieldLayoutId' => $layoutId])
            ->leftJoin('fields', 'fields.id = relabel.fieldId')
            ->all();
    }
}
