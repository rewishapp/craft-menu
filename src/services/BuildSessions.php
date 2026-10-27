<?php

namespace rewish\craft\menu\services;

use craft\base\Component;
use craft\db\Query;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\Plugin;

class BuildSessions extends Component
{
    /**
     * @return array{id: int, moves: int, snapshot: list<array>}
     */
    public function getOrCreate(int $menuId, int $siteId, int $userId): array
    {
        $row = $this->findRow($menuId, $siteId, $userId);
        if ($row) {
            $moves = Json::decodeIfJson($row['moves'] ?? '[]') ?: [];

            return [
                'id' => (int) $row['id'],
                'moves' => \is_array($moves) ? \count($moves) : 0,
                'snapshot' => Json::decodeIfJson($row['snapshot'] ?? '[]') ?: [],
            ];
        }

        $snapshot = $this->captureSnapshot($menuId, $siteId);
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        \Craft::$app->getDb()->createCommand()
            ->insert('{{%menu_build_sessions}}', [
                'menuId' => $menuId,
                'siteId' => $siteId,
                'userId' => $userId,
                'snapshot' => Json::encode($snapshot),
                'moves' => Json::encode([]),
                'dateCreated' => $now,
                'dateUpdated' => $now,
                'uid' => StringHelper::UUID(),
            ])
            ->execute()
        ;

        $id = (int) \Craft::$app->getDb()->getLastInsertID();

        return ['id' => $id, 'moves' => 0, 'snapshot' => $snapshot];
    }

    public function recordMove(int $menuId, int $siteId, int $userId, array $move): void
    {
        $session = $this->getOrCreate($menuId, $siteId, $userId);
        $row = $this->findRow($menuId, $siteId, $userId);
        $moves = Json::decodeIfJson($row['moves'] ?? '[]') ?: [];
        if (!\is_array($moves)) {
            $moves = [];
        }
        $moves[] = $move;
        \Craft::$app->getDb()->createCommand()
            ->update('{{%menu_build_sessions}}', [
                'moves' => Json::encode($moves),
                'dateUpdated' => (new \DateTime())->format('Y-m-d H:i:s'),
            ], ['id' => $session['id']])
            ->execute()
        ;
    }

    public function discard(int $menuId, int $siteId, int $userId): bool
    {
        $row = $this->findRow($menuId, $siteId, $userId);
        if (!$row) {
            return false;
        }
        $snapshot = Json::decodeIfJson($row['snapshot'] ?? '[]') ?: [];
        if (!\is_array($snapshot) || $snapshot === []) {
            $this->clear($menuId, $siteId, $userId);

            return true;
        }

        $menu = Plugin::getInstance()?->menus->getById($menuId);
        if (!$menu?->structureId) {
            return false;
        }

        $structures = \Craft::$app->getStructures();
        $structureId = (int) $menu->structureId;
        $lastByParent = [];
        foreach ($snapshot as $item) {
            if (!\is_array($item) || empty($item['id'])) {
                continue;
            }
            $node = Node::find()->id((int) $item['id'])->menuId($menuId)->status(null)->siteId($siteId)->one()
                ?? Node::find()->id((int) $item['id'])->menuId($menuId)->status(null)->one();
            if (!$node) {
                continue;
            }
            $parentId = isset($item['parentId']) && $item['parentId'] ? (int) $item['parentId'] : 0;
            $prevId = $lastByParent[$parentId] ?? null;
            if ($prevId) {
                $prev = Node::find()->id($prevId)->menuId($menuId)->status(null)->one();
                if ($prev) {
                    $structures->moveAfter($structureId, $node, $prev);
                    $lastByParent[$parentId] = (int) $node->id;

                    continue;
                }
            }
            if ($parentId) {
                $parent = Node::find()->id($parentId)->menuId($menuId)->status(null)->one();
                if ($parent) {
                    $structures->prepend($structureId, $node, $parent);
                } else {
                    $structures->prependToRoot($structureId, $node);
                }
            } else {
                $structures->prependToRoot($structureId, $node);
            }
            $lastByParent[$parentId] = (int) $node->id;
        }

        $this->clear($menuId, $siteId, $userId);
        // Start a fresh session snapshot after restore.
        $this->getOrCreate($menuId, $siteId, $userId);

        return true;
    }

    public function clear(int $menuId, int $siteId, int $userId): void
    {
        \Craft::$app->getDb()->createCommand()
            ->delete('{{%menu_build_sessions}}', [
                'menuId' => $menuId,
                'siteId' => $siteId,
                'userId' => $userId,
            ])
            ->execute()
        ;
    }

    /**
     * @return list<array{id: int, parentId: null|int}>
     */
    public function captureSnapshot(int $menuId, int $siteId): array
    {
        $out = [];
        foreach (Plugin::getInstance()->nodes->getForMenu($menuId, $siteId) as $node) {
            $out[] = [
                'id' => (int) $node->id,
                'parentId' => $node->getParent()?->id ? (int) $node->getParent()->id : null,
            ];
        }

        return $out;
    }

    /**
     * @return null|array<string, mixed>
     */
    private function findRow(int $menuId, int $siteId, int $userId): ?array
    {
        if (!\Craft::$app->getDb()->tableExists('{{%menu_build_sessions}}')) {
            return null;
        }

        $row = (new Query())
            ->from('{{%menu_build_sessions}}')
            ->where([
                'menuId' => $menuId,
                'siteId' => $siteId,
                'userId' => $userId,
            ])
            ->one()
        ;

        return $row ?: null;
    }
}
