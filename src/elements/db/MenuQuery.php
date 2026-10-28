<?php

namespace rewish\craft\menu\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * @method null|\rewish\craft\menu\elements\Menu one($db = null)
 * @method \rewish\craft\menu\elements\Menu[]    all($db = null)
 */
class MenuQuery extends ElementQuery
{
    public mixed $handle = null;

    public function handle(mixed $value): static
    {
        $this->handle = $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('menu_menus');
        $this->query->select([
            'menu_menus.handle',
            'menu_menus.structureId',
            'menu_menus.instructions',
            'menu_menus.maxLevels',
            'menu_menus.sortOrder',
            'menu_menus.nodeFieldLayoutId',
            'menu_menus.cssClass',
            'menu_menus.htmlAttributes',
        ]);

        if ($this->handle) {
            $this->subQuery->andWhere(Db::parseParam('menu_menus.handle', $this->handle));
        }

        return parent::beforePrepare();
    }
}
