<?php

namespace app\media\model;

use app\media\model\SysConfigModel as SysConfigModel;
use think\Model;

class UserModel extends Model
{
    // 数据表名（不含前缀）
    protected $name = 'user';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'createdAt' => 'timestamp',
        'updatedAt' => 'timestamp',
        'userName' => 'varchar',
        'nickName' => 'varchar',
        'password' => 'varchar',
        'authority' => 'int',
        'email' => 'varchar',
        'rCoin' => 'double',
        'userInfo' => 'text',
    ];

    // 设置JSON字段（如果有的话）
    protected $json = ['userInfo'];

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'timestamp'; // 自动写入时间戳
    protected $createTime = 'createdAt'; // 创建时间字段名
    protected $updateTime = 'updatedAt'; // 更新时间字段名

    // 数据表主键 复合主键使用数组定义 不设置则自动获取
    protected $pk = 'id';

    public function judgeUser($userName, $password)
    {

        $user = $this->where('userName', $userName)->find();

        if (!$user) {
            $user = $this->where('email', $userName)->find();
        }

        if ($user && password_verify($password, $user->password)) {
            return $user;
        }

        return null;
    }

    public function updatePassword($id, $password)
    {
        $user = $this->where([
            'id' => $id,
        ])->find();
        if ($user) {
            $user->password = password_hash($password, PASSWORD_DEFAULT);
            $user->save();
        }
    }

    public function updateUserInfo($id, $userInfo)
    {
        $user = $this->where([
            'id' => $id,
        ])->find();
        if ($user) {
            $user->userInfo = $userInfo;
            $user->save();
        }
    }

    public function getNickName($id)
    {
        $user = $this->where([
            'id' => $id,
        ])->find();
        if ($user) {
            return ($user->nickName && $user->nickName != '') ? $user->nickName : $user->userName;
        } else {
            return '未知用户';
        }
    }

    public function registerUser(mixed $username, mixed $password, mixed $email)
    {
        $user = $this->where('userName', $username)->find();
        if ($user) {
            return [
                'error' => '用户名已存在',
                'user' => null
            ];
        }

        $user = $this->where('email', $email)->find();
        if ($user) {
            return [
                'error' => '邮箱已存在',
                'user' => null
            ];
        }

        $user = new UserModel();
        $user->userName = $username;
        $user->nickName = $username;
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->rCoin = 0;
        $user->email = $email;

        if (!env('REGISTER_OPEN', true)) {
            return [
                'error' => '注册已关闭（管理员已关闭注册功能）',
                'user' => null
            ];
        }

        $sysConfigModel = new SysConfigModel();
        // 注册用户后可用注册数减一
        if (!$sysConfigModel->where('key', 'avableRegisterCount')->find()) {
            $sysConfigModel->save([
                'key' => 'avableRegisterCount',
                'value' => '0'
            ]);
        }

        $config = $sysConfigModel->where('key', 'avableRegisterCount')->find();
        if ($config) {
            if ((int)$config->value <= 0 && (int)$config->value != -1) {
                return [
                    'error' => '注册数已用完',
                    'user' => null
                ];
            } else if ((int)$config->value != -1) {
                $currentValue = (int)$config->value;
                $newValue = $currentValue - 1;
                $config->value = (string)$newValue;
                $config->save();
            }
            $user->save();
        }

        return [
            'error' => null,
            'user' => $user
        ];
    }

    public function updateUser(mixed $id, mixed $data)
    {
        $user = $this->where('id', $id)->find();
        if ($user) {
            if (isset($data['username']) && $data['username'] != $user->userName) {
                if ($this->where('userName', $data['username'])->find()) {
                    return [
                        'error' => '用户名已存在',
                        'user' => null
                    ];
                } else {
                    $user->userName = $data['username'];
                }
            }
            if (isset($data['nickname'])) {
                $user->nickName = $data['nickname'];
            }
            if (isset($data['email']) && $data['email'] != $user->email) {
                if ($this->where('email', $data['email'])->find()) {
                    return [
                        'error' => '邮箱已存在',
                        'user' => null
                    ];
                } else {
                    $user->email = $data['email'];
                }
            }
            if (isset($data['password']) && $data['password'] != '') {
                $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
            }

//            if (isset($data['email-notificiation'])) {
//                $userInfo = json_decode(json_encode($user->userInfo), true);
//                $userInfo['banEmail'] = $data['email-notificiation']=='on'?0:1;
//                $user->userInfo = json_encode($userInfo);
//            }

            $user->save();
            $user->password = '';
            return [
                'error' => null,
                'user' => $user
            ];
        }
        return [
            'error' => '用户不存在',
            'user' => null
        ];
    }
}