<?php

namespace rewish\craft\menu\tests;

use PHPUnit\Framework\TestCase;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\services\Migrators;

/**
 * @coversNothing
 */
final class MigratorTypeMapTest extends TestCase
{
    /**
     * @dataProvider typeProvider
     */
    public function testMapNodeType(string $stored, ?string $expected): void
    {
        $migrators = new Migrators();
        $method = new \ReflectionMethod(Migrators::class, 'mapNodeType');
        $method->setAccessible(true);

        self::assertSame($expected, $method->invoke($migrators, $stored));
    }

    /**
     * @return list<array{0: string, 1: null|string}>
     */
    public static function typeProvider(): array
    {
        return [
            ['verbb\navigation\nodetypes\Custom', Node::TYPE_CUSTOM],
            ['verbb\navigation\nodetypes\Entry', Node::TYPE_ENTRY],
            ['verbb\navigation\nodetypes\Category', Node::TYPE_CATEGORY],
            ['verbb\navigation\nodetypes\Asset', Node::TYPE_ASSET],
            ['verbb\navigation\nodetypes\Passive', Node::TYPE_PASSIVE],
            ['verbb\navigation\nodetypes\Site', Node::TYPE_SITE],
            ['craft\elements\Entry', Node::TYPE_ENTRY],
            ['verbb\navigation\nodetypes\Dynamic', Node::TYPE_DYNAMIC],
            // Product maps only when Commerce is installed; otherwise null.
            ['verbb\navigation\nodetypes\Product', Node::commerceInstalled() ? Node::TYPE_PRODUCT : null],
            ['', Node::TYPE_CUSTOM],
        ];
    }
}
