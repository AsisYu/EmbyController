<?php

namespace app\index\controller;

use app\BaseController;
use think\facade\Db;
use think\facade\View;

class Install extends BaseController
{
    protected $rootPath;

    protected function initialize()
    {
        $this->rootPath = dirname(__DIR__, 3);
    }

    public function index()
    {
        $envFile = $this->rootPath . '/.env';
        $installed = false;

        if (file_exists($envFile)) {
            loadEnvFile($this->rootPath);
            try {
                Db::query('SELECT 1');
                $installed = (bool) Db::name('user')->where('userName', 'admin')->find();
            } catch (\Throwable $e) {
                $installed = false;
            }
        }

        View::assign('installed', $installed);
        View::assign('appName', 'EmbyController');
        return view();
    }

    public function init()
    {
        $data = request()->post();

        $required = ['db_host', 'db_port', 'db_name', 'db_user', 'db_pass', 'admin_email', 'admin_pass'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return json(['code' => 400, 'message' => "参数缺失: {$field}"]);
            }
        }

        $envFile = $this->rootPath . '/.env';
        if (file_exists($envFile)) {
            return json(['code' => 400, 'message' => '已安装，如需重装请删除 .env 文件']);
        }

        try {
            $envContent = $this->buildEnv($data);
            file_put_contents($envFile, $envContent);

            loadEnvFile($this->rootPath);

            $logDir = $this->rootPath . '/runtime/log';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }

            // 测试数据库连接
            try {
                Db::query('SELECT 1');
            } catch (\Throwable $e) {
                return json(['code' => 400, 'message' => '数据库连接失败: ' . $e->getMessage()]);
            }

            // 运行迁移
            $migrateResult = $this->runMigrations();

            // 初始化数据
            $this->runSeed($data);

            // 注册 queue worker
            $queueResult = $this->registerQueueWorker();

            return json([
                'code' => 200,
                'message' => '安装成功',
                'data' => [
                    'migrate' => $migrateResult,
                    'queue' => $queueResult,
                ]
            ]);

        } catch (\Throwable $e) {
            @unlink($envFile);
            return json(['code' => 500, 'message' => '安装失败: ' . $e->getMessage()]);
        }
    }

    private function buildEnv(array $data): string
    {
        $envFile = $this->rootPath . '/.env';
        // 如果已存在，以现有文件为基础替换；否则用 example.env
        $templateFile = $this->rootPath . '/example.env';
        if (!file_exists($templateFile)) {
            throw new \RuntimeException('example.env 模板文件不存在');
        }

        $lines = file($templateFile, FILE_IGNORE_NEW_LINES);
        $env = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                $env[] = $line;
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                $env[] = "{$key} = {$value}";
            } else {
                $env[] = $line;
            }
        }

        $replacements = [
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => $data['db_port'],
            'DB_NAME' => $data['db_name'],
            'DB_USER' => $data['db_user'],
            'DB_PASS' => $data['db_pass'],
            'ADMIN_EMAIL' => $data['admin_email'],
            'CACHE_TYPE' => 'redis',
            'REDIS_HOST' => $data['redis_host'] ?? '127.0.0.1',
            'REDIS_PORT' => $data['redis_port'] ?? '6379',
            'REDIS_PASS' => $data['redis_pass'] ?? '',
            'REDIS_DB' => $data['redis_db'] ?? '8',
            'APP_HOST' => request()->domain(),
        ];

        $result = [];
        foreach ($env as $line) {
            $trimmed = trim($line);
            $matched = false;
            foreach ($replacements as $key => $value) {
                if (strpos($trimmed, "{$key} ") === 0 || strpos($trimmed, "{$key}=") === 0) {
                    $result[] = "{$key} = {$value}";
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                $result[] = $line;
            }
        }

        return implode("\n", $result) . "\n";
    }

    private function runMigrations(): array
    {
        $result = ['method' => '', 'output' => ''];

        $think = $this->rootPath . '/think';
        if (function_exists('exec')) {
            $migrateCmd = escapeshellcmd("php {$think} migrate:run") . ' 2>&1';
            $output = [];
            $exitCode = 0;
            exec($migrateCmd, $output, $exitCode);
            $result['method'] = 'exec';
            $result['output'] = implode("\n", $output) ?: 'ok';
            $result['exitCode'] = $exitCode;
            return $result;
        }

        // fallback: 手动执行 SQL
        $result['method'] = 'manual';
        $migrationDir = $this->rootPath . '/database/migrations';
        $files = glob($migrationDir . '/*.php');
        sort($files);

        foreach ($files as $file) {
            $className = $this->getMigrationClass($file);
            if (!$className) continue;
            require_once $file;
            if (!class_exists($className)) continue;

            $migration = new $className();
            $method = null;
            if (method_exists($migration, 'up')) {
                $method = 'up';
            } elseif (method_exists($migration, 'change')) {
                $method = 'change';
            }
            if ($method) {
                try {
                    $migration->$method();
                } catch (\Throwable $e) {
                    $result['output'] .= basename($file) . ': SKIP (' . $e->getMessage() . ")\n";
                }
            }
        }
        if (empty($result['output'])) {
            $result['output'] = 'ok';
        }
        return $result;
    }

    private function getMigrationClass(string $file): ?string
    {
        $content = file_get_contents($file);
        if (preg_match('/class\s+(\w+)\s/', $content, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function runSeed(array $data): void
    {
        $admin = Db::name('user')->where('userName', 'admin')->find();
        if (!$admin) {
            $tablePrefix = env('DB_PREFIX', 'rc_');
            // 尝试创建 user 表（如果迁移没跑成）
            try {
                Db::execute("CREATE TABLE IF NOT EXISTS `{$tablePrefix}user` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `userName` VARCHAR(50) NOT NULL,
                    `nickName` VARCHAR(50) DEFAULT '',
                    `password` VARCHAR(255) NOT NULL,
                    `authority` TINYINT DEFAULT 1,
                    `email` VARCHAR(100) DEFAULT '',
                    `rCoin` DECIMAL(10,2) DEFAULT 0,
                    `userInfo` TEXT,
                    `status` TINYINT DEFAULT 1,
                    `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (\Throwable $e) {
                // 表可能已存在
            }

            Db::name('user')->insert([
                'userName' => 'admin',
                'nickName' => 'admin',
                'password' => password_hash($data['admin_pass'], PASSWORD_DEFAULT),
                'authority' => 0,
                'email' => $data['admin_email'],
                'rCoin' => 0,
            ]);
        }

        // 尝试创建 config 表
        $tablePrefix = env('DB_PREFIX', 'rc_');
        try {
            Db::execute("CREATE TABLE IF NOT EXISTS `{$tablePrefix}config` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(100) NOT NULL,
                `value` TEXT,
                `appName` VARCHAR(50) DEFAULT 'media',
                `type` TINYINT DEFAULT 1,
                `status` TINYINT DEFAULT 1,
                `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            // 表可能已存在
        }

        $defaults = [
            'avableRegisterCount' => env('REGISTER_OPEN', true) ? '-1' : '0',
            'chargeRate' => '1',
            'sysnotificiations' => '您有一条新消息：{Message}',
            'findPasswordTemplate' => '您的找回密码链接是：<a href="{Url}">{Url}</a>',
            'verifyCodeTemplate' => '您的验证码是：{Code}',
            'clientList' => '[]',
            'clientBlackList' => '[]',
            'maxActiveDeviceCount' => '0',
            'signInMaxAmount' => '0',
            'signInMinAmount' => '0',
            'telegramRules' => '[]',
            'privacyPolicy' => '',
            'userAgreement' => '',
        ];

        foreach ($defaults as $key => $value) {
            $exists = Db::name('config')->where('key', $key)->find();
            if (!$exists && $value !== '') {
                Db::name('config')->insert([
                    'key' => $key,
                    'value' => $value,
                    'appName' => 'media',
                    'type' => 1,
                    'status' => 1,
                ]);
            }
        }
    }

    private function registerQueueWorker(): array
    {
        $result = ['written' => false, 'running' => false];

        $confDir = '/etc/supervisor.d';
        if (!is_dir($confDir)) {
            $result['error'] = "supervisor.d 目录不存在，请在 1Panel 中手动添加 queue:work 进程";
            return $result;
        }

        $iniPath = $confDir . '/queue-worker.ini';
        $projectPath = $this->rootPath;
        $logPath = $projectPath . '/runtime/log';

        $ini = <<<INI
[program:queue-worker]
command=php {$projectPath}/think queue:work --queue main --tries 3 --sleep 3
directory={$projectPath}
autostart=true
autorestart=true
user=www-data
stdout_logfile={$logPath}/queue-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
stderr_logfile={$logPath}/queue-worker-error.log
stderr_logfile_maxbytes=10MB
stderr_logfile_backups=3
INI;

        file_put_contents($iniPath, $ini);
        $result['written'] = true;

        // 重新加载 supervisor 配置
        if (function_exists('exec')) {
            $cmds = [
                'supervisorctl reread 2>&1',
                'supervisorctl update 2>&1',
                'supervisorctl start queue-worker 2>&1',
            ];
            foreach ($cmds as $cmd) {
                $out = [];
                exec(escapeshellcmd($cmd), $out);
                $result['cmd_output'][] = $cmd . ': ' . implode("\n", $out);
            }
            $result['running'] = true;
        } else {
            $result['cmd_output'][] = '请手动执行: supervisorctl reread && supervisorctl update && supervisorctl start queue-worker';
        }

        return $result;
    }
}
