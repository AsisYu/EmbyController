<?php
namespace app\api\job;

use think\facade\Config;
use think\queue\Job;
use mailer\Mailer;

class SendMailMessage
{
    private function jobLog($msg)
    {
        $logDir = __DIR__ . '/../../runtime/log';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $line = date('Y-m-d H:i:s') . " " . $msg . "\n";
        @file_put_contents($logDir . '/mailer_job.log', $line, FILE_APPEND);
    }

    public function fire(Job $job, $data)
    {
        $to = $data['to'] ?? 'unknown';
        $subject = $data['subject'] ?? 'unknown';
        $this->jobLog("[START] to={$to} subject={$subject}");

        try {
            if ($job->isDeleted()) {
                $this->jobLog("[SKIP] job already deleted");
                return;
            }

            if (!Config::get('mailer.enable')) {
                $this->jobLog("[SKIP] mailer disabled in config");
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
                $this->jobLog("[INFO] using socks5 proxy");
                $mailer->setStreamOptions($streamContext);
            }

            if ($isHtml) {
                $mailer->html($content);
            } else {
                $mailer->text($content);
            }

            $mailer->to($to)->subject($subject)->send();

            $this->jobLog("[SUCCESS] sent to {$to}");
            $job->delete();

        } catch (\Exception $e) {
            $attempts = $job->attempts();
            $this->jobLog("[FAIL] attempt {$attempts}: " . $e->getMessage());

            if ($attempts >= 3) {
                $this->jobLog("[FAIL] max retries reached, deleting job");
                $job->delete();
                return;
            }

            $job->release(60);
        }
    }
}
