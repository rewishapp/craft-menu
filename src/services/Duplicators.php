<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\Plugin;

class Duplicators extends Component
{
    /**
     * Clone a menu with its settings, node field layout, and whole node tree.
     */
    public function duplicateMenu(Menu $menu, ?string $title = null, ?string $handle = null): ?Menu
    {
        if (!$menu->id) {
            return null;
        }

        $copy = new Menu();
        $copy->title = $title ?: $menu->title.' '.\Craft::t('menu', '(copy)');
        $copy->handle = $handle ?: $this->uniqueHandle((string) $menu->handle);
        $copy->instructions = $menu->instructions;
        $copy->maxLevels = $menu->maxLevels;
        $copy->sortOrder = $menu->sortOrder;
        $copy->cssClass = $menu->cssClass;
        $copy->htmlAttributes = $menu->htmlAttributes;
        $copy->enabled = (bool) $menu->enabled;
        $copy->enabledSiteIds = $menu->getEnabledSiteIds();
        $copy->nodeFieldLayoutId = $this->cloneLayout($menu);

        if (!\Craft::$app->getElements()->saveElement($copy)) {
            return null;
        }

        $nodes = Plugin::getInstance()->nodes;
        $tree = $nodes->getTree((int) $menu->id, null, false);
        $this->duplicateBranch($tree, (int) $copy->id, null, false);

        return $copy;
    }

    /**
     * Clone a node and every descendant.
     *
     * @param null|int $parentId new parent node ID; null keeps the source node's parent
     */
    public function duplicateNode(Node $node, ?int $parentId = null): ?Node
    {
        if (!$node->id) {
            return null;
        }

        $parentId ??= $node->getParent()?->id;

        $clone = $this->cloneNode($node, (int) $node->menuId, $parentId ? (int) $parentId : null, true);
        if (!$clone) {
            return null;
        }

        $this->duplicateChildren($node, $clone);

        return $clone;
    }

    public function uniqueHandle(string $base): string
    {
        $base = $base !== '' ? $base : 'menu';
        $handle = $base.'Copy';
        $i = 2;
        while (Plugin::getInstance()->menus->getByHandle($handle, true)) {
            $handle = $base.'Copy'.$i;
            ++$i;
        }

        return $handle;
    }

    /**
     * Remove tab / element UIDs so the cloned layout gets fresh ones (field references stay).
     *
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    public static function stripUids(array $config): array
    {
        $out = [];
        foreach ($config as $key => $value) {
            if ($key === 'uid') {
                continue;
            }
            $out[$key] = \is_array($value) ? self::stripUids($value) : $value;
        }

        return $out;
    }

    /**
     * @param list<array{node: object, children: list}> $branch
     */
    private function duplicateBranch(array $branch, int $menuId, ?int $parentId, bool $markCopy): void
    {
        foreach ($branch as $item) {
            $node = $item['node'] ?? null;
            if (!$node instanceof Node) {
                continue;
            }
            $clone = $this->cloneNode($node, $menuId, $parentId, $markCopy);
            if ($clone && !empty($item['children'])) {
                $this->duplicateBranch($item['children'], $menuId, (int) $clone->id, false);
            }
        }
    }

    private function duplicateChildren(Node $source, Node $clone): void
    {
        $children = Node::find()
            ->menuId((int) $source->menuId)
            ->descendantOf($source)
            ->descendantDist(1)
            ->status(null)
            ->siteId($source->siteId)
            ->all()
        ;
        foreach ($children as $child) {
            $childClone = $this->cloneNode($child, (int) $clone->menuId, (int) $clone->id, false);
            if ($childClone) {
                $this->duplicateChildren($child, $childClone);
            }
        }
    }

    private function cloneNode(Node $node, int $menuId, ?int $parentId, bool $markCopy): ?Node
    {
        $attributes = [
            'menuId' => $menuId,
            'newParentId' => $parentId,
        ];
        if ($markCopy) {
            $attributes['title'] = $node->title.' '.\Craft::t('menu', '(copy)');
        }

        try {
            /** @var Node $clone */
            $clone = \Craft::$app->getElements()->duplicateElement($node, $attributes, false);
        } catch (\Throwable $e) {
            \Craft::warning('Could not duplicate node '.$node->id.': '.$e->getMessage(), __METHOD__);

            return null;
        }

        return $clone;
    }

    private function cloneLayout(Menu $menu): ?int
    {
        $layout = $menu->getNodeFieldLayout();
        $config = $layout?->getConfig();
        if (!$config) {
            return null;
        }

        try {
            $copy = \Craft::$app->getFields()->createLayout(self::stripUids($config));
            $copy->type = Node::class;
            if (\Craft::$app->getFields()->saveLayout($copy)) {
                return (int) $copy->id;
            }
        } catch (\Throwable $e) {
            \Craft::warning('Could not duplicate node field layout: '.$e->getMessage(), __METHOD__);
        }

        return null;
    }
}
