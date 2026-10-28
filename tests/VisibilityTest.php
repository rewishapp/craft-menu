<?php

namespace rewish\craft\menu\tests;

use PHPUnit\Framework\TestCase;
use rewish\craft\menu\models\ProjectedNode;
use rewish\craft\menu\services\Visibility;

/**
 * @coversNothing
 */
final class VisibilityTest extends TestCase
{
    public function testLoggedInAndOut(): void
    {
        $v = new Visibility();

        self::assertTrue($v->evaluateRule(['type' => 'always'], $this->context()));
        self::assertFalse($v->evaluateRule(['type' => 'loggedIn'], $this->context()));
        self::assertTrue($v->evaluateRule(['type' => 'loggedIn'], $this->context(['loggedIn' => true])));
        self::assertTrue($v->evaluateRule(['type' => 'loggedOut'], $this->context()));
        self::assertFalse($v->evaluateRule(['type' => 'loggedOut'], $this->context(['loggedIn' => true])));
    }

    public function testUserGroupById(): void
    {
        $v = new Visibility();
        $ctx = $this->context(['loggedIn' => true, 'groupIds' => [2, 5], 'groupHandles' => ['editors']]);

        self::assertTrue($v->evaluateRule(['type' => 'userGroup', 'value' => '5'], $ctx));
        self::assertFalse($v->evaluateRule(['type' => 'userGroup', 'value' => '7'], $ctx));
        self::assertTrue($v->evaluateRule(['type' => 'userGroup', 'value' => 'editors'], $ctx));
        self::assertFalse($v->evaluateRule(['type' => 'userGroup', 'value' => '5'], $this->context()));
    }

    public function testSite(): void
    {
        $v = new Visibility();

        self::assertTrue($v->evaluateRule(['type' => 'site', 'value' => '1'], $this->context()));
        self::assertFalse($v->evaluateRule(['type' => 'site', 'value' => '2'], $this->context()));
    }

    public function testDateRange(): void
    {
        $v = new Visibility();
        $in = ['type' => 'dateRange', 'value' => ['from' => '2026-06-01', 'to' => '2026-06-30']];
        $past = ['type' => 'dateRange', 'value' => ['from' => '2026-01-01', 'to' => '2026-02-01']];
        $open = ['type' => 'dateRange', 'value' => ['from' => '2026-06-15', 'to' => null]];

        self::assertTrue($v->evaluateRule($in, $this->context()));
        self::assertFalse($v->evaluateRule($past, $this->context()));
        self::assertTrue($v->evaluateRule($open, $this->context()), 'bounds are inclusive');
        self::assertFalse($v->evaluateRule($open, $this->context(['today' => '2026-06-14'])));
    }

    public function testEnvironment(): void
    {
        $v = new Visibility();

        self::assertTrue($v->evaluateRule(['type' => 'environment', 'value' => 'production'], $this->context()));
        self::assertTrue($v->evaluateRule(['type' => 'environment', 'value' => 'staging, Production'], $this->context()));
        self::assertFalse($v->evaluateRule(['type' => 'environment', 'value' => 'dev'], $this->context()));
    }

    public function testFilterTreeAppliesAllRulesAndDropsSubtrees(): void
    {
        $v = new Visibility();
        $make = static fn (string $title, array $rules = []) => new ProjectedNode([
            'title' => $title,
            'rawMeta' => ['visibility' => $rules],
        ]);

        $tree = [
            ['node' => $make('Public'), 'children' => []],
            ['node' => $make('Members', [['type' => 'loggedIn']]), 'children' => [
                ['node' => $make('Child of members'), 'children' => []],
            ]],
            ['node' => $make('Both', [['type' => 'loggedOut'], ['type' => 'site', 'value' => '1']]), 'children' => [
                ['node' => $make('Nested hidden', [['type' => 'site', 'value' => '9']]), 'children' => []],
                ['node' => $make('Nested shown'), 'children' => []],
            ]],
        ];

        $filtered = $v->filterTree($tree, 1, $this->context());

        self::assertSame(['Public', 'Both'], array_map(static fn (array $i) => $i['node']->title, $filtered));
        self::assertSame(['Nested shown'], array_map(static fn (array $i) => $i['node']->title, $filtered[1]['children']));

        $loggedIn = $v->filterTree($tree, 1, $this->context(['loggedIn' => true]));
        self::assertSame(['Public', 'Members'], array_map(static fn (array $i) => $i['node']->title, $loggedIn));
        self::assertCount(1, $loggedIn[1]['children']);
    }

    /**
     * @return array<string, mixed>
     */
    private function context(array $override = []): array
    {
        return array_merge([
            'loggedIn' => false,
            'groupIds' => [],
            'groupHandles' => [],
            'siteId' => 1,
            'today' => '2026-06-15',
            'environment' => 'production',
        ], $override);
    }
}
