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

    public function testNewTypesRegistered(): void
    {
        self::assertContains(Node::TYPE_ANCHOR, Node::TYPES);
        self::assertContains(Node::TYPE_SEPARATOR, Node::TYPES);
        self::assertSame('anchor', Node::TYPE_ANCHOR);
        self::assertSame('separator', Node::TYPE_SEPARATOR);
    }

    public function testAnchorAndSeparatorUrls(): void
    {
        $anchor = $this->node(Node::TYPE_ANCHOR, ['url' => 'pricing']);
        self::assertSame('#pricing', $anchor->getNodeUrl());
        self::assertTrue($anchor->hasUrl());

        $anchor->url = '#already';
        self::assertSame('#already', $anchor->getNodeUrl());

        $separator = $this->node(Node::TYPE_SEPARATOR, ['url' => '/ignored']);
        self::assertNull($separator->getNodeUrl());
        self::assertFalse($separator->hasUrl());
        self::assertTrue($separator->isSeparator());

        self::assertNull($this->node(Node::TYPE_PASSIVE)->getNodeUrl());
        self::assertNull($this->node(Node::TYPE_DYNAMIC)->getNodeUrl());
    }

    public function testFallbackStrategies(): void
    {
        // Element-linked node whose element can't be found (no elementId).
        $unlink = $this->node(Node::TYPE_ENTRY, ['data' => '{"meta":{"fallback":"unlink"}}']);
        self::assertNull($unlink->getNodeUrl());
        self::assertTrue($unlink->isAvailable());

        $hide = $this->node(Node::TYPE_ENTRY, ['data' => '{"meta":{"fallback":"hide"}}']);
        self::assertNull($hide->getNodeUrl());
        self::assertFalse($hide->isAvailable());

        $url = $this->node(Node::TYPE_ENTRY, ['data' => '{"meta":{"fallback":"url","fallbackUrl":"/gone"}}']);
        self::assertSame('/gone', $url->getNodeUrl());
        self::assertTrue($url->isAvailable());

        // Custom links are never "unavailable".
        $custom = $this->node(Node::TYPE_CUSTOM, ['url' => '/x', 'data' => '{"meta":{"fallback":"hide"}}']);
        self::assertTrue($custom->isAvailable());
        self::assertSame('/x', $custom->getNodeUrl());
    }

    public function testClickableMeta(): void
    {
        $node = $this->node(Node::TYPE_CUSTOM, ['url' => '/x']);
        self::assertTrue($node->isClickable());

        $node->data = '{"meta":{"clickable":false}}';
        self::assertFalse($node->isClickable());
    }

    /**
     * @param array<string, mixed> $props
     */
    private function node(string $type, array $props = []): Node
    {
        $node = $this->getMockBuilder(Node::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $node->type = $type;
        foreach ($props as $key => $value) {
            $node->{$key} = $value;
        }

        return $node;
    }
}
