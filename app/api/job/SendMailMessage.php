<?php
namespace app\api\job;

use think\facade\Config;
use think\facade\Log;
use think\queue\Job;
use mailer\Mailer;

class SendMailMessage
{
    public function fire(Job $job, $data)
    {
        $to = $data['to'] ?? 'unknown';
        $subject = $data['subject'] ?? 'unknown';
        Log::info("[SendMailMessage] job start, to={$to} subject={$subject}");

        try {
            if ($job->isDeleted()) {
                Log::info("[SendMailMessage] job already deleted, skip");
                return;
            }

            if (!Config::get('mailer.enable')) {
                Log::warning("[SendMailMessage] mailer disabled in config, skip");
                $job->delete();
                return;
            }

            $content = $data['content'];
            $isHtml = $data['isHtml'] ?? true;

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

            if ($isHtml) {
                $mailer->html($content);
            } else {
                $mailer->text($content);
            }

            $mailer->to($to)->subject($subject)->send();

            Log::info("[SendMailMessage] success, to={$to}");
            $job->delete();

        } catch (\Exception $e) {
            $attempts = $job->attempts();
            Log::error("[SendMailMessage] attempt {$attempts} failed: " . $e->getMessage());

            if ($attempts >= 3) {
                Log::error("[SendMailMessage] max retries reached, deleting job");
                $job->delete();
                return;
            }

            $job->release(60);
        }
    }
} 