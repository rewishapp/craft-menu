<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use craft\db\Query;
use craft\helpers\Json;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\Plugin;

class Migrators extends Component
{
    /**
     * @return array{available: bool, plugin: bool, menus: int, nodes: int, message: string}
     */
    public function detectVerbbNavigation(): array
    {
        $plugin = \Craft::$app->getPlugins()->getPlugin('navigation');
        $hasPlugin = $plugin !== null;
        $menus = 0;
        $nodes = 0;
        $db = \Craft::$app->getDb();

        try {
            if ($db->tableExists('{{%navigation_menus}}')) {
                $q = (new Query())->from('{{%navigation_menus}}');
                if ($this->sourceHasColumn('{{%navigation_menus}}', 'dateDeleted')) {
                    $q->where(['dateDeleted' => null]);
                }
                $menus = (int) $q->count();
            }
            if ($db->tableExists('{{%navigation_nodes}}')) {
                $nodes = (int) (new Query())->from('{{%navigation_nodes}}')->count();
            }
        } catch (\Throwable $e) {
            return [
                'available' => false,
                'plugin' => $hasPlugin,
                'menus' => 0,
                'nodes' => 0,
                'message' => 'Could not read source tables: '.$e->getMessage(),
            ];
        }

        $available = $menus > 0 || $nodes > 0 || $hasPlugin;

        return [
            'available' => $available,
            'plugin' => $hasPlugin,
            'menus' => $menus,
            'nodes' => $nodes,
            'message' => $available
                ? \sprintf('Migrate from Verbb: %d menu(s), %d node(s)%s.', $menus, $nodes, $hasPlugin ? '' : ' (plugin not installed)')
                : 'Migrate from Verbb: nothing found on this install.',
        ];
    }

    /**
     * @return array{ok: bool, dryRun: bool, lines: list<string>}
     */
    public function migrateFromVerbbNavigation(bool $dryRun = true): array
    {
        $detect = $this->detectVerbbNavigation();
        if (!$detect['available'] || $detect['menus'] < 1) {
            return ['ok' => false, 'dryRun' => $dryRun, 'lines' => [$detect['message']]];
        }

        $db = \Craft::$app->getDb();
        if (!$db->tableExists('{{%navigation_menus}}') || !$db->tableExists('{{%navigation_nodes}}')) {
            return ['ok' => false, 'dryRun' => $dryRun, 'lines' => ['Source tables navigation_menus / navigation_nodes are missing.']];
        }

        $menuQuery = (new Query())
            ->select(['id', 'name', 'handle', 'instructions', 'structureId', 'sortOrder'])
            ->from('{{%navigation_menus}}')
            ->orderBy(['sortOrder' => \SORT_ASC, 'name' => \SORT_ASC])
        ;
        if ($this->sourceHasColumn('{{%navigation_menus}}', 'dateDeleted')) {
            $menuQuery->where(['dateDeleted' => null]);
        }
        $sourceMenus = $menuQuery->all();

        $lines = [$detect['message']];
        $createdMenus = 0;
        $createdNodes = 0;
        $skipped = 0;

        foreach ($sourceMenus as $row) {
            $handle = (string) $row['handle'];
            $title = (string) ($row['name'] ?: $handle);
            $existing = Plugin::getInstance()->menus->getByHandle($handle, true);

            if ($existing) {
                $lines[] = "Skip menu “{$handle}” — already exists in Menu.";
                ++$skipped;

                continue;
            }

            $nodeRows = $this->fetchSourceNodes((int) $row['id'], $row['structureId'] ? (int) $row['structureId'] : null);
            $lines[] = ($dryRun ? 'Would import' : 'Importing')." menu “{$handle}” ({$title}) with ".\count($nodeRows).' node(s).';

            if ($dryRun) {
                ++$createdMenus;
                $createdNodes += \count($nodeRows);

                continue;
            }

            $menu = new Menu();
            $menu->title = $title;
            $menu->handle = $handle;
            $menu->instructions = $row['instructions'] ?? null;
            $menu->sortOrder = isset($row['sortOrder']) ? (int) $row['sortOrder'] : null;
            $menu->maxLevels = Plugin::settings()->defaultMaxLevels;

            if (!\Craft::$app->getElements()->saveElement($menu)) {
                $lines[] = "Failed to save menu “{$handle}”: ".Json::encode($menu->getErrors());
                ++$skipped;

                continue;
            }

            ++$createdMenus;
            $idMap = [];
            foreach ($nodeRows as $nodeRow) {
                $mapped = $this->mapSourceNode($nodeRow);
                if ($mapped === null) {
                    $lines[] = '  Skipped unsupported node type: '.(string) ($nodeRow['type'] ?? '?');
                    ++$skipped;

                    continue;
                }

                $node = new Node(['menuId' => (int) $menu->id]);
                $node->title = $mapped['title'];
                $node->type = $mapped['type'];
                $node->url = $mapped['url'];
                $node->elementId = $mapped['elementId'];
                $node->classes = $mapped['classes'];
                $node->urlSuffix = $mapped['urlSuffix'];
                $node->newWindow = $mapped['newWindow'];
                $node->followElement = $mapped['followElement'];
                $node->customAttributes = $mapped['customAttributes'];
                $node->data = $mapped['data'] ?? null;
                $node->enabled = $mapped['enabled'];
                $node->siteId = $mapped['siteId'];

                $parentSourceId = $mapped['parentSourceId'];
                $node->newParentId = $parentSourceId && isset($idMap[$parentSourceId])
                    ? $idMap[$parentSourceId]
                    : null;

                if (!\Craft::$app->getElements()->saveElement($node)) {
                    $lines[] = '  Failed node “'.$mapped['title'].'”: '.Json::encode($node->getErrors());
                    ++$skipped;

                    continue;
                }

                $idMap[(int) $nodeRow['id']] = (int) $node->id;
                ++$createdNodes;
            }
        }

        $lines[] = $dryRun
            ? \sprintf('Dry run: would create %d menu(s), %d node(s)%s.', $createdMenus, $createdNodes, $skipped ? ", {$skipped} skipped" : '')
            : \sprintf('Done: created %d menu(s), %d node(s)%s.', $createdMenus, $createdNodes, $skipped ? ", {$skipped} skipped" : '');

        return ['ok' => true, 'dryRun' => $dryRun, 'lines' => $lines];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSourceNodes(int $menuId, ?int $structureId): array
    {
        $select = [
            'n.id',
            'n.menuId',
            'n.elementId',
            'n.type',
            'n.classes',
            'n.customAttributes',
            'n.newWindow',
            'es.title',
            'es.siteId',
            'es.enabled',
        ];
        if ($this->sourceHasColumn('{{%navigation_nodes}}', 'parentId')) {
            $select[] = 'n.parentId';
        }
        if ($this->sourceHasColumn('{{%navigation_nodes}}', 'url')) {
            $select[] = 'n.url';
        }
        if ($this->sourceHasColumn('{{%navigation_nodes}}', 'urlSuffix')) {
            $select[] = 'n.urlSuffix';
        }
        if ($this->sourceHasColumn('{{%navigation_nodes}}', 'data')) {
            $select[] = 'n.data';
        }

        $query = (new Query())
            ->select($select)
            ->from(['n' => '{{%navigation_nodes}}'])
            ->leftJoin(['es' => '{{%elements_sites}}'], '[[es.elementId]] = [[n.id]]')
            ->leftJoin(['e' => '{{%elements}}'], '[[e.id]] = [[n.id]]')
            ->where(['n.menuId' => $menuId])
            ->andWhere(['e.dateDeleted' => null])
        ;

        if ($structureId) {
            $query
                ->addSelect(['se.lft', 'parent.elementId AS structureParentId'])
                ->leftJoin(
                    ['se' => '{{%structureelements}}'],
                    '[[se.elementId]] = [[n.id]] AND [[se.structureId]] = :structureId',
                    [':structureId' => $structureId]
                )
                ->leftJoin(
                    ['parent' => '{{%structureelements}}'],
                    '[[parent.structureId]] = [[se.structureId]] AND [[parent.lft]] < [[se.lft]] AND [[parent.rgt]] > [[se.rgt]] AND [[parent.level]] = [[se.level]] - 1'
                )
                ->orderBy(['se.lft' => \SORT_ASC, 'n.id' => \SORT_ASC])
            ;
        } else {
            $query->orderBy(['n.id' => \SORT_ASC]);
        }

        // One row per node (primary / first site). Prefer site-specific URL when present.
        $hasNodeSites = \Craft::$app->getDb()->tableExists('{{%navigation_nodes_sites}}');
        $seen = [];
        $rows = [];
        foreach ($query->all() as $row) {
            $id = (int) $row['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            if ($hasNodeSites && empty($row['url'])) {
                $siteUrl = (new Query())
                    ->select(['url', 'urlSuffix'])
                    ->from('{{%navigation_nodes_sites}}')
                    ->where(['nodeId' => $id])
                    ->andWhere(['not', ['url' => null]])
                    ->one()
                ;
                if ($siteUrl) {
                    $row['url'] = $siteUrl['url'];
                    if (empty($row['urlSuffix']) && !empty($siteUrl['urlSuffix'])) {
                        $row['urlSuffix'] = $siteUrl['urlSuffix'];
                    }
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function sourceHasColumn(string $table, string $column): bool
    {
        $schema = \Craft::$app->getDb()->getTableSchema($table);

        return $schema !== null && isset($schema->columns[$column]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return null|array{
     *     title: string,
     *     type: string,
     *     url: ?string,
     *     elementId: ?int,
     *     classes: ?string,
     *     urlSuffix: ?string,
     *     newWindow: bool,
     *     followElement: bool,
     *     customAttributes: ?string,
     *     enabled: bool,
     *     siteId: int,
     *     parentSourceId: ?int
     * }
     */
    private function mapSourceNode(array $row): ?array
    {
        $type = $this->mapNodeType((string) ($row['type'] ?? ''));
        if ($type === null) {
            return null;
        }

        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            $title = 'Untitled';
        }

        $parentSourceId = null;
        if (!empty($row['structureParentId'])) {
            $parentSourceId = (int) $row['structureParentId'];
        } elseif (!empty($row['parentId'])) {
            $parentSourceId = (int) $row['parentId'];
        }

        $url = $row['url'] ?? null;
        $elementId = isset($row['elementId']) && is_numeric($row['elementId']) ? (int) $row['elementId'] : null;

        $dataJson = null;
        if ($type === Node::TYPE_DYNAMIC) {
            $elementId = null;
            $url = null;
            $dataJson = \is_string($row['data'] ?? null) ? $row['data'] : (isset($row['data']) ? Json::encode($row['data']) : null);
        } elseif ($type === Node::TYPE_PASSIVE) {
            $elementId = null;
            $url = null;
        } elseif ($type === Node::TYPE_SITE) {
            $url = null;
            $siteId = $this->extractLinkedSiteId($row);
            if ($siteId) {
                $elementId = $siteId;
            } elseif (!$elementId) {
                $type = Node::TYPE_CUSTOM;
                $url = '#';
                $elementId = null;
            }
        } elseif ($type === Node::TYPE_CUSTOM) {
            $elementId = null;
            if ($url === null || $url === '') {
                $url = '#';
            }
        } else {
            $url = null;
            if (!$elementId) {
                // Element link without target — degrade to custom placeholder.
                $type = Node::TYPE_CUSTOM;
                $url = '#';
                $elementId = null;
            }
        }

        return [
            'title' => $title,
            'type' => $type,
            'url' => $url,
            'elementId' => $elementId,
            'classes' => $row['classes'] ?? null,
            'urlSuffix' => $row['urlSuffix'] ?? null,
            'newWindow' => (bool) ($row['newWindow'] ?? false),
            'followElement' => \in_array($type, [Node::TYPE_ENTRY, Node::TYPE_CATEGORY, Node::TYPE_ASSET, Node::TYPE_PRODUCT], true),
            'customAttributes' => $this->normalizeCustomAttributes($row['customAttributes'] ?? null),
            'data' => $dataJson,
            'enabled' => (bool) ($row['enabled'] ?? true),
            'siteId' => (int) ($row['siteId'] ?: \Craft::$app->getSites()->getPrimarySite()->id),
            'parentSourceId' => $parentSourceId,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractLinkedSiteId(array $row): ?int
    {
        $data = $row['data'] ?? null;
        if (\is_string($data) && $data !== '') {
            $decoded = Json::decodeIfJson($data);
            if (\is_array($decoded) && isset($decoded['siteId']) && is_numeric($decoded['siteId'])) {
                return (int) $decoded['siteId'];
            }
        }
        if (\is_array($data) && isset($data['siteId']) && is_numeric($data['siteId'])) {
            return (int) $data['siteId'];
        }

        return null;
    }

    private function mapNodeType(string $stored): ?string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return Node::TYPE_CUSTOM;
        }

        $lower = strtolower($stored);
        if (str_contains($lower, 'nodetypes\passive') || str_contains($lower, 'nodetypes\group') || $lower === 'passive') {
            return Node::TYPE_PASSIVE;
        }
        if (str_contains($lower, 'nodetypes\site') || $lower === 'site') {
            return Node::TYPE_SITE;
        }
        if (str_contains($lower, 'nodetypes\custom') || $lower === 'custom') {
            return Node::TYPE_CUSTOM;
        }
        if (str_contains($lower, 'nodetypes\entry') || str_ends_with($lower, '\entry') || $lower === 'entry') {
            return Node::TYPE_ENTRY;
        }
        if (str_contains($lower, 'nodetypes\category') || str_ends_with($lower, '\category') || $lower === 'category') {
            return Node::TYPE_CATEGORY;
        }
        if (str_contains($lower, 'nodetypes\asset') || str_ends_with($lower, '\asset') || $lower === 'asset') {
            return Node::TYPE_ASSET;
        }
        if (str_contains($lower, 'nodetypes\dynamic') || $lower === 'dynamic') {
            return Node::TYPE_DYNAMIC;
        }
        if (
            str_contains($lower, 'nodetypes\product')
            || str_contains($lower, 'commerce\elements\product')
            || $lower === 'product'
        ) {
            return Node::commerceInstalled() ? Node::TYPE_PRODUCT : null;
        }

        // Legacy FQCN element types.
        if (str_contains($lower, 'elements\entry')) {
            return Node::TYPE_ENTRY;
        }
        if (str_contains($lower, 'elements\category')) {
            return Node::TYPE_CATEGORY;
        }
        if (str_contains($lower, 'elements\asset')) {
            return Node::TYPE_ASSET;
        }

        return Node::TYPE_CUSTOM;
    }

    private function normalizeCustomAttributes(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (\is_string($raw)) {
            $decoded = Json::decodeIfJson($raw);
        } else {
            $decoded = $raw;
        }
        if (!\is_array($decoded)) {
            return \is_string($raw) ? $raw : null;
        }

        // List of {attribute, value} → associative object.
        if ($decoded !== [] && array_is_list($decoded)) {
            $out = [];
            foreach ($decoded as $item) {
                if (!\is_array($item)) {
                    continue;
                }
                $key = $item['attribute'] ?? $item['name'] ?? null;
                if (\is_string($key) && $key !== '') {
                    $out[$key] = $item['value'] ?? '';
                }
            }

            return $out === [] ? null : Json::encode($out);
        }

        return Json::encode($decoded);
    }
}
