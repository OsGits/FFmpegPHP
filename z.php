<?php
// 自动转码脚本 - 跨平台优化版

// 加载配置和函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . DS . 'includes/functions.php';

// 检查是否有正在进行的转码任务
$current_task = get_current_transcode_task();

// 如果没有任务，从vodoss目录中选择一个视频文件进行转码
if (empty($current_task)) {
    // 扫描vodoss目录获取视频文件
    $video_files = [];
    $dir = opendir(UPLOAD_DIR);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            // 使用跨平台函数处理文件名
            $file_utf8 = safe_readdir($file);
            
            if (validate_extension($file_utf8)) {
                $video_files[] = $file; // 保存原始文件名用于操作
            }
        }
    }
    closedir($dir);
    
    // 如果有视频文件，选择第一个进行转码
    if (!empty($video_files)) {
        $selected_file = $video_files[0];
        
        // 设置脚本执行时间限制为 3600 秒（1小时）
        set_time_limit(3600);
        // 禁用内存限制
        ini_set('memory_limit', '-1');
        
        // 从配置文件读取参数
        $base_url = $config['base_url'] ?? '';
        $segment_duration = $config['segment_duration'] ?? 10;
        $screenshot_time = $config['screenshot_time'] ?? 10;
        $quality = $config['quality'] ?? '1080p';
        $output_dir = 'm3u8';
        $use_gpu = isset($config['use_gpu']) && $config['use_gpu'] == 1;
        
        // 使用process_single_file函数处理
        process_single_file($selected_file, $output_dir, $base_url, $segment_duration, $screenshot_time, $quality, $use_gpu);
    } else {
        echo '<div class="info">vodoss目录中没有找到视频文件</div>';
    }
} else {
    echo '<div class="info">自动转码中，请不要离开当前页面！</div>';
    echo '<p>正在转码: ' . htmlspecialchars($current_task['filename']) . '</p>';
    echo '<p>开始时间: ' . htmlspecialchars($current_task['start_time']) . '</p>';
}

/**
 * 复用process.php中的处理函数
 */
function process_single_file($file, $output_dir, $base_url, $segment_duration, $screenshot_time, $quality, $use_gpu) {
    // 保存源文件的文件名（用于数据库和json记录）
    $original_filename = $file;
    
    // 构建最终输出目录（在output目录下创建以10位随机数字加字母命名的子目录）
    $random_dir_name = generate_random_string();
    $final_output_dir = $output_dir . '/' . $random_dir_name;
    
    // 加载硬件检测函数
    require_once __DIR__ . DS . 'includes/hardware_detection.php';
    $gpu_info = detect_gpu();
    
    // 根据use_gpu决定转码方式
    $transcode_method = $use_gpu ? $gpu_info['default'] : 'none';
    
    // 验证参数
    $errors = [];
    
    if (empty($base_url)) {
        $errors[] = 'TS文件路径设置不能为空';
    }
    
    if (empty($file)) {
        $errors[] = '请选择视频文件';
    }
    
    if (empty($output_dir)) {
        $errors[] = '请指定保存目录';
    }
    
    // 验证路径安全性
    if (!validate_path($output_dir)) {
        $errors[] = '保存目录路径不安全';
    }
    
    if (!validate_path($file)) {
        $errors[] = '视频文件路径不安全';
    }
    
    if ($segment_duration < 1 || $segment_duration > 60) {
        $errors[] = '切片时长必须在 1-60 秒之间';
    }
    
    if ($screenshot_time < 0) {
        $errors[] = '截图时间不能为负数';
    }
    
    // 生成当前年月日时分秒作为新文件名
    $timestamp = date('YmdHis');
    $file_extension = pathinfo($file, PATHINFO_EXTENSION);
    $new_filename = $timestamp . '.' . $file_extension;
    
    // 使用跨平台函数处理路径
    $zmfinish_dir = safe_path(ROOT_DIR . DS . 'ZmFinish');
    ensure_dir($zmfinish_dir);
    
    // 构建输入文件路径
    $original_input_path = safe_path(UPLOAD_DIR . DS . safe_filename($file));
    $zmfinish_input_path = safe_path($zmfinish_dir . DS . safe_filename($new_filename));
    
    // 移动文件
    if (file_exists($original_input_path)) {
        rename($original_input_path, $zmfinish_input_path);
        $input_path = $zmfinish_input_path;
    } else {
        $input_path = $original_input_path;
    }
    
    // 构建输出目录
    $final_output_dir_gbk = safe_path(ROOT_DIR . DS . $final_output_dir);
    ensure_dir($final_output_dir_gbk);
    
    if (!file_exists($input_path)) {
        $errors[] = '指定的视频文件不存在';
    }
    
    // 如果有错误，显示错误信息
    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
        echo '<div class="error">' . $error_message . '</div>';
        return;
    }
    
    // 记录转码开始，使用源文件的文件名
    $transcode_options = [
        'base_url' => $base_url,
        'segment_duration' => $segment_duration,
        'screenshot_time' => $screenshot_time,
        'quality' => $quality,
        'use_gpu' => $use_gpu,
        'output_dir' => $output_dir
    ];
    $record_id = record_transcode_start($original_filename, $transcode_options);
    
    // 记录开始时间
    $start_time = microtime(true);
    
    // 执行转码
    $transcode_result = transcode_video($input_path, $final_output_dir_gbk, $segment_duration, $quality, $transcode_method, $random_dir_name);
    
    // 检查转码是否成功
    if (isset($transcode_result['error'])) {
        // 记录转码失败
        record_transcode_failed($record_id, $transcode_result['error']);
        echo '<div class="error">转码失败: ' . htmlspecialchars($transcode_result['error']) . '</div>';
        return;
    }
    
    // 生成截图
    generate_screenshot($input_path, $final_output_dir_gbk, $screenshot_time, $random_dir_name);
    
    // 计算文件大小
    $file_size = 0;
    $dir = opendir($final_output_dir_gbk);
    while (($file_item = readdir($dir)) !== false) {
        if ($file_item != '.' && $file_item != '..') {
            $file_path = safe_path($final_output_dir_gbk . DS . $file_item);
            if (file_exists($file_path)) {
                $file_size += filesize($file_path);
            }
        }
    }
    closedir($dir);
    $file_size_mb = round($file_size / 1024 / 1024, 2);
    
    // 计算转码时间
    $end_time = microtime(true);
    $transcode_time = round($end_time - $start_time, 2);
    
    // 尝试保存到数据库
    $final_image_url = '';
    $final_m3u8_url = '';
    $video_duration = '未知';
    
    try {
        // 读取配置文件
        $configFile = safe_path(__DIR__ . DS . 'config.json');
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            
            // 检查数据库功能是否启用
            if (isset($config['mysql_enabled']) && $config['mysql_enabled'] == 1) {
                // 包含数据库操作类
                require_once __DIR__ . DS . 'mysql/database.php';
                
                // 创建数据库实例
                $db = new Database($config);
                
                // 获取视频时长
                $video_duration_seconds = get_video_duration_ffprobe($input_path);
                if ($video_duration_seconds > 0) {
                    if ($video_duration_seconds >= 3600) {
                        $video_duration = gmdate('H:i:s', $video_duration_seconds);
                    } else {
                        $video_duration = gmdate('i:s', $video_duration_seconds);
                    }
                }
                
                // 构建完整的链接
                $m3u8_full_url = $config['m3u8_full_url'] ?? '';
                $year = date('Y');
                $month = date('m');
                $day = date('d');
                $date_path = $year . '/' . $month . '/' . $day;
                
                if (!empty($m3u8_full_url)) {
                    $final_image_url = rtrim($m3u8_full_url, '/') . '/' . $date_path . '/' . urlencode($random_dir_name) . '.jpg';
                    $final_m3u8_url = rtrim($m3u8_full_url, '/') . '/' . $date_path . '/' . urlencode($random_dir_name) . '.m3u8';
                } else {
                    $final_image_url = rtrim($base_url, '/') . '/m3u8/' . $date_path . '/' . urlencode($random_dir_name) . '.jpg';
                    $final_m3u8_url = rtrim($base_url, '/') . '/m3u8/' . $date_path . '/' . urlencode($random_dir_name) . '.m3u8';
                }
                
                // 准备视频信息
                $videoInfo = [
                    'vodmc' => pathinfo($original_filename, PATHINFO_FILENAME),
                    'vodimg' => $final_image_url,
                    'vodurl' => $final_m3u8_url,
                    'vodsj' => $video_duration,
                    'voddx' => $file_size_mb . 'MB'
                ];
                
                // 保存到数据库
                $result = $db->saveVideoInfo($videoInfo);
            }
        }
    } catch (Exception $e) {
        // 记录错误但不影响转码流程
        error_log('数据库操作异常: ' . $e->getMessage());
    }
    
    // 创建年/月/日目录结构
    $year = date('Y');
    $month = date('m');
    $day = date('d');
    $date_dir = $year . '/' . $month . '/' . $day;
    
    // 目标目录路径
    $target_base_dir = safe_path(ROOT_DIR . DS . 'm3u8');
    $target_dir = safe_path($target_base_dir . DS . $date_dir);
    
    // 确保目录存在
    ensure_dir($target_dir);
    
    // 复制所有文件（m3u8、截图、TS文件）到目标目录
    // 首先创建以random_dir_name命名的子目录
    $target_sub_dir = safe_path($target_dir . DS . $random_dir_name);
    ensure_dir($target_sub_dir);
    
    // 复制所有文件
    $dir = opendir($final_output_dir_gbk);
    while (($file_item = readdir($dir)) !== false) {
        if ($file_item != '.' && $file_item != '..') {
            $source_file = safe_path($final_output_dir_gbk . DS . $file_item);
            $dest_file = safe_path($target_sub_dir . DS . $file_item);
            copy($source_file, $dest_file);
        }
    }
    closedir($dir);
    
    // 构建新的图片地址和m3u8地址（包含年/月/日路径）
    $encoded_dir_name = urlencode($random_dir_name);
    $encoded_year = urlencode($year);
    $encoded_month = urlencode($month);
    $encoded_day = urlencode($day);
    $date_path_url = $encoded_year . '/' . $encoded_month . '/' . $encoded_day;
    
    $new_image_url = rtrim($base_url, '/') . '/m3u8/' . $date_path_url . '/' . $encoded_dir_name . '.jpg';
    $new_m3u8_url = rtrim($base_url, '/') . '/m3u8/' . $date_path_url . '/' . $encoded_dir_name . '.m3u8';
    
    // 如果我们没有从数据库配置获取到url，就使用上面的
    if (empty($final_image_url)) {
        $final_image_url = $new_image_url;
        $final_m3u8_url = $new_m3u8_url;
    }
    
    // 记录转码完成
    record_transcode_complete($record_id, $file_size_mb, $transcode_time, $final_image_url, $final_m3u8_url);
    
    // 修改m3u8文件，更新TS文件路径
    $m3u8_file = safe_path($target_sub_dir . DS . $random_dir_name . '.m3u8');
    if (file_exists($m3u8_file)) {
        $m3u8_content = file_get_contents($m3u8_file);
        // 替换TS文件路径，使用正确的年/月/日目录结构
        $ts_path_prefix = rtrim($base_url, '/') . '/m3u8/' . $date_path_url . '/' . $random_dir_name . '/';
        $new_m3u8_content = preg_replace('/(\d{6}\.ts)/', $ts_path_prefix . '$1', $m3u8_content);
        // 保存修改后的内容
        file_put_contents($m3u8_file, $new_m3u8_content);
    }
    
    echo '<div class="success">转码成功完成！</div>';
    echo '<p>视频文件: ' . htmlspecialchars($original_filename) . '</p>';
    echo '<p>转码时间: ' . $transcode_time . ' 秒</p>';
    echo '<p>文件大小: ' . $file_size_mb . ' MB</p>';
    echo '<p>M3U8链接: <a href="' . htmlspecialchars($final_m3u8_url) . '" target="_blank">' . htmlspecialchars($final_m3u8_url) . '</a></p>';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>自动转码</title>
    <!-- 缓存控制 -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
            padding-top: 80px; /* 为返回按钮留出空间 */
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
    
    <script>
    // 刷新间隔设置
    const REFRESH_INTERVAL = 8000; // 8秒刷新一次
    const TIMEOUT_THRESHOLD = 3000; // 3秒超时阈值
    
    let refreshTimer = null;
    let timeoutTimer = null;
    
    // 自动刷新函数
    function autoRefresh() {
        // 清除之前的定时器
        if (timeoutTimer) {
            clearTimeout(timeoutTimer);
            timeoutTimer = null;
        }
        
        // 设置超时检测
        timeoutTimer = setTimeout(function() {
            console.warn('刷新可能卡住，8秒后重新尝试');
            // 8秒后重新尝试刷新
            setTimeout(autoRefresh, REFRESH_INTERVAL);
        }, TIMEOUT_THRESHOLD);
        
        // 执行刷新
        try {
            // 添加随机参数防止缓存
            const url = window.location.href.split('?')[0] + '?t=' + new Date().getTime();
            window.location.href = url;
        } catch (e) {
            console.error('刷新执行失败:', e);
            // 发生错误时，8秒后重新尝试
            setTimeout(autoRefresh, REFRESH_INTERVAL);
        }
    }
    
    // 初始化刷新
    function initRefresh() {
        // 清除之前的定时器
        if (refreshTimer) {
            clearTimeout(refreshTimer);
        }
        
        // 设置8秒后刷新
        refreshTimer = setTimeout(autoRefresh, REFRESH_INTERVAL);
    }
    
    // 页面加载完成后初始化
    window.onload = function() {
        console.log('页面加载完成，设置8秒自动刷新');
        initRefresh();
    };
    
    // 页面卸载前清理
    window.onbeforeunload = function() {
        if (refreshTimer) {
            clearTimeout(refreshTimer);
        }
        if (timeoutTimer) {
            clearTimeout(timeoutTimer);
        }
    };
    </script>
</body>
</html>
