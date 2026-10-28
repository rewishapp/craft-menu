<?php

namespace rewish\craft\menu\tests;

use PHPUnit\Framework\TestCase;
use rewish\craft\menu\services\DynamicSources;

/**
 * @coversNothing
 */
final class DynamicSourcesTest extends TestCase
{
    public function testSourceConstants(): void
    {
        self::assertSame('entrySection', DynamicSources::SOURCE_ENTRY_SECTION);
        self::assertSame('categoryGroup', DynamicSources::SOURCE_CATEGORY_GROUP);
        self::assertSame('assetVolume', DynamicSources::SOURCE_ASSET_VOLUME);
    }

    public function testOrderByClause(): void
    {
        self::assertSame(['title' => \SORT_ASC], DynamicSources::orderByClause(null));
        self::assertSame(['title' => \SORT_ASC], DynamicSources::orderByClause('titleAsc'));
        self::assertSame(['title' => \SORT_DESC], DynamicSources::orderByClause('titleDesc'));
        self::assertSame(['postDate' => \SORT_DESC], DynamicSources::orderByClause('dateDesc', 'postDate'));
        self::assertSame(['dateCreated' => \SORT_ASC], DynamicSources::orderByClause('dateAsc'));
        self::assertSame(['title' => \SORT_ASC], DynamicSources::orderByClause('bogus'));
    }
}
