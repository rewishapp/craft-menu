<?php

namespace rewish\craft\menu\gql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\models\ProjectedNode;
use rewish\craft\menu\Plugin;

class NodeType
{
    public static function getName(): string
    {
        return 'MenuNode';
    }

    public static function getType(): ObjectType
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), static fn () => new ObjectType([
            'name' => self::getName(),
            'fields' => static function () {
                return [
                    'id' => [
                        'type' => Type::id(),
                        'resolve' => static function (object $node) {
                            if ($node instanceof Node) {
                                return $node->id;
                            }
                            if ($node instanceof ProjectedNode) {
                                return $node->elementId;
                            }

                            return null;
                        },
                    ],
                    'title' => [
                        'type' => Type::string(),
                        'resolve' => static fn (object $node) => $node->title ?? null,
                    ],
                    'type' => [
                        'type' => Type::string(),
                        'resolve' => static fn (object $node) => $node->type ?? null,
                    ],
                    'url' => [
                        'type' => Type::string(),
                        'resolve' => static function (object $node) {
                            if ($node instanceof Node || $node instanceof ProjectedNode) {
                                return $node->getNodeUrl();
                            }

                            return null;
                        },
                    ],
                    'newWindow' => [
                        'type' => Type::boolean(),
                        'resolve' => static fn (object $node) => (bool) ($node->newWindow ?? false),
                    ],
                    'classes' => [
                        'type' => Type::string(),
                        'resolve' => static fn (object $node) => $node->classes ?? null,
                    ],
                    'level' => [
                        'type' => Type::int(),
                        'resolve' => static fn (object $node) => $node instanceof Node ? $node->level : 0,
                    ],
                    'elementId' => [
                        'type' => Type::id(),
                        'resolve' => static fn (object $node) => $node->elementId ?? null,
                    ],
                    'projected' => [
                        'type' => Type::boolean(),
                        'resolve' => static fn (object $node) => $node instanceof ProjectedNode,
                    ],
                    'active' => [
                        'type' => Type::boolean(),
                        'args' => [
                            'uri' => Type::string(),
                        ],
                        'resolve' => static function (object $node, array $args) {
                            return Plugin::getInstance()?->activeMatcher->isActive($node, $args['uri'] ?? null) ?? false;
                        },
                    ],
                    'current' => [
                        'type' => Type::boolean(),
                        'args' => [
                            'uri' => Type::string(),
                        ],
                        'resolve' => static function (object $node, array $args) {
                            return Plugin::getInstance()?->activeMatcher->isCurrent($node, $args['uri'] ?? null) ?? false;
                        },
                    ],
                    'children' => [
                        'type' => Type::listOf(self::getType()),
                        'resolve' => static function (object $node) {
                            if (!$node instanceof Node) {
                                return [];
                            }
                            $children = array_values(array_filter(
                                $node->getChildren()->all(),
                                static fn ($child) => $child instanceof Node
                            ));
                            if ($node->type === Node::TYPE_DYNAMIC) {
                                foreach (Plugin::getInstance()?->dynamicSources->project($node) ?? [] as $projected) {
                                    $children[] = $projected;
                                }
                            }

                            return $children;
                        },
                    ],
                ];
            },
        ]));
    }
}
