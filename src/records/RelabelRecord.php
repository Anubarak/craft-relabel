<?php
/**
 * relabel plugin for Craft CMS 3.x
 *
 * Relabel Plugin Craft
 *
 * @copyright Copyright (c) 2018 anubarak
 */
namespace anubarak\relabel\records;

use craft\db\ActiveRecord;

/**
 * @author    anubarak
 * @package   Relabel
 * @since     1
 *
 *
 * @property int                       $id               ID
 * @property string                    $name             Name
 * @property string                    $instructions     Instructions
 * @property int                       $fieldId          Field
 * @property int                       $fieldLayoutId    FieldLayout
 */
class RelabelRecord extends ActiveRecord
{
    // Public Static Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%relabel}}';
    }
}
