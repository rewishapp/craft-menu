<?php

namespace rewish\craft\menu\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property int         $structureId
 * @property string      $handle
 * @property null|string $instructions
 * @property null|int    $maxLevels
 * @property null|int    $sortOrder
 * @property null|int    $nodeFieldLayoutId
 * @property null|string $cssClass
 * @property null|string $htmlAttributes
 */
class MenuRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%menu_menus}}';
    }
}
