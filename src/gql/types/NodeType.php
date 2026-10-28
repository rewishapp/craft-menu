<?php

namespace rewish\craft\menu\gql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\helpers\NodeMeta;
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
                    'htmlId' => self::metaField(Type::string(), 'htmlId'),
                    'ariaLabel' => self::metaField(Type::string(), 'ariaLabel'),
                    'titleAttr' => self::metaField(Type::string(), 'titleAttr'),
                    'badgeText' => self::metaField(Type::string(), 'badgeText'),
                    'badgeStyle' => self::metaField(Type::string(), 'badgeStyle'),
                    'iconClass' => self::metaField(Type::string(), 'iconClass'),
                    'description' => self::metaField(Type::string(), 'description'),
                    'rel' => self::metaField(Type::string(), 'rel'),
                    'featured' => self::metaField(Type::boolean(), 'featured'),
                    'megaEnabled' => self::metaField(Type::boolean(), 'megaEnabled'),
                    'megaColumns' => self::metaField(Type::int(), 'megaColumns'),
                    'clickable' => self::metaField(Type::boolean(), 'clickable'),
                    'separator' => [
                        'type' => Type::boolean(),
                        'resolve' => static fn (object $node) => ($node->type ?? null) === Node::TYPE_SEPARATOR,
                    ],
                    'activeAncestor' => [
                        'type' => Type::boolean(),
                        'args' => [
                            'uri' => Type::string(),
                        ],
                        'description' => 'True when a descendant (but not necessarily this node) matches the URI.',
                        'resolve' => static function (object $node, array $args) {
                            return self::hasActiveDescendant(self::childrenOf($node), $args['uri'] ?? null);
                        },
                    ],
                    'children' => [
                        'type' => Type::listOf(self::getType()),
                        'resolve' => static fn (object $node) => self::childrenOf($node),
                    ],
                ];
            },
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private static function metaField(Type $type, string $key): array
    {
        return [
            'type' => $type,
            'resolve' => static fn (object $node) => NodeMeta::forObject($node)[$key] ?? null,
        ];
    }

    /**
     * Children after visibility filtering when the node came from a resolved tree; otherwise structure + projected.
     *
     * @return list<object>
     */
    private static function childrenOf(object $node): array
    {
        if (!$node instanceof Node) {
            return [];
        }

        $resolved = $node->getTreeChildren();
        if ($resolved !== null) {
            return $resolved;
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
    }

    /**
     * @param list<object> $children
     */
    private static function hasActiveDescendant(array $children, ?string $uri): bool
    {
        $matcher = Plugin::getInstance()?->activeMatcher;
        foreach ($children as $child) {
            if ($matcher?->isActive($child, $uri) || self::hasActiveDescendant(self::childrenOf($child), $uri)) {
                return true;
            }
        }

        return false;
    }
}
