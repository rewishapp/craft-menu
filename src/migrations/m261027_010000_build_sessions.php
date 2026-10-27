<?php

namespace rewish\craft\menu\migrations;

use craft\db\Migration;
use craft\db\Table;

class m261027_010000_build_sessions extends Migration
{
    public function safeUp(): bool
    {
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

        return true;
    }
}
