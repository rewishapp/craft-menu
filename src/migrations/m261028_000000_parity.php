<?php

namespace rewish\craft\menu\migrations;

use craft\db\Migration;

class m261028_000000_parity extends Migration
{
    public function safeUp(): bool
    {
        if ($this->db->tableExists('{{%menu_menus}}')) {
            if (!$this->db->columnExists('{{%menu_menus}}', 'cssClass')) {
                $this->addColumn('{{%menu_menus}}', 'cssClass', $this->string()->null()->after('nodeFieldLayoutId'));
            }
            if (!$this->db->columnExists('{{%menu_menus}}', 'htmlAttributes')) {
                $this->addColumn('{{%menu_menus}}', 'htmlAttributes', $this->text()->null()->after('cssClass'));
            }
        }

        return true;
    }

    public function safeDown(): bool
    {
        if ($this->db->tableExists('{{%menu_menus}}')) {
            if ($this->db->columnExists('{{%menu_menus}}', 'htmlAttributes')) {
                $this->dropColumn('{{%menu_menus}}', 'htmlAttributes');
            }
            if ($this->db->columnExists('{{%menu_menus}}', 'cssClass')) {
                $this->dropColumn('{{%menu_menus}}', 'cssClass');
            }
        }

        return true;
    }
}
