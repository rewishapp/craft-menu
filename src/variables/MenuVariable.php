<?php

namespace rewish\craft\menu\variables;

use rewish\craft\menu\elements\db\MenuQuery;
use rewish\craft\menu\elements\db\NodeQuery;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\Plugin;

class MenuVariable
{
    public function menus(?string $handle = null): Menu|MenuQuery|null
    {
        if ($handle !== null) {
            return Plugin::getInstance()?->menus->getByHandle($handle);
        }

        return Menu::find();
    }

    public function nodes(): NodeQuery
    {
        return Node::find();
    }

    /**
     * @return list<array{node: Node, children: list}>
     */
    public function tree(string $handle, ?int $siteId = null): array
    {
        $menu = Plugin::getInstance()?->menus->getByHandle($handle);
        if (!$menu?->id) {
            return [];
        }
        $siteId ??= (int) \Craft::$app->getSites()->getCurrentSite()->id;
        if (!$menu->isEnabledForSite($siteId)) {
            return [];
        }

        return Plugin::getInstance()->nodes->getTree((int) $menu->id, $siteId);
    }

    /**
     * Tree with `active`, `current`, and `hasActiveChild` flags.
     *
     * @return list<array{node: Node, children: list, active: bool, current: bool, hasActiveChild: bool}>
     */
    public function activeTree(string $handle, ?string $uri = null, ?int $siteId = null): array
    {
        return Plugin::getInstance()?->activeMatcher->tree($handle, $uri, $siteId) ?? [];
    }

    public function isActive(object $node, ?string $uri = null): bool
    {
        return Plugin::getInstance()?->activeMatcher->isActive($node, $uri) ?? false;
    }

    public function isCurrent(object $node, ?string $uri = null): bool
    {
        return Plugin::getInstance()?->activeMatcher->isCurrent($node, $uri) ?? false;
    }

    /**
     * @return list<Node>
     */
    public function breadcrumbs(string $handle, ?string $uri = null): array
    {
        if ($uri === null) {
            try {
                $uri = \Craft::$app->getRequest()->getPathInfo();
            } catch (\Throwable) {
                $uri = '';
            }
        }

        return Plugin::getInstance()?->nodes->breadcrumbs($handle, $uri) ?? [];
    }
}
