<?php
// 后台转码脚本

// 忽略用户中止
ignore_user_abort(true);

// 设置脚本执行时间为无限
set_time_limit(0);

// 加载配置和函数
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

// 获取命令行参数
if (count($argv) < 9) {
    $error_msg = '缺少必要的命令行参数，当前参数数量: ' . count($argv) . ', 期望参数数量: 9';
    die($error_msg);
}

$record_id = $argv[1];
$input_path = $argv[2];
$output_dir = $argv[3];
$segment_duration = $argv[4];
$quality = $argv[5];
$transcode_method = $argv[6];
$base_url = $argv[7];
$video_filename = $argv[8];

// 确保输出目录存在
ensure_dir($output_dir);

// 记录开始时间
$start_time = microtime(true);

// 执行转码
$transcode_result = transcode_video($input_path, $output_dir, $segment_duration, $quality, $transcode_method);

// 检查转码是否成功
if (isset($transcode_result['error'])) {
    // 记录转码失败
    record_transcode_failed($record_id, $transcode_result['error']);
} else {
    // 生成视频截图
    generate_screenshot($input_path, $output_dir, 10);
    
    // 计算文件大小
    $file_size = 0;
    $dir = opendir($output_dir);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $file_path = $output_dir . '/' . $file;
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
    
    // 记录转码完成
    record_transcode_complete($record_id, $file_size_mb, $transcode_time);
    
    // 修改m3u8文件，更新TS文件路径
    $m3u8_file = $transcode_result['output_file'];
    if (file_exists($m3u8_file)) {
        $m3u8_content = file_get_contents($m3u8_file);
        // 替换TS文件路径
        $encoded_video_filename = urlencode($video_filename);
        $new_m3u8_content = preg_replace('/(\d{6}\.ts)/', rtrim($base_url, '/') . '/m3u8/' . $encoded_video_filename . '/$1', $m3u8_content);
        // 保存修改后的内容
        file_put_contents($m3u8_file, $new_m3u8_content);
    }
}

// 退出脚本
exit;
?>