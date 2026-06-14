<?php
// 跨平台兼容性检查脚本
// 用于验证代码在Windows和Linux下的兼容性

// 检查配置文件是否存在
$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';
$config_exists = file_exists($config_file);

// 开始输出缓冲
ob_start();

echo "=== 跨平台兼容性检查 ===\n\n";

// 1. 检查PHP版本
$php_version = PHP_VERSION;
$php_ok = version_compare(PHP_VERSION, '7.0.0', '>=');
echo "1. PHP版本检查\n";
echo "   PHP版本: " . PHP_VERSION . "\n";
echo "   推荐版本: 7.0或更高\n";
echo "   " . ($php_ok ? "✅ 符合要求" : "❌ 需要升级") . "\n\n";

// 2. 检查操作系统
$os = PHP_OS;
$is_windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$os_type = $is_windows ? "Windows" : "Unix/Linux";
echo "2. 操作系统检查\n";
echo "   操作系统: " . PHP_OS . "\n";
echo "   操作系统类型: " . $os_type . "\n\n";

// 3. 检查必需的函数
$required_functions = ['exec', 'json_encode', 'json_decode', 'file_get_contents', 'file_put_contents'];
$functions_status = [];
echo "3. 必需函数检查\n";
foreach ($required_functions as $func) {
    $available = function_exists($func);
    $functions_status[$func] = $available;
    echo "   $func: " . ($available ? "✅ 可用" : "❌ 不可用") . "\n";
}
echo "\n";

// 4. 检查必需的扩展
$required_extensions = ['json', 'mbstring'];
$extensions_status = [];
echo "4. 必需扩展检查\n";
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    $extensions_status[$ext] = $loaded;
    echo "   $ext: " . ($loaded ? "✅ 已加载" : "❌ 未加载") . "\n";
}
echo "\n";

// 5. 检查目录权限
$ds = DIRECTORY_SEPARATOR;
$dirs_to_check = [
    'vodoss' => __DIR__ . $ds . 'vodoss',
    'm3u8' => __DIR__ . $ds . 'm3u8',
    'ZmFinish' => __DIR__ . $ds . 'ZmFinish',
    'data' => __DIR__ . $ds . 'data'
];
$dirs_status = [];

echo "5. 目录权限检查\n";
foreach ($dirs_to_check as $name => $dir) {
    $dir_exists = is_dir($dir);
    $dir_writable = $dir_exists && is_writable($dir);
    $dirs_status[$name] = ['exists' => $dir_exists, 'writable' => $dir_writable, 'path' => $dir];
    echo "   $dir: " . ($dir_exists ? "存在" : "不存在");
    echo ", " . ($dir_writable ? "可写✅" : "不可写❌") . "\n";

    // 如果目录不存在，尝试创建
    if (!$dir_exists) {
        echo "   尝试创建目录...";
        if (@mkdir($dir, 0755, true)) {
            $dirs_status[$name]['exists'] = true;
            $dirs_status[$name]['writable'] = true;
            echo "成功✅\n";
        } else {
            echo "失败❌\n";
        }
    }
}

// 检查根目录是否可写
$root_writable = is_writable(__DIR__);
echo "\n6. 根目录可写性检查\n";
echo "   根目录 (".__DIR__."): " . ($root_writable ? "可写✅" : "不可写❌") . "\n";

// 检查配置文件状态
$config_path = '';
$config_file_exists = false;
$ffmpeg_path = 'ffmpeg';
$ffprobe_path = 'ffprobe';
$is_windows_check = $is_windows;

if ($config_exists) {
    require_once __DIR__ . '/config.php';
    $config_path = CONFIG_FILE_PATH;
    $config_file_exists = file_exists(CONFIG_FILE_PATH);
    $ffmpeg_path = defined('FFMPEG_PATH') ? FFMPEG_PATH : 'ffmpeg';
    $ffprobe_path = defined('FFPROBE_PATH') ? FFPROBE_PATH : 'ffprobe';
    $is_windows_check = defined('IS_WINDOWS') ? IS_WINDOWS : $is_windows;
}

echo "\n7. 配置文件检查\n";
echo "   配置文件路径: " . $config_path . "\n";
echo "   配置文件存在: " . ($config_file_exists ? "是✅" : "否❌") . "\n";
echo "\n";

// 8. 检查转码记录文件
$ting_file = '';
$ting_file_exists = false;
$ting_file_size = 0;
$ting_records_count = 0;

if ($config_exists) {
    require_once __DIR__ . '/includes/functions.php';
    $ting_file = get_transcode_record_file();
    $ting_file_exists = file_exists($ting_file);
    echo "8. 转码记录文件检查\n";
    echo "   记录文件路径: " . $ting_file . "\n";
    echo "   记录文件存在: " . ($ting_file_exists ? "是✅" : "否❌") . "\n";
    if ($ting_file_exists) {
        $ting_file_size = filesize($ting_file);
        echo "   文件大小: " . $ting_file_size . " 字节\n";
        if ($ting_file_size > 0) {
            $content = @file_get_contents($ting_file);
            $records = json_decode($content, true);
            if (is_array($records)) {
                $ting_records_count = count($records);
                echo "   记录数量: " . $ting_records_count . "\n";
            }
        }
    }
    echo "\n";
}

// 本地检查函数
function is_exec_disabled() {
    $disabled = explode(',', ini_get('disable_functions'));
    $disabled = array_map('trim', $disabled);
    return in_array('exec', $disabled);
}

// 9. 检查FFmpeg可用性
$ffmpeg_available = false;
$ffmpeg_version = '';
$ffmpeg_error = '';

echo "9. FFmpeg可用性检查\n";
echo "   FFmpeg路径: " . $ffmpeg_path . "\n";

if (is_exec_disabled()) {
    $ffmpeg_error = 'exec()函数被禁用';
    echo "   FFmpeg: ❌ 无法检查（exec()函数被禁用）\n";
} else {
    $output = [];
    $return_var = 0;
    if ($is_windows_check) {
        $cmd = 'cmd /c ' . escapeshellarg($ffmpeg_path . ' -version') . ' 2>&1';
    } else {
        $cmd = $ffmpeg_path . ' -version 2>&1';
    }

    exec($cmd, $output, $return_var);

    if ($return_var === 0) {
        $ffmpeg_available = true;
        echo "   FFmpeg: ✅ 可用\n";
        if (!empty($output)) {
            $ffmpeg_version = trim($output[0]);
            echo "   版本: " . $ffmpeg_version . "\n";
        }
    } else {
        $ffmpeg_error = implode("\n", $output);
        echo "   FFmpeg: ❌ 不可用\n";
        echo "   错误信息:\n";
        foreach ($output as $line) {
            echo "      $line\n";
        }
    }
}
echo "\n";

// 10. 检查FFprobe可用性
$ffprobe_available = false;
$ffprobe_error = '';

echo "10. FFprobe可用性检查\n";
echo "   FFprobe路径: " . $ffprobe_path . "\n";

if (is_exec_disabled()) {
    $ffprobe_error = 'exec()函数被禁用';
    echo "   FFprobe: ❌ 无法检查（exec()函数被禁用）\n";
} else {
    if ($is_windows_check) {
        $cmd = 'cmd /c ' . escapeshellarg($ffprobe_path . ' -version') . ' 2>&1';
    } else {
        $cmd = $ffprobe_path . ' -version 2>&1';
    }

    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);

    if ($return_var === 0) {
        $ffprobe_available = true;
        echo "   FFprobe: ✅ 可用\n";
    } else {
        $ffprobe_error = implode("\n", $output);
        echo "   FFprobe: ⚠️ 不可用（可选，但推荐）\n";
    }
}
echo "\n";

// 11. 总结
echo "=== 检查完成 ===\n";
echo "如果所有标记为✅的项目都通过，则代码应该能正常运行。\n";
echo "如果有❌的项目，请根据提示解决相关问题。\n";

// 获取缓冲内容
$console_output = ob_get_clean();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统兼容性检查 - FFmpegPHP</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .header p {
            opacity: 0.9;
            font-size: 1rem;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            padding: 15px 20px;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 {
            font-size: 1.1rem;
            color: #333;
            font-weight: 600;
        }

        .card-header .icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .card-body {
            padding: 20px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }

        .status-item.success {
            border-left-color: #10b981;
            background: #ecfdf5;
        }

        .status-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }

        .status-item.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }

        .status-label {
            font-weight: 500;
            color: #374151;
        }

        .status-value {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            color: #6b7280;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #059669;
        }

        .badge-error {
            background: #fee2e2;
            color: #dc2626;
        }

        .badge-warning {
            background: #fef3c7;
            color: #d97706;
        }

        .badge-info {
            background: #dbeafe;
            color: #2563eb;
        }

        .dir-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .dir-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .dir-name {
            font-weight: 600;
            color: #374151;
        }

        .dir-path {
            font-size: 0.85rem;
            color: #6b7280;
            font-family: monospace;
            margin-top: 4px;
        }

        .dir-status {
            display: flex;
            gap: 8px;
        }

        .func-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }

        .func-item {
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .func-name {
            font-family: monospace;
            font-size: 0.9rem;
            color: #374151;
        }

        .summary-card {
            background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
            color: white;
        }

        .summary-card .card-header {
            background: transparent;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .summary-card .card-header h2 {
            color: white;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            text-align: center;
        }

        .stat-item {
            padding: 15px;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .console-output {
            background: #1e1e1e;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .console-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }

        .console-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .console-dot.red { background: #ff5f56; }
        .console-dot.yellow { background: #ffbd2e; }
        .console-dot.green { background: #27c93f; }

        .console-title {
            color: #888;
            font-size: 0.85rem;
            margin-left: 10px;
        }

        .console-content {
            color: #d4d4d4;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.85rem;
            line-height: 1.6;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            transition: background 0.3s;
        }

        .back-link:hover {
            background: rgba(255,255,255,0.3);
        }

        @media (max-width: 768px) {
            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
            }

            .status-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 系统兼容性检查</h1>
            <p>检查服务器环境是否满足 FFmpegPHP 运行要求</p>
        </div>

        <!-- 总览 -->
        <div class="card summary-card">
            <div class="card-header">
                <span class="icon">📊</span>
                <h2>检查总览</h2>
            </div>
            <div class="card-body">
                <div class="summary-stats">
                    <div class="stat-item">
                        <div class="stat-number" style="color: #10b981;">
                            <?php
                            $pass_count = 0;
                            $total_count = 0;
                            if ($php_ok) $pass_count++; $total_count++;
                            foreach ($functions_status as $status) { if ($status) $pass_count++; $total_count++; }
                            foreach ($extensions_status as $status) { if ($status) $pass_count++; $total_count++; }
                            foreach ($dirs_status as $status) { if ($status['writable']) $pass_count++; $total_count++; }
                            if ($config_file_exists) $pass_count++; $total_count++;
                            if ($ffmpeg_available) $pass_count++; $total_count++;
                            if ($ffprobe_available) $pass_count++; $total_count++;
                            echo $pass_count;
                            ?>
                        </div>
                        <div class="stat-label">通过项</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: #ef4444;">
                            <?php echo $total_count - $pass_count; ?>
                        </div>
                        <div class="stat-label">失败项</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: #3b82f6;">
                            <?php echo $total_count; ?>
                        </div>
                        <div class="stat-label">总检查项</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number" style="color: <?php echo $pass_count == $total_count ? '#10b981' : '#f59e0b'; ?>;">
                            <?php echo round($pass_count / $total_count * 100); ?>%
                        </div>
                        <div class="stat-label">通过率</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PHP版本 -->
        <div class="card">
            <div class="card-header">
                <span class="icon">🐘</span>
                <h2>PHP 环境</h2>
            </div>
            <div class="card-body">
                <div class="status-grid">
                    <div class="status-item <?php echo $php_ok ? 'success' : 'error'; ?>">
                        <span class="status-label">PHP 版本</span>
                        <span class="badge <?php echo $php_ok ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $php_version; ?>
                        </span>
                    </div>
                    <div class="status-item <?php echo $php_ok ? 'success' : 'error'; ?>">
                        <span class="status-label">版本要求</span>
                        <span class="badge <?php echo $php_ok ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $php_ok ? '✅ 符合' : '❌ 需升级'; ?>
                        </span>
                    </div>
                    <div class="status-item badge-info">
                        <span class="status-label">操作系统</span>
                        <span class="badge badge-info"><?php echo $os_type; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 函数检查 -->
        <div class="card">
            <div class="card-header">
                <span class="icon">⚙️</span>
                <h2>必需函数</h2>
            </div>
            <div class="card-body">
                <div class="func-grid">
                    <?php foreach ($functions_status as $func => $available): ?>
                    <div class="func-item">
                        <span class="func-name"><?php echo $func; ?>()</span>
                        <span class="badge <?php echo $available ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $available ? '✅' : '❌'; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 扩展检查 -->
        <div class="card">
            <div class="card-header">
                <span class="icon">📦</span>
                <h2>必需扩展</h2>
            </div>
            <div class="card-body">
                <div class="func-grid">
                    <?php foreach ($extensions_status as $ext => $loaded): ?>
                    <div class="func-item">
                        <span class="func-name"><?php echo $ext; ?></span>
                        <span class="badge <?php echo $loaded ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $loaded ? '✅ 已加载' : '❌ 未加载'; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 目录权限 -->
        <div class="card">
            <div class="card-header">
                <span class="icon">📁</span>
                <h2>目录权限</h2>
            </div>
            <div class="card-body">
                <div class="dir-list">
                    <?php foreach ($dirs_status as $name => $status): ?>
                    <div class="dir-item">
                        <div>
                            <div class="dir-name"><?php echo $name; ?></div>
                            <div class="dir-path"><?php echo $status['path']; ?></div>
                        </div>
                        <div class="dir-status">
                            <span class="badge <?php echo $status['exists'] ? 'badge-success' : 'badge-error'; ?>">
                                <?php echo $status['exists'] ? '存在' : '不存在'; ?>
                            </span>
                            <span class="badge <?php echo $status['writable'] ? 'badge-success' : 'badge-error'; ?>">
                                <?php echo $status['writable'] ? '可写' : '不可写'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- FFmpeg 检查 -->
        <div class="card">
            <div class="card-header">
                <span class="icon">🎬</span>
                <h2>FFmpeg 环境(可后期设置页配置)</h2>
            </div>
            <div class="card-body">
                <div class="status-grid">
                    <div class="status-item <?php echo $ffmpeg_available ? 'success' : ($ffmpeg_error ? 'error' : 'warning'); ?>">
                        <span class="status-label">FFmpeg</span>
                        <span class="badge <?php echo $ffmpeg_available ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $ffmpeg_available ? '✅ 可用' : '❌ 不可用'; ?>
                        </span>
                    </div>
                    <div class="status-item <?php echo $ffprobe_available ? 'success' : 'warning'; ?>">
                        <span class="status-label">FFprobe</span>
                        <span class="badge <?php echo $ffprobe_available ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo $ffprobe_available ? '✅ 可用' : '⚠️ 不可用'; ?>
                        </span>
                    </div>
                </div>
                <?php if ($ffmpeg_version): ?>
                <div style="margin-top: 15px; padding: 10px; background: #f0fdf4; border-radius: 8px; font-family: monospace; font-size: 0.85rem; color: #166534;">
                    <strong>版本:</strong> <?php echo htmlspecialchars($ffmpeg_version); ?>
                </div>
                <?php endif; ?>
                <?php if ($ffmpeg_error && !$ffmpeg_available): ?>
                <div style="margin-top: 15px; padding: 10px; background: #fef2f2; border-radius: 8px; font-family: monospace; font-size: 0.85rem; color: #991b1b;">
                    <strong>错误:</strong> <?php echo htmlspecialchars($ffmpeg_error); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 配置文件 -->
        <div class="card">
            <div class="card-header">
                <span class="icon">⚙️</span>
                <h2>配置文件</h2>
            </div>
            <div class="card-body">
                <div class="status-grid">
                    <div class="status-item <?php echo $config_file_exists ? 'success' : 'error'; ?>">
                        <span class="status-label">配置文件</span>
                        <span class="badge <?php echo $config_file_exists ? 'badge-success' : 'badge-error'; ?>">
                            <?php echo $config_file_exists ? '✅ 已存在' : '❌ 系统还未安装'; ?>
                        </span>
                    </div>
                    <?php if ($ting_file): ?>
                    <div class="status-item <?php echo $ting_file_exists ? 'success' : 'warning'; ?>">
                        <span class="status-label">转码记录</span>
                        <span class="badge <?php echo $ting_file_exists ? 'badge-success' : 'badge-warning'; ?>">
                            <?php echo $ting_records_count; ?> 条记录
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 控制台输出 -->
        <div class="card">
            <div class="card-header">
                <span class="icon">💻</span>
                <h2>控制台输出</h2>
            </div>
            <div class="card-body">
                <div class="console-output">
                    <div class="console-header">
                        <span class="console-dot red"></span>
                        <span class="console-dot yellow"></span>
                        <span class="console-dot green"></span>
                        <span class="console-title">Terminal</span>
                    </div>
                    <div class="console-content"><?php echo htmlspecialchars($console_output); ?></div>
                </div>
            </div>
        </div>

        <!-- 返回链接 -->
        <div style="text-align: center; margin-top: 20px;">
            <a href="index.php" class="back-link">
                ← 返回首页
            </a>
        </div>
    </div>
</body>
</html>
