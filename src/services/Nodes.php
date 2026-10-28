<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\helpers\NodeMeta;
use rewish\craft\menu\models\ProjectedNode;
use rewish\craft\menu\Plugin;

class Nodes extends Component
{
    /**
     * @param bool $includeDisabled also return disabled nodes (CP, export)
     *
     * @return list<Node>
     */
    public function getForMenu(int $menuId, ?int $siteId = null, bool $includeDisabled = false): array
    {
        $menu = Plugin::getInstance()?->menus->getById($menuId);
        $query = Node::find()->menuId($menuId);
        if ($includeDisabled) {
            $query->status(null);
        }
        if ($menu?->structureId) {
            $query->structureId($menu->structureId);
        } else {
            $query->orderBy(['title' => \SORT_ASC]);
        }
        if ($siteId) {
            $query->siteId($siteId);
        }

        return $query->all();
    }

    /**
     * Nested tree for Twig: each node has a `children` list.
     * Dynamic nodes get projected children from their source.
     *
     * With `$respectVisibility` (default) disabled menus resolve to an empty tree and
     * disabled / unavailable / rule-hidden nodes are dropped together with their subtrees.
     * Pass `false` for the raw structure (export, duplicate, CP).
     *
     * @return list<array{node: Node|ProjectedNode, children: list, projected?: bool}>
     */
    public function getTree(int $menuId, ?int $siteId = null, bool $respectVisibility = true): array
    {
        $menu = Plugin::getInstance()?->menus->getById($menuId);
        if ($respectVisibility && $menu && !$menu->enabled) {
            return [];
        }
        if ($menu && $siteId && !$menu->isEnabledForSite($siteId)) {
            return [];
        }

        // Always load disabled nodes: the structure needs them so a hidden parent hides its subtree.
        $nodes = $this->getForMenu($menuId, $siteId, true);
        $byId = [];
        foreach ($nodes as $node) {
            $byId[$node->id] = ['node' => $node, 'children' => []];
        }

        $roots = [];
        foreach ($nodes as $node) {
            $parent = $node->getParent();
            $parentId = $parent?->id;
            if ($parentId && isset($byId[$parentId])) {
                $byId[$parentId]['children'][] = &$byId[$node->id];
            } else {
                $roots[] = &$byId[$node->id];
            }
            unset($parent);
        }

        if (!$respectVisibility) {
            return $this->detach($roots);
        }

        $tree = $this->expandDynamicBranch($this->detach($roots), $siteId);

        return Plugin::getInstance()->visibility->filterTree($tree, $siteId);
    }

    /**
     * Flatten a tree to its stored nodes (projected children excluded), in display order.
     *
     * @param list<array{node: object, children: list}> $tree
     *
     * @return list<Node>
     */
    public function flatten(array $tree): array
    {
        $out = [];
        foreach ($tree as $item) {
            if ($item['node'] instanceof Node) {
                $out[] = $item['node'];
            }
            foreach ($this->flatten($item['children'] ?? []) as $child) {
                $out[] = $child;
            }
        }

        return $out;
    }

    /**
     * Remember each node's filtered children so resolvers (GraphQL) don't re-query the structure.
     *
     * @param list<array{node: object, children: list}> $tree
     */
    public function attachTreeChildren(array $tree): void
    {
        foreach ($tree as $item) {
            if ($item['node'] instanceof Node) {
                $item['node']->setTreeChildren(array_map(
                    static fn (array $child) => $child['node'],
                    $item['children'] ?? []
                ));
            }
            $this->attachTreeChildren($item['children'] ?? []);
        }
    }

    /**
     * Plain-array version of a tree for JSON APIs.
     *
     * @param list<array{node: object, children: list}> $tree
     *
     * @return list<array<string, mixed>>
     */
    public function treeToArray(array $tree): array
    {
        $out = [];
        foreach ($tree as $item) {
            $node = $item['node'];
            $meta = NodeMeta::forObject($node);
            unset($meta['visibility']);
            $out[] = [
                'id' => $node instanceof Node ? $node->id : ($node->elementId ?? null),
                'title' => $node->title ?? '',
                'type' => $node->type ?? '',
                'url' => $node->getNodeUrl(),
                'newWindow' => (bool) ($node->newWindow ?? false),
                'classes' => $node->classes ?? null,
                'projected' => $node instanceof ProjectedNode,
                'meta' => $meta,
                'active' => $item['active'] ?? null,
                'current' => $item['current'] ?? null,
                'children' => $this->treeToArray($item['children'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * Nodes from root → current match for a URI (best active match).
     *
     * @return list<Node>
     */
    public function breadcrumbs(string $handle, string $uri): array
    {
        $menu = Plugin::getInstance()?->menus->getByHandle($handle);
        if (!$menu?->id) {
            return [];
        }

        $uri = trim($uri, '/');
        $nodes = array_values(array_filter(
            $this->getForMenu((int) $menu->id),
            static fn (Node $n) => $n->isAvailable()
        ));
        $match = null;
        $bestLen = -1;
        foreach ($nodes as $node) {
            $url = $node->getNodeUrl();
            if (!$url) {
                continue;
            }
            $path = trim((string) (parse_url($url, \PHP_URL_PATH) ?: $url), '/');
            if ($path === $uri || ($path !== '' && str_starts_with($uri.'/', $path.'/'))) {
                if (\strlen($path) > $bestLen) {
                    $bestLen = \strlen($path);
                    $match = $node;
                }
            }
        }

        if (!$match) {
            return [];
        }

        $trail = [];
        $current = $match;
        while ($current instanceof Node) {
            array_unshift($trail, $current);
            $parent = $current->getParent();
            $current = $parent instanceof Node ? $parent : null;
        }

        return $trail;
    }

    /**
     * Move a node in its menu structure.
     *
     * @param string $position `before` | `after` | `into`
     */
    public function move(Node $node, string $position, ?int $targetId): bool
    {
        $menu = $node->getMenu();
        if (!$menu?->structureId || !$node->id) {
            return false;
        }

        $structures = \Craft::$app->getStructures();
        $structureId = (int) $menu->structureId;
        $position = strtolower($position);

        if ($targetId === null || $targetId === 0) {
            return $position === 'into'
                ? $structures->appendToRoot($structureId, $node)
                : $structures->appendToRoot($structureId, $node);
        }

        $target = Node::find()->id($targetId)->menuId((int) $node->menuId)->status(null)->structureId($structureId)->one();
        if (!$target || (int) $target->id === (int) $node->id) {
            return false;
        }

        // Prevent nesting under a descendant.
        $parent = $target;
        while ($parent instanceof Node) {
            if ((int) $parent->id === (int) $node->id) {
                return false;
            }
            $parent = $parent->getParent();
        }

        return match ($position) {
            'before' => $structures->moveBefore($structureId, $node, $target),
            'after' => $structures->moveAfter($structureId, $node, $target),
            'into' => $structures->append($structureId, $node, $target),
            default => false,
        };
    }

    /**
     * Break the by-reference links used while assembling so copies are plain values.
     *
     * @param list<array{node: Node, children: list}> $branch
     *
     * @return list<array{node: Node, children: list}>
     */
    private function detach(array $branch): array
    {
        $out = [];
        foreach ($branch as $item) {
            $out[] = [
                'node' => $item['node'],
                'children' => $this->detach($item['children'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{node: Node, children: list}> $branch
     *
     * @return list<array{node: mixed, children: list, projected?: bool}>
     */
    private function expandDynamicBranch(array $branch, ?int $siteId): array
    {
        $out = [];
        foreach ($branch as $item) {
            /** @var Node $node */
            $node = $item['node'];
            $children = $this->expandDynamicBranch($item['children'] ?? [], $siteId);
            if ($node->type === Node::TYPE_DYNAMIC) {
                $projected = Plugin::getInstance()?->dynamicSources->project($node, $siteId) ?? [];
                foreach ($projected as $p) {
                    $children[] = [
                        'node' => $p,
                        'children' => [],
                        'projected' => true,
                    ];
                }
            }
            $out[] = [
                'node' => $node,
                'children' => $children,
            ];
        }

        return $out;
    }
}
