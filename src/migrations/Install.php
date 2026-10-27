<?php

namespace rewish\craft\menu\migrations;

use craft\db\Migration;
use craft\db\Table;

class Install extends Migration
{
    public function safeUp(): bool
    {
        if (!$this->db->tableExists('{{%menu_menus}}')) {
            $this->createTable('{{%menu_menus}}', [
                'id' => $this->integer()->notNull(),
                'structureId' => $this->integer()->notNull(),
                'handle' => $this->string()->notNull(),
                'instructions' => $this->text()->null(),
                'maxLevels' => $this->smallInteger()->unsigned()->null(),
                'sortOrder' => $this->smallInteger()->unsigned()->null(),
                'nodeFieldLayoutId' => $this->integer()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY(id)',
            ]);
            $this->createIndex(null, '{{%menu_menus}}', ['handle'], true);
            $this->createIndex(null, '{{%menu_menus}}', ['structureId'], false);
            $this->createIndex(null, '{{%menu_menus}}', ['nodeFieldLayoutId'], false);
            $this->addForeignKey(null, '{{%menu_menus}}', ['id'], Table::ELEMENTS, ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%menu_menus}}', ['structureId'], Table::STRUCTURES, ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%menu_menus}}', ['nodeFieldLayoutId'], Table::FIELDLAYOUTS, ['id'], 'SET NULL', null);
        }

        if (!$this->db->tableExists('{{%menu_nodes}}')) {
            $this->createTable('{{%menu_nodes}}', [
                'id' => $this->integer()->notNull(),
                'menuId' => $this->integer()->notNull(),
                'type' => $this->string(64)->notNull()->defaultValue('custom'),
                'elementId' => $this->integer()->null(),
                'url' => $this->text()->null(),
                'classes' => $this->string()->null(),
                'urlSuffix' => $this->string()->null(),
                'newWindow' => $this->boolean()->notNull()->defaultValue(false),
                'followElement' => $this->boolean()->notNull()->defaultValue(true),
                'customAttributes' => $this->text()->null(),
                'data' => $this->text()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
                'PRIMARY KEY(id)',
            ]);
            $this->createIndex(null, '{{%menu_nodes}}', ['menuId'], false);
            $this->createIndex(null, '{{%menu_nodes}}', ['elementId'], false);
            $this->createIndex(null, '{{%menu_nodes}}', ['type'], false);
            $this->addForeignKey(null, '{{%menu_nodes}}', ['id'], Table::ELEMENTS, ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%menu_nodes}}', ['menuId'], '{{%menu_menus}}', ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%menu_nodes}}', ['elementId'], Table::ELEMENTS, ['id'], 'SET NULL', null);
        }

        if (!$this->db->tableExists('{{%menu_menus_sites}}')) {
            $this->createTable('{{%menu_menus_sites}}', [
                'id' => $this->primaryKey(),
                'menuId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'enabled' => $this->boolean()->notNull()->defaultValue(true),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            $this->createIndex(null, '{{%menu_menus_sites}}', ['menuId', 'siteId'], true);
            $this->createIndex(null, '{{%menu_menus_sites}}', ['siteId'], false);
            $this->addForeignKey(null, '{{%menu_menus_sites}}', ['menuId'], '{{%menu_menus}}', ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%menu_menus_sites}}', ['siteId'], Table::SITES, ['id'], 'CASCADE', 'CASCADE');
        }

        if (!$this->db->tableExists('{{%menu_build_sessions}}')) {
            $this->createTable('{{%menu_build_sessions}}', [
                'id' => $this->primaryKey(),
                'menuId' => $this->integer()->notNull(),
                'siteId' => $this->integer()->notNull(),
                'userId' => $this->integer()->notNull(),
                'snapshot' => $this->mediumText()->null(),
                'moves' => $this->mediumText()->null(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);
            $this->createIndex(null, '{{%menu_build_sessions}}', ['menuId', 'siteId', 'userId'], true);
            $this->addForeignKey(null, '{{%menu_build_sessions}}', ['menuId'], '{{%menu_menus}}', ['id'], 'CASCADE', null);
            $this->addForeignKey(null, '{{%menu_build_sessions}}', ['siteId'], Table::SITES, ['id'], 'CASCADE', 'CASCADE');
            $this->addForeignKey(null, '{{%menu_build_sessions}}', ['userId'], Table::USERS, ['id'], 'CASCADE', null);
        }

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%menu_build_sessions}}');
        $this->dropTableIfExists('{{%menu_menus_sites}}');
        $this->dropTableIfExists('{{%menu_nodes}}');
        $this->dropTableIfExists('{{%menu_menus}}');

        return true;
    }
}
