<?php
/**
 * 加载 .env 文件到环境变量
 * vlucas/phpdotenv 无法正确解析 ThinkPHP 的 KEY = value 格式，
 * 故使用此手动解析器（server.php、think、public/index.php 共用）
 */
function loadEnvFile(string $dir): void
{
    $envFile = rtrim($dir, '/') . '/.env';
    if (!file_exists($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = array_map('trim', explode('=', $line, 2));
            $value = trim($value, '"\'');
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            putenv("{$key}={$value}");
        }
    }
}
