<?php
// 应用公共文件

use app\media\model\TelegramModel;
use mailer\Mailer;
use think\facade\Cache;
use think\facade\Config;
use app\BaseController;
use Telegram\Bot\Api;
use WebSocket\Client;

function sendTGMessage($id, $message)
{
    $telegram = new Api(Config::get('telegram.botConfig.bots.default.token'));
    $telegramUserModel = new TelegramModel();
    $telegramUser = $telegramUserModel->where('userId', $id)->find();
    if ($telegramUser) {
        $userInfoArray = json_decode(json_encode($telegramUser['userInfo']), true);
        if (isset($userInfoArray['notification']) && ($userInfoArray['notification'] == 1 || $userInfoArray['notification'] == "1")) {
            $telegram->sendMessage([
                'chat_id' => $telegramUser['telegramId'],
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);
        }
    }
}

function sendTGMessageToGroup($message)
{
    $groupSetting = Config::get('telegram.groupSetting');
    if (isset($groupSetting['allow_notify']) && $groupSetting['allow_notify']) {
        $telegram = new Api(Config::get('telegram.botConfig.bots.default.token'));
        $telegram->sendMessage([
            'chat_id' => $groupSetting['chat_id'],
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
    }
}

function sendEmail($email, $title, $message)
{
    if (!Config::get('mailer.enable')) {
        throw new \RuntimeException('邮件功能未启用');
    }
    $mailer = new Mailer();
    $mailer->html($message);
    $mailer->subject($title);
    $mailer->to($email);
    $mailer->send();
}

function sendEmailForce($email, $title, $message)
{
    if (!Config::get('mailer.enable')) {
        throw new \RuntimeException('邮件功能未启用');
    }

    $mailer = new Mailer();

    if (Config::get('mailer.use_socks5') && Config::get('proxy.socks5.enable')) {
        $socks5Config = Config::get('proxy.socks5');
        $streamContext = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'socks5' => [
                'proxy' => "socks5://{$socks5Config['host']}:{$socks5Config['port']}",
            ]
        ];
        if (!empty($socks5Config['username']) && !empty($socks5Config['password'])) {
            $streamContext['socks5']['proxy'] = "socks5://{$socks5Config['username']}:{$socks5Config['password']}@{$socks5Config['host']}:{$socks5Config['port']}";
        }
        $mailer->setStreamOptions($streamContext);
    }

    $mailer->html($message);
    $mailer->subject($title);
    $mailer->to($email);
    $mailer->send();
}