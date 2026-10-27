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
}
