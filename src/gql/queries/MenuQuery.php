<?php

namespace rewish\craft\menu\gql\queries;

use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\gql\types\MenuType;
use rewish\craft\menu\gql\types\NodeType;
use rewish\craft\menu\Plugin;
use yii\base\InvalidConfigException;

class MenuQuery extends Query
{
    public static function getQueries(bool $checkToken = true): array
    {
        return [
            'menu' => [
                'type' => MenuType::getType(),
                'args' => [
                    'handle' => Type::nonNull(Type::string()),
                ],
                'resolve' => static function ($source, array $arguments) {
                    return Plugin::getInstance()?->menus->getByHandle($arguments['handle']);
                },
                'description' => 'Return one Menu by handle.',
            ],
            'menus' => [
                'type' => Type::listOf(MenuType::getType()),
                'resolve' => static function () {
                    return Plugin::getInstance()?->menus->getAll(null, true) ?? [];
                },
                'description' => 'Return all menus.',
            ],
            'menuNodes' => [
                'type' => Type::listOf(NodeType::getType()),
                'args' => [
                    'handle' => Type::nonNull(Type::string()),
                    'level' => Type::int(),
                ],
                'resolve' => static function ($source, array $arguments) {
                    $menu = Plugin::getInstance()?->menus->getByHandle($arguments['handle']);
                    if (!$menu instanceof Menu || !$menu->id) {
                        return [];
                    }

                    $nodesService = Plugin::getInstance()->nodes;
                    $siteId = (int) \Craft::$app->getSites()->getCurrentSite()->id;
                    $tree = $nodesService->getTree((int) $menu->id, $siteId);
                    $nodesService->attachTreeChildren($tree);
                    $nodes = $nodesService->flatten($tree);
                    if (isset($arguments['level'])) {
                        $level = (int) $arguments['level'];
                        $nodes = array_values(array_filter(
                            $nodes,
                            static fn ($node) => (int) $node->level === $level
                        ));
                    }

                    return $nodes;
                },
                'description' => 'Nodes for a menu handle (structure order). Pass level: 1 for roots; use children for nesting.',
            ],
            'menuBreadcrumbs' => [
                'type' => Type::listOf(NodeType::getType()),
                'args' => [
                    'handle' => Type::nonNull(Type::string()),
                    'uri' => Type::string(),
                ],
                'resolve' => static function ($source, array $arguments) {
                    $uri = $arguments['uri'] ?? null;
                    if (!\is_string($uri) || $uri === '') {
                        try {
                            $uri = \Craft::$app->getRequest()->getPathInfo();
                        } catch (InvalidConfigException) {
                            return [];
                        }
                    }

                    return Plugin::getInstance()?->nodes->breadcrumbs($arguments['handle'], $uri) ?? [];
                },
                'description' => 'Breadcrumb nodes for a menu handle and URI.',
            ],
        ];
    }
}
