<?php

namespace rewish\craft\menu\records;

use craft\db\ActiveRecord;

/**
 * @property int         $id
 * @property int         $menuId
 * @property string      $type
 * @property null|int    $elementId
 * @property null|string $url
 * @property null|string $classes
 * @property null|string $urlSuffix
 * @property bool        $newWindow
 * @property bool        $followElement
 * @property null|string $customAttributes
 * @property null|string $data
 */
class NodeRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%menu_nodes}}';
    }
}
