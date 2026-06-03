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
        if (empty($this->to)) {
            throw new \RuntimeException('Mailer: no recipient set');
        }
        if (empty($this->subject)) {
            throw new \RuntimeException('Mailer: no subject set');
        }

        $host = $this->config['host'];
        $port = $this->config['port'];
        $scheme = $this->config['scheme'] ?? 'smtp';
        $username = $this->config['username'];
        $password = $this->config['password'];
        $fromAddr = $this->config['from']['address'] ?? $username;
        $fromName = $this->config['from']['name'] ?? '';

        $remote = ($scheme === 'smtps') ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";

        $ctx = stream_context_create($this->streamOptions);
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$socket) {
            throw new \RuntimeException("Mailer: cannot connect to {$host}:{$port} — {$errstr} ({$errno})");
        }

        try {
            $this->readResponse($socket);

            // EHLO
            $this->command($socket, "EHLO {$host}");

            // STARTTLS for non-SSL connections on port 587
            if ($scheme === 'smtp' && $port == 587) {
                $this->command($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->command($socket, "EHLO {$host}");
            }

            // AUTH LOGIN
            $this->command($socket, "AUTH LOGIN");
            $this->command($socket, base64_encode($username));
            $this->command($socket, base64_encode($password));

            // MAIL FROM
            $this->command($socket, "MAIL FROM:<{$fromAddr}>");

            // RCPT TO
            foreach ($this->to as $recipient) {
                $this->command($socket, "RCPT TO:<{$recipient}>");
            }

            // DATA
            $this->command($socket, "DATA");

            $headers = "From: " . $this->formatAddress($fromAddr, $fromName) . "\r\n";
            $headers .= "To: " . implode(', ', $this->to) . "\r\n";
            $headers .= "Subject: =?UTF-8?B?" . base64_encode($this->subject) . "?=\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: {$this->contentType}; charset=UTF-8\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "X-Mailer: EmbyController\r\n";
            $headers .= "\r\n";
            $headers .= $this->body;
            $headers .= "\r\n.";

            fwrite($socket, $headers);
            $this->readResponse($socket);

            // QUIT
            $this->command($socket, "QUIT");
        } finally {
            fclose($socket);
        }

        return true;
    }

    private function command($socket, $cmd)
    {
        fwrite($socket, $cmd . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket)
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if ($code >= 400) {
            throw new \RuntimeException("Mailer: SMTP error — " . trim($response));
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
}
