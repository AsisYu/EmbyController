<?php
namespace mailer;

use think\facade\Config;

class Mailer
{
    private $to = [];
    private $subject = '';
    private $body = '';
    private $contentType = 'text/html';
    private $streamOptions = [];
    private $config;
    private $log = [];

    public function __construct()
    {
        $this->config = Config::get('mailer');
    }

    public function to($address)
    {
        $this->to[] = $address;
        return $this;
    }

    public function subject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    public function html($content)
    {
        $this->body = $content;
        $this->contentType = 'text/html';
        return $this;
    }

    public function text($content)
    {
        $this->body = $content;
        $this->contentType = 'text/plain';
        return $this;
    }

    public function setStreamOptions(array $options)
    {
        $this->streamOptions = $options;
        return $this;
    }

    public function send()
    {
        $this->log = [];
        $this->info("========== Mailer::send() start ==========");

        if (empty($this->to)) {
            $this->err('no recipient set');
            $this->flushLog();
            throw new \RuntimeException('Mailer: no recipient set');
        }
        if (empty($this->subject)) {
            $this->err('no subject set');
            $this->flushLog();
            throw new \RuntimeException('Mailer: no subject set');
        }

        $host     = $this->config['host'];
        $port     = (int) $this->config['port'];
        $scheme   = $this->config['scheme'] ?? 'smtp';
        $username = $this->config['username'];
        $password = $this->config['password'];
        $fromAddr = $this->config['from']['address'] ?? $username;
        $fromName = $this->config['from']['name'] ?? '';

        $this->info("config: scheme={$scheme} host={$host} port={$port} user={$username} from={$fromAddr}");

        $remote = ($scheme === 'smtps') ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
        $this->info("remote: {$remote}");

        $ctx = stream_context_create($this->streamOptions);
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            $this->err("connect failed: {$errstr} ({$errno})");
            $this->flushLog();
            throw new \RuntimeException("Mailer: cannot connect to {$host}:{$port} — {$errstr} ({$errno})");
        }

        $this->info("connected OK");
        stream_set_timeout($socket, 10);

        try {
            // 服务器 greeting
            $greeting = $this->readResponse($socket);

            // EHLO — 获取服务器能力
            $ehloResponse = $this->command($socket, "EHLO {$host}");
            $capabilities = $this->parseCapabilities($ehloResponse);
            $this->info("capabilities: " . implode(' ', array_keys($capabilities)));

            // STARTTLS — 根据服务器能力决定
            if ($scheme !== 'smtps' && isset($capabilities['STARTTLS'])) {
                $this->info("STARTTLS advertised, upgrading connection");
                $this->command($socket, "STARTTLS");
                $enabled = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if (!$enabled) {
                    $this->err("STARTTLS crypto negotiation failed");
                    $this->flushLog();
                    throw new \RuntimeException("Mailer: STARTTLS negotiation failed");
                }
                $this->info("TLS enabled, re-sending EHLO");
                $ehloResponse = $this->command($socket, "EHLO {$host}");
                $capabilities = $this->parseCapabilities($ehloResponse);
            }

            // AUTH LOGIN
            if (isset($capabilities['AUTH'])) {
                $authMethods = $capabilities['AUTH'];
                $this->info("AUTH methods: {$authMethods}");
                if (stripos($authMethods, 'LOGIN') !== false) {
                    $this->info("authenticating via AUTH LOGIN as {$username}");
                    $this->command($socket, "AUTH LOGIN");
                    $this->command($socket, base64_encode($username), true);
                    $this->command($socket, base64_encode($password), true);
                } elseif (stripos($authMethods, 'PLAIN') !== false) {
                    $this->info("authenticating via AUTH PLAIN as {$username}");
                    $this->command($socket, "AUTH PLAIN " . base64_encode("\0{$username}\0{$password}"), true);
                } else {
                    $this->err("no supported AUTH method, server offers: {$authMethods}");
                    $this->flushLog();
                    throw new \RuntimeException("Mailer: no supported AUTH method (server: {$authMethods})");
                }
            } else {
                $this->info("no AUTH advertised, skipping authentication");
            }
            $this->info("authenticated OK");

            // MAIL FROM
            $this->command($socket, "MAIL FROM:<{$fromAddr}>");

            // RCPT TO
            foreach ($this->to as $recipient) {
                $this->info("RCPT TO: {$recipient}");
                $this->command($socket, "RCPT TO:<{$recipient}>");
            }

            // DATA
            $this->command($socket, "DATA");

            $headers = "From: " . $this->formatAddress($fromAddr, $fromName) . "\r\n";
            $headers .= "To: " . implode(', ', $this->to) . "\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($this->subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: {$this->contentType}; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "X-Mailer: EmbyController\r\n";
            $headers .= "\r\n";
            $headers .= chunk_split(base64_encode($this->body));
            $headers .= "\r\n.\r\n";

            $this->info("sending message body (" . strlen($headers) . " bytes)");
            $written = fwrite($socket, $headers);
            if ($written === false || $written !== strlen($headers)) {
                $this->err("DATA write incomplete: {$written}/" . strlen($headers));
                $this->flushLog();
                throw new \RuntimeException("Mailer: failed to write message body");
            }
            $this->readResponse($socket);
            $this->info("message accepted by server");

            $this->command($socket, "QUIT");
            $this->info("mail sent successfully to " . implode(', ', $this->to));
        } catch (\Exception $e) {
            $this->err("SMTP error: " . $e->getMessage());
            $this->flushLog();
            throw $e;
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }

        $this->flushLog();
        return true;
    }

    private function parseCapabilities($ehloResponse)
    {
        $caps = [];
        foreach (explode("\r\n", trim($ehloResponse)) as $line) {
            if (strlen($line) < 5) continue;
            $parts = preg_split('/\s+/', substr($line, 4), 2);
            $keyword = strtoupper($parts[0]);
            $caps[$keyword] = $parts[1] ?? '';
        }
        return $caps;
    }

    private function command($socket, $cmd, $redact = false)
    {
        if ($redact) {
            $this->info("C: AUTH ***redacted***");
        } else {
            $displayCmd = $cmd;
            if (strlen($displayCmd) > 80) {
                $displayCmd = substr($displayCmd, 0, 40) . '...';
            }
            $this->info("C: {$displayCmd}");
        }
        $written = fwrite($socket, $cmd . "\r\n");
        if ($written === false || $written === 0) {
            $this->err("command write failed");
            throw new \RuntimeException("Mailer: fwrite failed for command");
        }
        return $this->readResponse($socket);
    }

    private function readResponse($socket)
    {
        $response = '';
        $gotTerminator = false;

        while (!feof($socket)) {
            $line = fgets($socket, 515);
            if ($line === false) {
                $meta = stream_get_meta_data($socket);
                if ($meta['timed_out']) {
                    $this->err("socket timed out while reading");
                    throw new \RuntimeException("Mailer: socket timed out");
                }
                break;
            }
            if ($line === '') {
                break;
            }
            $response .= $line;
            $this->info("S: " . rtrim($line));
            if (isset($line[3]) && $line[3] === ' ') {
                $gotTerminator = true;
                break;
            }
        }

        if (empty($response)) {
            $meta = stream_get_meta_data($socket);
            if ($meta['timed_out']) {
                $this->err("socket timed out with no response");
                throw new \RuntimeException("Mailer: socket timed out with no response");
            }
            $this->err("empty response from server");
            throw new \RuntimeException("Mailer: empty response from server");
        }

        // SMTP 响应必须以 3 位数字开头
        if (!preg_match('/^\d{3}/', $response)) {
            $this->err("malformed SMTP response: " . trim($response));
            throw new \RuntimeException("Mailer: malformed SMTP response");
        }

        // 多行响应如果没有正确终止，报错
        if (!$gotTerminator && strpos($response, "\n") !== false) {
            $this->err("truncated multi-line response: " . trim($response));
            throw new \RuntimeException("Mailer: truncated multi-line SMTP response");
        }

        $code = (int) substr($response, 0, 3);
        if ($code >= 400) {
            $this->err("SMTP error code {$code}: " . trim($response));
            throw new \RuntimeException("Mailer: SMTP error {$code} — " . trim($response));
        }

        return $response;
    }

    private function formatAddress($email, $name = '')
    {
        if ($name) {
            return "=?UTF-8?B?" . base64_encode($name) . "?= <{$email}>";
        }
        return "<{$email}>";
    }

    private function info($msg)
    {
        $this->log[] = date('Y-m-d H:i:s') . " [INFO] " . $msg;
    }

    private function err($msg)
    {
        $this->log[] = date('Y-m-d H:i:s') . " [ERROR] " . $msg;
    }

    private function flushLog()
    {
        $logDir = dirname(__DIR__, 2) . '/runtime/log';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/mailer.log';
        $content = implode("\n", $this->log) . "\n";
        file_put_contents($logFile, $content, FILE_APPEND);
        $this->log = [];
    }
}
