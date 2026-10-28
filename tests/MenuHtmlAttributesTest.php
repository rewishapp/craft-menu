<?php

namespace rewish\craft\menu\tests;

use PHPUnit\Framework\TestCase;
use rewish\craft\menu\elements\Menu;
use rewish\craft\menu\services\Duplicators;

/**
 * @coversNothing
 */
final class MenuHtmlAttributesTest extends TestCase
{
    public function testParsesJsonAndPairs(): void
    {
        self::assertSame(['data-a' => '1', 'role' => 'navigation'], Menu::parseHtmlAttributes('{"data-a":1,"role":"navigation","bad key":"x","nested":{"a":1}}'));
        self::assertSame(['data-x' => 'y', 'id' => 'z'], Menu::parseHtmlAttributes('data-x="y" id=\'z\''));
        self::assertSame([], Menu::parseHtmlAttributes(null));
        self::assertSame([], Menu::parseHtmlAttributes('   '));
    }

    public function testStripUidsKeepsFieldReferences(): void
    {
        $config = [
            ['uid' => 'tab-1', 'name' => 'Tab', 'elements' => [
                ['uid' => 'el-1', 'type' => 'CustomField', 'fieldUid' => 'field-1'],
            ]],
        ];

        self::assertSame(
            [['name' => 'Tab', 'elements' => [['type' => 'CustomField', 'fieldUid' => 'field-1']]]],
            Duplicators::stripUids($config)
        );
    }
}
