<?php
// 自动转码脚本 - 跨平台优化版

// 检查是否需要安装（如果没有配置文件，重定向到安装页面）
$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';
if (!file_exists($config_file)) {
    header('Location: install.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . DS . 'includes/functions.php';

// 读取转码记录
$ting_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ting.json';
$transcode_records = [];
if (file_exists($ting_file)) {
    $transcode_records = json_decode(file_get_contents($ting_file), true) ?? [];
}

// 检查是否有正在进行的转码任务
$current_task = get_current_transcode_task();

// 如果有任务，显示任务状态并退出（只在空闲时才获取视频文件进行转码）
if (!empty($current_task)) {
    echo '<div class="info">自动转码中，请不要离开当前页面！</div>';
    echo '<p>正在转码: ' . htmlspecialchars($current_task['filename']) . '</p>';
    echo '<p>开始时间: ' . htmlspecialchars($current_task['start_time']) . '</p>';
} else {
    // 扫描vodoss目录获取视频文件
    $video_files = [];
    $dir = opendir(UPLOAD_DIR);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $file_utf8 = safe_readdir($file);
            if (validate_extension($file_utf8)) {
                $video_files[] = $file;
            }
        }
    }
    closedir($dir);
    
    if (!empty($video_files)) {
        $selected_file = $video_files[0];
        
        set_time_limit(3600);
        ini_set('memory_limit', '-1');
        
        $base_url = $config['base_url'] ?? '';
        $segment_duration = $config['segment_duration'] ?? 10;
        $screenshot_time = $config['screenshot_time'] ?? 10;
        $quality = $config['quality'] ?? '1080p';
        $output_dir = 'm3u8';
        $use_gpu = isset($config['use_gpu']) && $config['use_gpu'] == 1;
        
        process_single_file($selected_file, $output_dir, $base_url, $segment_duration, $screenshot_time, $quality, $use_gpu, true, false);
    } else {
        echo '<div class="info">vodoss目录中没有找到视频文件</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>自动转码</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
            padding-top: 80px;
        }
        .card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        h2 {
            color: #333;
            margin-top: 0;
        }
        .error {
            color: red;
            background-color: #ffebee;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .success {
            color: green;
            background-color: #e8f5e8;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .info {
            color: blue;
            background-color: #e3f2fd;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div style="position: fixed; top: 20px; left: 20px; z-index: 1000;">
        <a href="transcode.php" style="display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px; font-weight: bold;">返回目录管理</a>
    </div>
    
    <div class="card">
        <h2>自动转码状态</h2>
        <p>系统会自动检查转码任务状态</p>
        <p>当前时间: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    
    <div class="card">
        <h2>转码记录</h2>
        <?php if (!empty($transcode_records)): ?>
            <table style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f5f5f5;">
                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">文件名</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">转码时间</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 1px solid #ddd;">状态</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($transcode_records) as $record): ?>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($record['filename'] ?? ''); ?></td>
                            <td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo htmlspecialchars($record['end_time'] ?? ''); ?></td>
                            <td style="padding: 10px; border-bottom: 1px solid #eee;"><?php echo !empty($record['end_time']) ? '已完成' : '转码中'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>暂无转码记录</p>
        <?php endif; ?>
    </div>
    

</body>
</html>
