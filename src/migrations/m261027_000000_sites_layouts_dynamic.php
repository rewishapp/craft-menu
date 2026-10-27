<?php

namespace rewish\craft\menu\migrations;

use craft\db\Migration;
use craft\db\Table;

class m261027_000000_sites_layouts_dynamic extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%menu_menus}}')) {
            if (!$this->db->columnExists('{{%menu_menus}}', 'nodeFieldLayoutId')) {
                $this->addColumn('{{%menu_menus}}', 'nodeFieldLayoutId', $this->integer()->null()->after('sortOrder'));
                $this->createIndex(null, '{{%menu_menus}}', ['nodeFieldLayoutId'], false);
                $this->addForeignKey(null, '{{%menu_menus}}', ['nodeFieldLayoutId'], Table::FIELDLAYOUTS, ['id'], 'SET NULL', null);
            }
        }

        if ($this->db->tableExists('{{%menu_nodes}}') && !$this->db->columnExists('{{%menu_nodes}}', 'data')) {
            $this->addColumn('{{%menu_nodes}}', 'data', $this->text()->null()->after('customAttributes'));
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

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%menu_menus_sites}}');
        if ($this->db->tableExists('{{%menu_nodes}}') && $this->db->columnExists('{{%menu_nodes}}', 'data')) {
            $this->dropColumn('{{%menu_nodes}}', 'data');
        }
        if ($this->db->tableExists('{{%menu_menus}}') && $this->db->columnExists('{{%menu_menus}}', 'nodeFieldLayoutId')) {
            $this->dropForeignKeyIfExists('{{%menu_menus}}', ['nodeFieldLayoutId']);
            $this->dropIndexIfExists('{{%menu_menus}}', ['nodeFieldLayoutId'], false);
            $this->dropColumn('{{%menu_menus}}', 'nodeFieldLayoutId');
        }

        return true;
    }
}
