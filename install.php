<?php

// 安装页面
// 首次运行时，如果没有配置文件，进入此页面进行初始化设置

// 检查是否已安装（如果配置文件已存在，跳转到首页）
$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';
if (file_exists($config_file)) {
    header('Location: index.php');
    exit;
}

// 默认配置
$default_config = [
    'ffmpeg_path' => 'ffmpeg',
    'ffprobe_path' => 'ffprobe',
    'input_dir' => './vodoss/',
    'output_dir' => './m3u8/',
    'base_url' => '',
    'segment_duration' => 10,
    'screenshot_time' => 10,
    'quality' => 'original',
    'use_gpu' => 0,
    'mysql_enabled' => 0,
    'mysql_host' => 'localhost',
    'mysql_port' => '3306',
    'mysql_db' => 'vod_system',
    'mysql_user' => 'root',
    'mysql_password' => '',
    'm3u8_full_url' => '',
];

$message = '';

// 密码验证相关
define('AUTH_HASH_ALGO', 'sha256');

function hash_password($password) {
    return hash(AUTH_HASH_ALGO, $password);
}

// 处理安装提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 收集表单数据
    $config = $default_config;

    // 基础设置
    $config['ffmpeg_path'] = $_POST['ffmpeg_path'] ?? 'ffmpeg';
    $config['ffprobe_path'] = $_POST['ffprobe_path'] ?? 'ffprobe';
    $config['input_dir'] = $_POST['input_dir'] ?? './vodoss/';
    $config['output_dir'] = $_POST['output_dir'] ?? './m3u8/';
    $config['base_url'] = $_POST['base_url'] ?? '';
    $config['segment_duration'] = (int)($_POST['segment_duration'] ?? 10);
    $config['screenshot_time'] = (int)($_POST['screenshot_time'] ?? 10);
    $config['quality'] = $_POST['quality'] ?? 'original';
    $config['use_gpu'] = isset($_POST['use_gpu']) ? 1 : 0;

    // 数据库设置
    $config['mysql_enabled'] = isset($_POST['mysql_enabled']) ? 1 : 0;
    $config['mysql_host'] = $_POST['mysql_host'] ?? 'localhost';
    $config['mysql_port'] = $_POST['mysql_port'] ?? '3306';
    $config['mysql_db'] = $_POST['mysql_db'] ?? 'vod_system';
    $config['mysql_user'] = $_POST['mysql_user'] ?? 'root';
    $config['mysql_password'] = $_POST['mysql_password'] ?? '';
    $config['m3u8_full_url'] = $_POST['m3u8_full_url'] ?? '';

    // 管理员密码（必填）
    $admin_password = $_POST['admin_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 验证密码
    if (empty($admin_password)) {
        $message = '<div class="error">请设置管理员访问密码</div>';
    } elseif (strlen($admin_password) < 4) {
        $message = '<div class="error">密码长度至少4位</div>';
    } elseif ($admin_password !== $confirm_password) {
        $message = '<div class="error">两次输入的密码不一致</div>';
    } else {
        // 直接保存明文密码
        $config['admin_password'] = $admin_password;

        // 创建配置文件
        $php_content = '<?php
/**
 * 配置文件 - PHP 格式
 * 此文件包含敏感信息，请确保 Web 服务器禁止直接访问 .php 文件
 */

$saved_config = ' . var_export($config, true) . ';
';

        // 确保目录存在
        $dir = dirname($config_file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 保存配置文件
        if (file_put_contents($config_file, $php_content)) {
            $message = '<div class="success">安装成功！正在跳转到首页...</div>';
            echo $message;
            echo '<script>setTimeout(function(){ window.location.href="index.php"; }, 2000);</script>';
            exit;
        } else {
            $message = '<div class="error">安装失败：无法创建配置文件，请检查目录权限</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FFmpegPHP - 首次安装</title>
    <style>
        :root {
            --primary-color: #3B82F6;
            --primary-dark: #2563EB;
            --success-color: #10B981;
            --danger-color: #EF4444;
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
            --bg-page: #F3F4F6;
            --bg-card: #FFFFFF;
            --border-color: #E5E7EB;
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-page);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 30px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .card h2 {
            font-size: 18px;
            color: var(--text-primary);
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary-color);
            display: inline-block;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 14px;
            transition: all 0.15s ease;
            background-color: var(--bg-card);
            color: var(--text-primary);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .form-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
            margin-right: 8px;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: var(--text-secondary);
            font-size: 12px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: normal;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.15s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .success {
            background-color: #ECFDF5;
            color: #059669;
            padding: 14px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border-left: 4px solid var(--success-color);
            font-size: 14px;
        }

        .error {
            background-color: #FEF2F2;
            color: #DC2626;
            padding: 14px;
            border-radius: var(--radius-md);
            margin-bottom: 20px;
            border-left: 4px solid var(--danger-color);
            font-size: 14px;
        }

        .tab-navigation {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border-color);
            gap: 4px;
        }

        .tab-button {
            background-color: var(--bg-page);
            border: none;
            border-bottom: 3px solid transparent;
            padding: 12px 20px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s ease;
            color: var(--text-secondary);
            border-radius: var(--radius-md) var(--radius-md) 0 0;
        }

        .tab-button:hover {
            background-color: #E5E7EB;
            color: var(--text-primary);
        }

        .tab-button.active {
            background-color: var(--bg-card);
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .password-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 24px;
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
        }

        .password-section h3 {
            color: white;
            font-size: 16px;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .password-section p {
            color: rgba(255,255,255,0.9);
            font-size: 13px;
            margin-bottom: 16px;
        }

        .password-section .form-group {
            margin-bottom: 0;
        }

        .password-section input {
            background: white;
            border: none;
        }

        .info-box {
            background-color: #EFF6FF;
            border-left: 4px solid var(--primary-color);
            padding: 14px;
            border-radius: var(--radius-md);
            font-size: 13px;
            color: var(--text-secondary);
        }

        .info-box strong {
            color: var(--text-primary);
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px 16px;
            }

            .card {
                padding: 20px;
            }

            .tab-navigation {
                flex-direction: column;
                border-bottom: none;
            }

            .tab-button {
                border-radius: var(--radius-md);
                border-bottom: none;
                border-left: 3px solid transparent;
            }

            .tab-button.active {
                border-left-color: var(--primary-color);
                border-bottom: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>FFmpegPHP 安装向导</h1>
            <p>欢迎使用 FFmpegPHP 视频转码系统，请完成以下配置</p>
        </div>

        <div class="card">
            <h2>安装配置</h2>

            <?php echo $message; ?>

            <form method="post">
                <!-- 密码设置（必填） -->
                <div class="password-section">
                    <h3>🔐 管理员访问密码（必填）</h3>
                    <p>请设置访问密码，用于保护管理后台安全。30分钟内无需重复输入。</p>
                    <div class="form-group">
                        <label for="admin_password" style="color: white;">设置访问密码</label>
                        <input type="password" id="admin_password" name="admin_password" placeholder="请输入访问密码（至少4位）" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" style="color: white;">确认访问密码</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="请再次输入密码" required>
                    </div>
                </div>

                <!-- 选项卡导航 -->
                <div class="tab-navigation">
                    <button type="button" class="tab-button active" onclick="openTab(event, 'basic-settings')">基础设置</button>
                    <button type="button" class="tab-button" onclick="openTab(event, 'database-settings')">数据库设置</button>
                </div>

                <!-- 基础设置 -->
                <div id="basic-settings" class="tab-content active">
                    <div class="form-group">
                        <label for="ffmpeg_path">FFmpeg路径 (必填)</label>
                        <input type="text" id="ffmpeg_path" name="ffmpeg_path" value="ffmpeg" placeholder="例如: C:/ffmpeg/bin/ffmpeg.exe">
                        <small>如果已添加到系统PATH，可直接使用 'ffmpeg'</small>
                    </div>

                    <div class="form-group">
                        <label for="ffprobe_path">FFprobe路径 (必填)</label>
                        <input type="text" id="ffprobe_path" name="ffprobe_path" value="ffprobe" placeholder="例如: C:/ffmpeg/bin/ffprobe.exe">
                        <small>用于获取视频信息，可选但推荐配置</small>
                    </div>

                    <div class="form-group">
                        <label for="input_dir">待转码目录 (必填)</label>
                        <input type="text" id="input_dir" name="input_dir" value="./vodoss/" placeholder="./vodoss/">
                        <small>存放需要转码的视频文件的目录</small>
                    </div>

                    <div class="form-group">
                        <label for="output_dir">转码后保存目录 (必填)</label>
                        <input type="text" id="output_dir" name="output_dir" value="./m3u8/" placeholder="./m3u8/">
                        <small>存放转码完成后的文件的目录</small>
                    </div>

                    <div class="form-group">
                        <label for="base_url">TS文件路径设置 (必填)</label>
                        <input type="text" id="base_url" name="base_url" placeholder="例如: http://your-domain/m3u8/">
                        <small>TS文件的基础访问地址</small>
                    </div>

                    <div class="form-group">
                        <label for="segment_duration">切片时长 (秒)</label>
                        <input type="number" id="segment_duration" name="segment_duration" value="10" min="1" max="60">
                        <small>每个TS切片的时长，默认为10秒</small>
                    </div>

                    <div class="form-group">
                        <label for="screenshot_time">截图时间点 (秒)</label>
                        <input type="number" id="screenshot_time" name="screenshot_time" value="10" min="0">
                        <small>视频截图的时间点，默认为10秒</small>
                    </div>

                    <div class="form-group">
                        <label for="quality">画质选择</label>
                        <select id="quality" name="quality">
                            <option value="original" selected>原画质</option>
                            <option value="1080p">1080P</option>
                            <option value="720p">720P</option>
                        </select>
                        <small>选择转码后的视频画质</small>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="use_gpu" name="use_gpu" value="1">
                            使用GPU加速
                        </label>
                        <small>需要系统支持GPU加速</small>
                    </div>
                </div>

                <!-- 数据库设置 -->
                <div id="database-settings" class="tab-content">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="mysql_enabled" name="mysql_enabled" value="1">
                            启用MySQL数据库
                        </label>
                        <small>勾选后启用MySQL数据库功能</small>
                    </div>

                    <div class="form-group">
                        <label for="mysql_host">数据库IP</label>
                        <input type="text" id="mysql_host" name="mysql_host" value="localhost" placeholder="例如: localhost">
                        <small>MySQL数据库服务器的IP地址</small>
                    </div>

                    <div class="form-group">
                        <label for="mysql_port">数据库端口</label>
                        <input type="number" id="mysql_port" name="mysql_port" value="3306" min="1" max="65535">
                        <small>MySQL数据库服务器的端口，默认为3306</small>
                    </div>

                    <div class="form-group">
                        <label for="mysql_db">数据库名称</label>
                        <input type="text" id="mysql_db" name="mysql_db" value="vod_system" placeholder="例如: vod_system">
                        <small>要使用的数据库名称</small>
                    </div>

                    <div class="form-group">
                        <label for="mysql_user">用户账号</label>
                        <input type="text" id="mysql_user" name="mysql_user" value="root" placeholder="例如: root">
                        <small>MySQL数据库的用户名</small>
                    </div>

                    <div class="form-group">
                        <label for="mysql_password">数据库密码</label>
                        <input type="password" id="mysql_password" name="mysql_password" placeholder="输入数据库密码">
                        <small>MySQL数据库的密码</small>
                    </div>

                    <div class="form-group">
                        <label for="m3u8_full_url">m3u8完整链接</label>
                        <input type="text" id="m3u8_full_url" name="m3u8_full_url" placeholder="例如: http://your-domain/m3u8/">
                        <small>m3u8文件的完整访问链接</small>
                    </div>
                </div>

                <div class="info-box" style="margin-top: 20px;">
                    <strong>💡 提示：</strong> 安装完成后，每次访问管理后台都需要输入访问密码。密码将在30分钟后需要重新验证。
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary">完成安装</button>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>安装说明</h2>
            <ul style="margin-left: 20px; color: var(--text-secondary);">
                <li><strong>管理员密码</strong>：必填项，用于保护管理后台安全</li>
                <li><strong>FFmpeg路径</strong>：如果已添加到系统PATH，可直接使用 'ffmpeg'</li>
                <li><strong>目录配置</strong>：使用相对路径，以 ./ 开头，表示项目根目录</li>
                <li><strong>数据库</strong>：可选功能，用于保存转码记录到MySQL数据库</li>
                <li><strong>配置文件</strong>：安装完成后，配置将保存到 data/config.php 文件</li>
            </ul>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var tabContents = document.getElementsByClassName("tab-content");
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].style.display = "none";
                tabContents[i].classList.remove("active");
            }

            var tabButtons = document.getElementsByClassName("tab-button");
            for (var i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove("active");
            }

            document.getElementById(tabName).style.display = "block";
            document.getElementById(tabName).classList.add("active");
            evt.currentTarget.classList.add("active");
        }
    </script>
</body>
</html>
