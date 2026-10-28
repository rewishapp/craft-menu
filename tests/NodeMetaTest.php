<?php

namespace rewish\craft\menu\tests;

use PHPUnit\Framework\TestCase;
use rewish\craft\menu\helpers\NodeMeta;

/**
 * @coversNothing
 */
final class NodeMetaTest extends TestCase
{
    public function testDefaultsWhenEmpty(): void
    {
        $meta = NodeMeta::fromData(null);

        self::assertTrue($meta['clickable']);
        self::assertSame('unlink', $meta['fallback']);
        self::assertSame('default', $meta['badgeStyle']);
        self::assertSame([], $meta['visibility']);
        self::assertSame(1, $meta['megaColumns']);
    }

    public function testNormalizeCoercesAndClamps(): void
    {
        $meta = NodeMeta::normalize([
            'clickable' => '0',
            'featured' => 'on',
            'megaColumns' => 99,
            'megaColumn' => 0,
            'badgeStyle' => 'nope',
            'fallback' => 'nope',
            'mobileOrder' => '3',
            'iconAssetId' => '12',
            'htmlId' => '  main  ',
            'rel' => '   ',
        ]);

        self::assertFalse($meta['clickable']);
        self::assertTrue($meta['featured']);
        self::assertSame(6, $meta['megaColumns']);
        self::assertSame(1, $meta['megaColumn']);
        self::assertSame('default', $meta['badgeStyle']);
        self::assertSame('unlink', $meta['fallback']);
        self::assertSame(3, $meta['mobileOrder']);
        self::assertSame(12, $meta['iconAssetId']);
        self::assertSame('main', $meta['htmlId']);
        self::assertNull($meta['rel']);
    }

    public function testVisibilityRulesAreNormalized(): void
    {
        $rules = NodeMeta::normalizeVisibility([
            ['type' => 'loggedIn', 'value' => 'ignored'],
            ['type' => 'userGroup', 'value' => ' 4 '],
            ['type' => 'userGroup', 'value' => ''],
            ['type' => 'bogus'],
            ['type' => 'dateRange', 'value' => '2026-01-01,2026-02-01'],
            ['type' => 'dateRange', 'value' => 'garbage'],
            'not-an-array',
        ]);

        self::assertSame([
            ['type' => 'loggedIn'],
            ['type' => 'userGroup', 'value' => '4'],
            ['type' => 'dateRange', 'value' => ['from' => '2026-01-01', 'to' => '2026-02-01']],
        ], $rules);
    }

    public function testDateRangeAcceptsOpenEnds(): void
    {
        self::assertSame(['from' => '2026-05-01', 'to' => null], NodeMeta::normalizeDateRange('2026-05-01,'));
        self::assertSame(['from' => null, 'to' => '2026-05-01'], NodeMeta::normalizeDateRange(['to' => '2026-05-01']));
        self::assertNull(NodeMeta::normalizeDateRange('2026-13-45'));
        self::assertSame('2026-01-01,2026-02-01', NodeMeta::visibilityValueToText([
            'type' => 'dateRange',
            'value' => ['from' => '2026-01-01', 'to' => '2026-02-01'],
        ]));
    }

    public function testSetMergesWithoutWipingOtherDataKeys(): void
    {
        $node = new FakeDataNode();
        $node->setDataArray(['source' => 'entrySection', 'section' => 'news', 'meta' => ['badgeText' => 'Hot', 'rel' => 'nofollow']]);

        NodeMeta::set($node, ['rel' => 'sponsored', 'featured' => '1']);

        $data = $node->getDataArray();
        self::assertSame('entrySection', $data['source']);
        self::assertSame('news', $data['section']);
        self::assertSame('Hot', $data['meta']['badgeText'], 'untouched meta keys survive');
        self::assertSame('sponsored', $data['meta']['rel']);
        self::assertTrue($data['meta']['featured']);
        self::assertArrayNotHasKey('clickable', $data['meta'], 'defaults are not stored');
    }

    public function testSetWithDefaultsRemovesMetaKey(): void
    {
        $node = new FakeDataNode();
        $node->setDataArray(['source' => 'x', 'meta' => ['badgeText' => 'Hot']]);

        NodeMeta::set($node, ['badgeText' => '']);

        self::assertSame(['source' => 'x'], $node->getDataArray());

        $empty = new FakeDataNode();
        NodeMeta::set($empty, ['badgeText' => '']);
        self::assertSame([], $empty->getDataArray());
    }

    public function testFromRequestHandlesEmptiedVisibility(): void
    {
        $cleared = NodeMeta::fromRequest(['meta' => ['visibilitySubmitted' => '1', 'rel' => 'x']]);
        self::assertSame([], $cleared['visibility']);
        self::assertArrayNotHasKey('visibilitySubmitted', $cleared);

        $indexed = NodeMeta::fromRequest(['meta' => [
            'visibilitySubmitted' => '1',
            'visibility' => [4 => ['type' => 'loggedOut']],
        ]]);
        self::assertSame([['type' => 'loggedOut']], $indexed['visibility']);

        self::assertSame([], NodeMeta::fromRequest([]));
    }
}

/**
 * Minimal stand-in exposing the data accessors NodeMeta relies on.
 */
final class FakeDataNode
{
    private ?string $data = null;

    /**
     * @return array<string, mixed>
     */
    public function getDataArray(): array
    {
        return $this->data ? json_decode($this->data, true) : [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setDataArray(array $data): void
    {
        $this->data = $data === [] ? null : json_encode($data);
    }
}
