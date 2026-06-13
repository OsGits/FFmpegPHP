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
        // 创建必要的目录
        require_once __DIR__ . '/config.php';
        
        // 创建上传目录和输出目录
        @mkdir(UPLOAD_DIR, 0755, true);
        @mkdir(OUTPUT_DIR, 0755, true);
        
        // 创建默认的转码记录文件 ting.json
        $record_file = dirname($config_file) . DIRECTORY_SEPARATOR . 'ting.json';
        @file_put_contents($record_file, '[]');
        
        $message = '<div class="success">安装成功！正在跳转到首页...</div>';
        // 延迟跳转
        echo $message;
        echo '<script>setTimeout(function(){ window.location.href="index.php"; }, 2000);</script>';
        exit;
    } else {
        $message = '<div class="error">安装失败：无法创建配置文件，请检查目录权限</div>';
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f7fa;
            min-height: 100vh;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .card h2 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus,
        .form-group input[type="number"]:focus,
        .form-group select:focus {
            outline: none;
            border-color: #4CAF50;
        }
        
        .form-group input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #999;
            font-size: 12px;
        }
        
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #45a049;
        }
        
        .btn-primary {
            background-color: #4CAF50;
        }
        
        .btn-primary:hover {
            background-color: #45a049;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
        }
        
        .tab-navigation {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .tab-button {
            background: none;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-size: 14px;
            border-bottom: 2px solid transparent;
            margin-right: 20px;
            color: #666;
            transition: all 0.3s;
        }
        
        .tab-button:hover {
            color: #333;
        }
        
        .tab-button.active {
            color: #4CAF50;
            border-bottom-color: #4CAF50;
            font-weight: 500;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .checkbox-label input {
            margin-right: 8px;
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
                <!-- 选项卡导航 -->
                <div class="tab-navigation">
                    <button type="button" class="tab-button active" onclick="openTab(event, 'basic-settings')">基础设置</button>
                    <button type="button" class="tab-button" onclick="openTab(event, 'database-settings')">数据库设置</button>
                </div>
                
                <!-- 基础设置 -->
                <div id="basic-settings" class="tab-content active">
                    <div class="form-group">
                        <label for="ffmpeg_path">FFmpeg路径</label>
                        <input type="text" id="ffmpeg_path" name="ffmpeg_path" value="ffmpeg" placeholder="例如: C:/ffmpeg/bin/ffmpeg.exe">
                        <small>如果已添加到系统PATH，可直接使用 'ffmpeg'</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="ffprobe_path">FFprobe路径</label>
                        <input type="text" id="ffprobe_path" name="ffprobe_path" value="ffprobe" placeholder="例如: C:/ffmpeg/bin/ffprobe.exe">
                        <small>用于获取视频信息，可选但推荐配置</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="input_dir">待转码目录</label>
                        <input type="text" id="input_dir" name="input_dir" value="./vodoss/" placeholder="./vodoss/">
                        <small>存放需要转码的视频文件的目录</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="output_dir">转码后保存目录</label>
                        <input type="text" id="output_dir" name="output_dir" value="./m3u8/" placeholder="./m3u8/">
                        <small>存放转码完成后的文件的目录</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="base_url">TS文件路径设置</label>
                        <input type="text" id="base_url" name="base_url" placeholder="例如: http://your-domain/m3u8/">
                        <small>TS文件的基础访问地址，会添加到m3u8文件中的每个TS文件路径前</small>
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
                        <small>勾选后启用MySQL数据库功能，用于保存转码记录</small>
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
                
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">完成安装</button>
                </div>
            </form>
        </div>
        
        <div class="card">
            <h2>安装说明</h2>
            <ul>
                <li><strong>FFmpeg路径</strong>：如果FFmpeg已添加到系统PATH，可直接使用 'ffmpeg'；否则需要指定完整路径。</li>
                <li><strong>目录配置</strong>：使用相对路径，以 ./ 开头，表示项目根目录。</li>
                <li><strong>数据库</strong>：可选功能，用于保存转码记录到MySQL数据库。</li>
                <li><strong>配置文件</strong>：安装完成后，配置将保存到 data/config.php 文件。</li>
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