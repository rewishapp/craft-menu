<?php

namespace rewish\craft\menu\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * @method null|\rewish\craft\menu\elements\Node one($db = null)
 * @method \rewish\craft\menu\elements\Node[]    all($db = null)
 */
class NodeQuery extends ElementQuery
{
    public mixed $menuId = null;

    public mixed $type = null;

    public function menuId(mixed $value): static
    {
        $this->menuId = $value;

        return $this;
    }

    public function type(mixed $value): static
    {
        $this->type = $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        $this->joinElementTable('menu_nodes');
        $this->query->select([
            'menu_nodes.menuId',
            'menu_nodes.type',
            'menu_nodes.elementId',
            'menu_nodes.url',
            'menu_nodes.classes',
            'menu_nodes.urlSuffix',
            'menu_nodes.newWindow',
            'menu_nodes.followElement',
            'menu_nodes.customAttributes',
            'menu_nodes.data',
        ]);

        if ($this->menuId) {
            $this->subQuery->andWhere(Db::parseParam('menu_nodes.menuId', $this->menuId));
        }
        if ($this->type) {
            $this->subQuery->andWhere(Db::parseParam('menu_nodes.type', $this->type));
        }

        return parent::beforePrepare();
    }
}
