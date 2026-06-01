<?php
use think\migration\Migrator;
use think\migration\db\Column;

class AddInitialAdminUser extends Migrator
{
    public function up()
    {
        $password = '$2y$10$rJff.jXkgLpFBN0qE9B.Uu/gnlH2WsUqblAMJOH4iNg7w7OjKJZG6';
        $email = env('ADMIN_EMAIL', 'admin@admin.com');
        $sql = "INSERT INTO `{$this->getTable('user')}` ("
             . "`userName`, `nickName`, `password`, `authority`, `email`, `rCoin`"
             . ") VALUES ("
             . "'admin', 'admin', '{$password}', 0, '{$email}', 0"
             . ");";
        $this->execute($sql);
    }

    public function down()
    {
        $this->execute("DELETE FROM `{$this->getTable('user')}` WHERE `userName` = 'admin';");
    }

    /**
     * 获取带前缀的表名
     */
    private function getTable($name)
    {
        return env('DB_PREFIX', 'rc_') . $name;
    }
}