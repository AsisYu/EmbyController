<?php
namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class Seed extends Command
{
    protected function configure()
    {
        $this->setName('seed')
            ->setDescription('初始化数据库：创建管理员账户和默认配置');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始初始化...');

        // 1. 创建管理员用户
        $user = Db::name('user')->where('userName', 'admin')->find();
        if ($user) {
            $output->writeln('管理员账户已存在，跳过');
        } else {
            Db::name('user')->insert([
                'userName' => 'admin',
                'nickName' => 'admin',
                'password' => '$2y$10$rJff.jXkgLpFBN0qE9B.Uu/gnlH2WsUqblAMJOH4iNg7w7OjKJZG6',
                'authority' => 0,
                'email'    => env('ADMIN_EMAIL', 'admin@admin.com'),
                'rCoin'    => 0,
            ]);
            $output->writeln('管理员账户已创建: admin / A123456');
        }

        // 2. 初始化配置表默认值
        $defaults = [
            'avableRegisterCount' => env('REGISTER_OPEN', true) ? '-1' : '0',
            'chargeRate'          => '1',
            'sysnotificiations'   => '您有一条新消息：{Message}',
            'findPasswordTemplate'=> '您的找回密码链接是：<a href="{Url}">{Url}</a>',
            'verifyCodeTemplate'  => '您的验证码是：{Code}',
            'clientList'          => '[]',
            'clientBlackList'     => '[]',
            'maxActiveDeviceCount'=> '0',
            'signInMaxAmount'     => '0',
            'signInMinAmount'     => '0',
            'telegramRules'       => '[]',
            'privacyPolicy'       => '',
            'userAgreement'       => '',
        ];

        foreach ($defaults as $key => $value) {
            $exists = Db::name('config')->where('key', $key)->find();
            if (!$exists && $value !== '') {
                Db::name('config')->insert([
                    'key'     => $key,
                    'value'   => $value,
                    'appName' => 'media',
                    'type'    => 1,
                    'status'  => 1,
                ]);
                $output->writeln("配置项已创建: {$key} = {$value}");
            }
        }

        $output->writeln('初始化完成。');
    }
}
