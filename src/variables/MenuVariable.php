<?php

namespace rewish\craft\menu\variables;

use craft\helpers\Template;
use craft\web\View;
use rewish\craft\menu\elements\db\MenuQuery;
use rewish\craft\menu\elements\db\NodeQuery;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\Plugin;
use Twig\Markup;

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
    public function tree(Menu|string $handle, ?int $siteId = null): array
    {
        $menu = $handle instanceof Menu ? $handle : Plugin::getInstance()?->menus->getByHandle($handle);
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
     * Tree with `active`, `current`, and `hasActiveChild` flags (visibility rules applied).
     *
     * @return list<array{node: Node, children: list, active: bool, current: bool, hasActiveChild: bool}>
     */
    public function activeTree(Menu|string $handle, ?string $uri = null, ?int $siteId = null): array
    {
        $handle = $handle instanceof Menu ? (string) $handle->handle : $handle;

        return Plugin::getInstance()?->activeMatcher->tree($handle, $uri, $siteId) ?? [];
    }

    /**
     * Render a menu as accessible nested `<nav>` markup (see `menu/_macros/nav`).
     *
     * @param array<string, mixed> $options ariaLabel, class, listClass, itemClass, activeClass, currentClass, uri, siteId
     */
    public function render(Menu|string $handle, array $options = []): Markup
    {
        $menu = $handle instanceof Menu ? $handle : Plugin::getInstance()?->menus->getByHandle($handle);
        if (!$menu?->id) {
            return Template::raw('');
        }

        $tree = $this->activeTree($menu, $options['uri'] ?? null, $options['siteId'] ?? null);
        if ($tree === []) {
            return Template::raw('');
        }

        $options['menu'] = $menu;
        $html = \Craft::$app->getView()->renderTemplate(
            'menu/_macros/render',
            ['tree' => $tree, 'options' => $options],
            View::TEMPLATE_MODE_SITE
        );

        return Template::raw($html);
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
