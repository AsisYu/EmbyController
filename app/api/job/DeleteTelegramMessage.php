<?php
namespace app\api\job;

use think\facade\Config;
use think\queue\Job;
use Telegram\Bot\Api;

class DeleteTelegramMessage
{
    public function fire(Job $job, $data)
    {
        try {
            // 如果任务已经删除则直接返回
            if ($job->isDeleted()) {
                return;
            }

            // 获取消息数据
            $chatId = $data['chat_id'];
            $messageId = $data['message_id'];
            
            // 获取Telegram Bot Token
            $token = Config::get('telegram.botConfig.bots.default.token');
            if (!$token || $token == 'notgbot') {
                $job->delete();
                return;
            }

            // 初始化Telegram Bot API
            $telegram = new Api($token);
            
            // 尝试删除消息
            $telegram->deleteMessage([
                'chat_id' => $chatId,
                'message_id' => $messageId
            ]);

            // 任务完成后删除
            $job->delete();
            
        } catch (\Exception $e) {
            // 如果是消息已经被删除的错误，直接标记任务完成
            if (strpos($e->getMessage(), 'message to delete not found') !== false) {
                $job->delete();
                return;
            }
            
            // 失败次数+1
            $attempts = $job->attempts();
            
            // 如果失败次数超过3次，则删除任务
            if ($attempts >= 3) {
                $job->delete();
                return;
            }
            
            // 否则重试，延迟60秒
            $job->release(60);
        }
    }
} 