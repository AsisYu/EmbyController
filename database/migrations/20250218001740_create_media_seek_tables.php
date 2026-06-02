<?php

use think\migration\Migrator;
use think\migration\db\Column;

class CreateMediaSeekTables extends Migrator
{
    public function change()
    {
        // 创建求片日志表
        $this->table('media_seek_log', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '求片日志表'
        ])->addColumn('seekId', 'integer', [
            'null' => false,
            'comment' => '求片ID'
        ])->addColumn('type', 'integer', [
            'limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY,
            'null' => false,
            'comment' => '类型:1=创建求片,2=同求,3=状态变更'
        ])->addColumn('content', 'text', [
            'null' => true,
            'comment' => '日志内容'
        ])->addColumn('createdAt', 'datetime', [
            'null' => false,
            'default' => 'CURRENT_TIMESTAMP'
        ])->addIndex(['seekId'], [
            'name' => 'seekId'
        ])->create();

        // 创建求片同求用户表
        $this->table('media_seek_user', [
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
            'comment' => '求片同求用户表'
        ])->addColumn('seekId', 'integer', [
            'null' => false,
            'comment' => '求片ID'
        ])->addColumn('userId', 'integer', [
            'null' => false,
            'comment' => '同求用户ID'
        ])->addColumn('createdAt', 'datetime', [
            'null' => false,
            'default' => 'CURRENT_TIMESTAMP'
        ])->addIndex(['seekId', 'userId'], [
            'unique' => true,
            'name' => 'seekId_userId'
        ])->create();
    }

    /**
     * 获取带前缀的表名
     */
    private function getTable($name)
    {
        return env('DB_PREFIX', 'rc_') . $name;
    }
}