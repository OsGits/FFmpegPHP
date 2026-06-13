<?php
/**
 * 密码验证模块
 * 用于保护管理后台免受未授权访问
 */

// 密码验证相关常量
define('AUTH_TIMEOUT', 30 * 60); // 30分钟

// 获取密码
function get_password() {
    $config_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';
    if (file_exists($config_file)) {
        @include $config_file;
        if (isset($saved_config['admin_password']) && !empty($saved_config['admin_password'])) {
            return $saved_config['admin_password'];
        }
    }
    return null;
}

// 检查是否已设置密码
function is_password_set() {
    return get_password() !== null;
}

// 验证密码（明文比对）
function verify_password($input_password) {
    $stored_password = get_password();
    if ($stored_password === null) {
        return false;
    }
    return $input_password === $stored_password;
}

// 设置密码（明文保存）
function set_password($new_password) {
    $config_file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';
    if (!file_exists($config_file)) {
        return false;
    }

    // 读取现有配置
    @include $config_file;
    if (!isset($saved_config) || !is_array($saved_config)) {
        $saved_config = [];
    }

    // 直接保存明文密码
    $saved_config['admin_password'] = $new_password;

    // 保存配置
    $php_content = '<?php
/**
 * 配置文件 - PHP 格式
 * 此文件包含敏感信息，请确保 Web 服务器禁止直接访问 .php 文件
 */

$saved_config = ' . var_export($saved_config, true) . ';
';

    return @file_put_contents($config_file, $php_content) !== false;
}

// 检查是否已认证
function is_authenticated() {
    if (!isset($_SESSION)) {
        session_start();
    }

    // 检查session中是否有认证标记和时间戳
    if (!isset($_SESSION['auth_verified']) || !isset($_SESSION['auth_time'])) {
        return false;
    }

    // 检查是否超过30分钟
    if (time() - $_SESSION['auth_time'] > AUTH_TIMEOUT) {
        // 超时，清除认证状态
        unset($_SESSION['auth_verified']);
        unset($_SESSION['auth_time']);
        return false;
    }

    return $_SESSION['auth_verified'] === true;
}

// 标记为已认证
function mark_authenticated() {
    if (!isset($_SESSION)) {
        session_start();
    }
    $_SESSION['auth_verified'] = true;
    $_SESSION['auth_time'] = time();
}

// 取消认证
function clear_authentication() {
    if (!isset($_SESSION)) {
        session_start();
    }
    unset($_SESSION['auth_verified']);
    unset($_SESSION['auth_time']);
}

// 处理登录提交
function handle_auth_post() {
    if (!isset($_SESSION)) {
        session_start();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (verify_password($_POST['password'])) {
            mark_authenticated();
            // 重定向到首页
            header('Location: ../index.php');
            exit;
        } else {
            header('Location: ../index.php?auth_error=1');
            exit;
        }
    }
}

// 处理密码修改
function handle_password_change() {
    if (!isset($_SESSION)) {
        session_start();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_new_password'] ?? '';

        // 验证当前密码
        if (!verify_password($current_password)) {
            header('Location: ../settings.php?password_msg=error_current');
            exit;
        }

        // 验证新密码长度
        if (strlen($new_password) < 4) {
            header('Location: ../settings.php?password_msg=error_length');
            exit;
        }

        // 验证两次输入一致
        if ($new_password !== $confirm_password) {
            header('Location: ../settings.php?password_msg=error_mismatch');
            exit;
        }

        // 保存新密码
        if (set_password($new_password)) {
            // 清除认证状态，需要重新登录
            clear_authentication();
            // 重定向到 settings.php 并显示成功消息
            header('Location: ../settings.php?password_msg=success');
            exit;
        } else {
            header('Location: ../settings.php?password_msg=error_save');
            exit;
        }
    }

    return '';
}

// 处理退出登录
function handle_logout() {
    if (!isset($_SESSION)) {
        session_start();
    }
    clear_authentication();
    header('Location: ../index.php');
    exit;
}

// 处理动作请求（由页面调用）
function handle_auth_action($action) {
    switch ($action) {
        case 'login':
            handle_auth_post();
            break;
        case 'change_password':
            handle_password_change();
            break;
        case 'logout':
            handle_logout();
            break;
    }
}

// 要求认证（如果未认证则显示登录页面）
function require_auth() {
    if (!is_authenticated()) {
        // 显示登录页面
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>身份验证 - FFmpegPHP</title>
    <style>
        :root {
            --primary-color: #3B82F6;
            --primary-dark: #2563EB;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            font-size: 24px;
            color: #333;
            margin-bottom: 8px;
            font-weight: 600;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.15s;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .error {
            background: #FEF2F2;
            color: #DC2626;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border-left: 4px solid #EF4444;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>FFmpegPHP 管理后台</h1>
            <p>请输入访问密码</p>
        </div>
        <?php if (isset($_GET['error'])): ?>
        <div class="error">密码错误，请重试</div>
        <?php endif; ?>
        <form method="post" action="../index.php?action=login">
            <div class="form-group">
                <label for="password">访问密码</label>
                <input type="password" id="password" name="password" placeholder="请输入访问密码" required autofocus>
            </div>
            <button type="submit" class="btn">确认访问</button>
        </form>
    </div>
</body>
</html>
        <?php
        exit;
    }
}
