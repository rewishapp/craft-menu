<?php

namespace rewish\craft\menu\tests;

use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use PHPUnit\Framework\TestCase;
use rewish\craft\menu\elements\Node;

/**
 * @coversNothing
 */
final class NodeTypeTest extends TestCase
{
    public function testElementClassForType(): void
    {
        self::assertSame(Entry::class, Node::elementClassForType(Node::TYPE_ENTRY));
        self::assertSame(Category::class, Node::elementClassForType(Node::TYPE_CATEGORY));
        self::assertSame(Asset::class, Node::elementClassForType(Node::TYPE_ASSET));
        self::assertNull(Node::elementClassForType(Node::TYPE_CUSTOM));
    }
}
