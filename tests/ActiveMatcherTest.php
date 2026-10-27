<?php

namespace rewish\craft\menu\tests;

use PHPUnit\Framework\TestCase;
use rewish\craft\menu\elements\Node;
use rewish\craft\menu\services\ActiveMatcher;

/**
 * @coversNothing
 */
final class ActiveMatcherTest extends TestCase
{
    public function testPathFromUrl(): void
    {
        $matcher = new ActiveMatcher();
        self::assertSame('about/team', $matcher->pathFromUrl('https://example.com/about/team'));
        self::assertSame('about', $matcher->pathFromUrl('/about/'));
        self::assertNull($matcher->pathFromUrl('#'));
        self::assertNull($matcher->pathFromUrl(null));
    }

    public function testIsActivePrefix(): void
    {
        $matcher = new ActiveMatcher();
        $node = $this->getMockBuilder(Node::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getNodeUrl'])
            ->getMock()
        ;
        $node->method('getNodeUrl')->willReturn('/about');

        self::assertTrue($matcher->isActive($node, 'about'));
        self::assertTrue($matcher->isActive($node, 'about/team'));
        self::assertFalse($matcher->isActive($node, 'contact'));
        self::assertTrue($matcher->isCurrent($node, 'about'));
        self::assertFalse($matcher->isCurrent($node, 'about/team'));
    }
}
