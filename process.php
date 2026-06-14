<?php
// 视频处理核心脚本 - 跨平台优化版

// 检查是否需要安装（如果没有配置文件，重定向到安装页面）
$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';
if (!file_exists($config_file)) {
    header('Location: install.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . DS . 'includes/functions.php';

// 检查是否有POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// 检查是否是批量转码
$is_batch_transcode = isset($_POST['batch_transcode']) && $_POST['batch_transcode'] === '1';
$selected_files = $_POST['selected_files'] ?? [];

// 获取表单参数
$input_file = $_POST['input_file'] ?? '';
$output_dir = $_POST['output_dir'] ?? 'm3u8';
$base_url = $_POST['base_url'] ?? '';
$segment_duration = (int)($_POST['segment_duration'] ?? 10);
$skip_head_seconds = (int)($_POST['skip_head_seconds'] ?? 0);
$screenshot_time = (int)($_POST['screenshot_time'] ?? 10);
$quality = $_POST['quality'] ?? '1080p';
$use_gpu = isset($_POST['use_gpu']) && $_POST['use_gpu'] === '1';

// 处理批量转码
if ($is_batch_transcode) {
    if (empty($selected_files)) {
        echo '<script>alert("请至少选择一个文件进行转码"); window.location.href = "transcode.php";</script>';
        exit;
    }
    
    // 设置脚本执行时间限制为 3600 秒（1小时）
    set_time_limit(3600);
    ini_set('memory_limit', '-1');
    
    // 遍历处理每个选中的文件
    foreach ($selected_files as $file) {
        // 等待直到没有正在进行的转码任务（超时机制：最多等待60秒）
        $wait_start = time();
        $max_wait = 60;
        while (true) {
            $current_task = get_current_transcode_task();
            if (empty($current_task)) {
                break;
            }
            // 检查是否超时
            if (time() - $wait_start > $max_wait) {
                error_log('批量转码：等待超时，跳过当前检查');
                break;
            }
            sleep(2);
        }
        
        // 处理单个文件（不显示HTML，不exit）
        process_single_file($file, $output_dir, $base_url, $segment_duration, $skip_head_seconds, $screenshot_time, $quality, $use_gpu, false, false);
        
        sleep(1);
    }
    
    sleep(2);
    header('Location: history.php');
    exit;
}

// 单个文件转码处理
process_single_file($input_file, $output_dir, $base_url, $segment_duration, $skip_head_seconds, $screenshot_time, $quality, $use_gpu, true, true);

sleep(2);
header('Location: history.php');
exit;
