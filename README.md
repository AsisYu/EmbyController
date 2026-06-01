# EMBY 影视管理系统

## 演示以及最新Beta功能

- [影视管理-算艺轩](https://randallanjie.com/media)
- [TG群组](https://t.me/randall_home)

## 概述

该项目是一个用于管理EMBY的影视管理系统，提供了用户注册、登录、密码找回、最近更新显示、影片评价系统、EMBY账号管理、激活、改密、查看影视站线路、测速、会话查看、工单、充值、签到、邮箱和Telegram机器人通知等功能。

## 安装

[见项目Wiki](https://github.com/RandallAnjie/EmbyController/wiki/InstallDoc)

## 功能

- **用户管理**：注册、登录、密码找回
- **影视管理**：显示最近更新、影片评价系统
- **EMBY账号管理**：账号创建、激活、改密
- **站点管理**：查看影视站线路、测速
- **会话管理**：查看活跃会话
- **工单系统**：提交和管理支持工单
- **充值**：用户账号充值
- **签到系统**：每日签到获取奖励
- **通知**：通过邮箱和Telegram机器人接收通知

## 预览

### 首页

![](image/index1.png)
![](image/index2.png)
![](image/index3.png)

### 控制台

![](image/dashboard.png)

### 用户中心

![](image/user-config.png)

### 站点账号

激活账号：
![](image/account-active.png)
未激活账号：
![](image/account-inactive.png)

### 工单系统

![](image/request-list.png)

![](image/request-detail.png)

### 充值中心

![](image/finace-pay.png)

### 影评系统

![](image/comment-detail.png)

## 使用

- **用户注册**：用户可以通过提供邮箱、用户名和密码进行注册。
- **登录**：注册用户可以使用凭证登录。
- **密码找回**：用户可以通过邮箱验证找回密码。
- **最近更新**：显示最新的影视更新。
- **影片评价**：用户可以对影片进行评分和评论。
- **EMBY账号管理**：创建、激活和修改EMBY账号密码。
- **站点线路**：查看和测试影视站线路。
- **会话管理**：查看活跃用户会话。
- **工单系统**：提交和管理支持工单。
- **充值**：用户账号充值。
- **签到系统**：每日签到获取奖励。
- **通知**：通过邮箱和Telegram机器人接收通知。

## 使用技术

- **后端**：PHP8
- **前端**：Html JavaScript Css Tailwindcss
- **数据库**：MySQL
- **框架**：ThinkPHP Layui
- **其他工具**：Composer、cURL、Cloudflare Turnstile、Telegram Bot API


## 开发

1. **克隆仓库**：
    ```sh
    git clone https://github.com/RandallAnjie/EmbyController.git
    cd EmbyController
    ```

2. **安装依赖**：
    ```sh
    composer install
    ```

3. **配置环境**：
   - 将 `example.env` 复制成 `.env` 。
   - 根据需要更新`.env`环境变量。
   - 设置数据库并更新`config`目录中的各项配置。

4. **初始化数据库**（以下两种方式任选其一）：
   - **方式A（推荐）**：运行初始化命令 `php think seed`，自动创建管理员账户和默认配置
   - **方式B**：导入 [数据库SQL文件](demomedia_2025-02-14.sql)
   - 默认用户名/密码：admin/A123456

5. **启动开发服务器**：
    ```sh
    php think run
    ```

## 贡献

1. Fork 仓库。
2. 创建新分支（`git checkout -b feature-branch`）。
3. 进行修改。
4. 提交修改（`git commit -m 'Add some feature'`）。
5. 推送到分支（`git push origin feature-branch`）。
6. 打开Pull Request。

## 许可证

该项目使用Apache许可证。详情请参阅[LICENSE](LICENSE)文件。

## 联系

如有任何问题或建议，请联系[randall@randallanjie.com](mailto:randall@randallanjie.com)。