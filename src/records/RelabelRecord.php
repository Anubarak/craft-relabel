<?php
/**
 * relabel plugin for Craft CMS 3.x
 *
 * Relabel Plugin Craft
 *
 * @link      www.anubarak.de
 * @copyright Copyright (c) 2018 anubarak
 */

namespace anubarak\relabel\records;

use anubarak\relabel\Relabel;

use Craft;
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
 * @property \craft\models\FieldLayout $fieldLayoutId    FieldLayout
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
