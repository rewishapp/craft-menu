<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\models\ProjectedNode;
use rewish\craft\menu\Plugin;

class ActiveMatcher extends Component
{
    /**
     * Path of the request (no leading/trailing slash).
     */
    public function currentUri(?string $uri = null): string
    {
        if ($uri !== null) {
            return trim($uri, '/');
        }

        try {
            return trim(\Craft::$app->getRequest()->getPathInfo(), '/');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Normalize a node URL to a comparable path.
     */
    public function pathFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '' || $url[0] === '#') {
            return null;
        }
        $path = parse_url($url, \PHP_URL_PATH);
        if ($path === null || $path === false) {
            $path = $url;
        }

        return trim((string) $path, '/');
    }

    public function urlOf(object $node): ?string
    {
        if ($node instanceof Node || $node instanceof ProjectedNode) {
            return $node->getNodeUrl();
        }
        if (method_exists($node, 'getNodeUrl')) {
            return $node->getNodeUrl();
        }

        return null;
    }

    public function isCurrent(object $node, ?string $uri = null): bool
    {
        $path = $this->pathFromUrl($this->urlOf($node));
        if ($path === null) {
            return false;
        }

        return $path === $this->currentUri($uri);
    }

    public function isActive(object $node, ?string $uri = null): bool
    {
        $path = $this->pathFromUrl($this->urlOf($node));
        if ($path === null) {
            return false;
        }
        $uri = $this->currentUri($uri);
        if ($path === $uri) {
            return true;
        }
        if ($path === '') {
            return $uri === '';
        }

        return str_starts_with($uri.'/', $path.'/');
    }

    /**
     * Annotate a tree from {@see Nodes::getTree()} with active flags.
     *
     * @param list<array{node: object, children: list}> $tree
     *
     * @return list<array{node: object, children: list, active: bool, current: bool, hasActiveChild: bool}>
     */
    public function annotateTree(array $tree, ?string $uri = null): array
    {
        $uri = $this->currentUri($uri);
        $out = [];
        foreach ($tree as $item) {
            $children = $this->annotateTree($item['children'] ?? [], $uri);
            $node = $item['node'];
            $current = $this->isCurrent($node, $uri);
            $active = $current || $this->isActive($node, $uri);
            $hasActiveChild = false;
            foreach ($children as $child) {
                if ($child['active'] || $child['hasActiveChild']) {
                    $hasActiveChild = true;

                    break;
                }
            }
            if ($hasActiveChild) {
                $active = true;
            }
            $out[] = [
                'node' => $node,
                'children' => $children,
                'active' => $active,
                'current' => $current,
                'hasActiveChild' => $hasActiveChild,
                'projected' => !empty($item['projected']),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{node: object, children: list, active: bool, current: bool, hasActiveChild: bool}>
     */
    public function tree(string $handle, ?string $uri = null, ?int $siteId = null): array
    {
        $menu = Plugin::getInstance()?->menus->getByHandle($handle);
        if (!$menu?->id) {
            return [];
        }

        try {
            $siteId ??= (int) \Craft::$app->getSites()->getCurrentSite()->id;
        } catch (\Throwable) {
            // Keep null: console / tests.
        }

        return $this->annotateTree(
            Plugin::getInstance()->nodes->getTree((int) $menu->id, $siteId),
            $uri
        );
    }
}
