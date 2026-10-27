<?php

namespace rewish\craft\menu\gql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use rewish\craft\menu\elements\Menu;

class MenuType
{
    public static function getName(): string
    {
        return 'MenuMenu';
    }

    public static function getType(): ObjectType
    {
        return GqlEntityRegistry::getOrCreate(self::getName(), static fn () => new ObjectType([
            'name' => self::getName(),
            'fields' => static fn () => [
                'id' => [
                    'type' => Type::id(),
                    'resolve' => static fn (Menu $menu) => $menu->id,
                ],
                'title' => [
                    'type' => Type::string(),
                    'resolve' => static fn (Menu $menu) => $menu->title,
                ],
                'handle' => [
                    'type' => Type::string(),
                    'resolve' => static fn (Menu $menu) => $menu->handle,
                ],
                'maxLevels' => [
                    'type' => Type::int(),
                    'resolve' => static fn (Menu $menu) => $menu->maxLevels,
                ],
            ],
        ]));
    }
}
