<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use craft\helpers\Json;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\Plugin;

class ImportExport extends Component
{
    /**
     * @return array{handle: string, title: string, maxLevels: null|int, nodes: list<array>}
     */
    public function exportMenu(Menu $menu): array
    {
        $tree = Plugin::getInstance()->nodes->getTree((int) $menu->id);

        return [
            'handle' => (string) $menu->handle,
            'title' => (string) $menu->title,
            'maxLevels' => $menu->maxLevels,
            'instructions' => $menu->instructions,
            'nodes' => $this->exportBranch($tree),
        ];
    }

    /**
     * @return array{ok: bool, menu: ?Menu, message: string}
     */
    public function importMenu(array $payload, bool $replace = false): array
    {
        $handle = trim((string) ($payload['handle'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        if ($handle === '' || $title === '') {
            return ['ok' => false, 'menu' => null, 'message' => 'JSON needs handle and title.'];
        }

        $existing = Plugin::getInstance()->menus->getByHandle($handle);
        if ($existing && !$replace) {
            return ['ok' => false, 'menu' => $existing, 'message' => "Menu “{$handle}” already exists. Enable replace to overwrite nodes."];
        }

        if ($existing && $replace) {
            foreach (Plugin::getInstance()->nodes->getForMenu((int) $existing->id) as $node) {
                \Craft::$app->getElements()->deleteElement($node, true);
            }
            $menu = $existing;
            $menu->title = $title;
            $menu->instructions = $payload['instructions'] ?? $menu->instructions;
            if (isset($payload['maxLevels'])) {
                $menu->maxLevels = (int) $payload['maxLevels'] ?: null;
            }
        } else {
            $menu = new Menu();
            $menu->title = $title;
            $menu->handle = $handle;
            $menu->instructions = $payload['instructions'] ?? null;
            $menu->maxLevels = isset($payload['maxLevels']) ? ((int) $payload['maxLevels'] ?: null) : Plugin::settings()->defaultMaxLevels;
        }

        if (!\Craft::$app->getElements()->saveElement($menu)) {
            return ['ok' => false, 'menu' => $menu, 'message' => 'Could not save menu: '.Json::encode($menu->getErrors())];
        }

        $this->importBranch((int) $menu->id, $payload['nodes'] ?? [], null);

        return ['ok' => true, 'menu' => $menu, 'message' => "Imported menu “{$handle}”."];
    }

    /**
     * @param list<array{node: Node, children: list}> $branch
     *
     * @return list<array>
     */
    private function exportBranch(array $branch): array
    {
        $out = [];
        foreach ($branch as $item) {
            $node = $item['node'] ?? null;
            // Dynamic sources inject ProjectedNode children into Twig trees — skip those for JSON export.
            if (!$node instanceof Node) {
                continue;
            }
            $out[] = [
                'title' => $node->title,
                'type' => $node->type,
                'url' => $node->url,
                'elementId' => $node->elementId,
                'classes' => $node->classes,
                'urlSuffix' => $node->urlSuffix,
                'newWindow' => $node->newWindow,
                'followElement' => $node->followElement,
                'enabled' => (bool) $node->enabled,
                'customAttributes' => $node->getCustomAttributesArray(),
                'data' => $node->getDataArray() ?: null,
                'children' => $this->exportBranch($item['children'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * @param list<array> $nodes
     */
    private function importBranch(int $menuId, array $nodes, ?int $parentId): void
    {
        foreach ($nodes as $row) {
            if (!\is_array($row)) {
                continue;
            }
            $node = new Node(['menuId' => $menuId]);
            $node->title = (string) ($row['title'] ?? 'Untitled');
            $node->type = (string) ($row['type'] ?? Node::TYPE_CUSTOM);
            $node->url = $row['url'] ?? null;
            $node->elementId = isset($row['elementId']) && is_numeric($row['elementId']) ? (int) $row['elementId'] : null;
            $node->classes = $row['classes'] ?? null;
            $node->urlSuffix = $row['urlSuffix'] ?? null;
            $node->newWindow = (bool) ($row['newWindow'] ?? false);
            $node->followElement = (bool) ($row['followElement'] ?? true);
            $node->enabled = (bool) ($row['enabled'] ?? true);
            $attrs = $row['customAttributes'] ?? null;
            $node->customAttributes = \is_array($attrs) ? Json::encode($attrs) : ($attrs ?: null);
            $data = $row['data'] ?? null;
            if (\is_array($data)) {
                $node->setDataArray($data);
            } elseif (\is_string($data) && $data !== '') {
                $node->data = $data;
            }
            $node->newParentId = $parentId;

            if (!\Craft::$app->getElements()->saveElement($node)) {
                continue;
            }

            $children = $row['children'] ?? [];
            if (\is_array($children) && $children !== []) {
                $this->importBranch($menuId, $children, (int) $node->id);
            }
        }
    }
}
