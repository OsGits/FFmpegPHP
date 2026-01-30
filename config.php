<?php
// 配置文件

// 项目根目录
define('ROOT_DIR', __DIR__);

// 读取配置文件
$config_file = __DIR__ . '/config.json';
$config = [];
if (file_exists($config_file)) {
    $content = file_get_contents($config_file);
    $config = json_decode($content, true) ?? [];
} else {
    // 如果配置文件不存在，创建默认配置文件
    $default_config = [
        'ffmpeg_path' => 'ffmpeg',
        'input_dir' => './vodoss/',
        'output_dir' => './m3u8/',
        'base_url' => '',
        'segment_duration' => 10,
        'screenshot_time' => 10,
        'quality' => '1080p',
        'use_gpu' => 0
    ];
    $config = $default_config;
    // 保存默认配置到文件
    file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT));
}

// 待转码目录
$input_dir = $config['input_dir'] ?? './vodoss/';
// 移除开头的./
$input_dir = ltrim($input_dir, './');
define('UPLOAD_DIR', ROOT_DIR . '/' . $input_dir);

// 转码后保存目录
$output_dir = $config['output_dir'] ?? './m3u8/';
// 移除开头的./
$output_dir = ltrim($output_dir, './');
define('OUTPUT_DIR', ROOT_DIR . '/' . $output_dir);

// FFmpeg 路径配置
// 方法1：如果已添加到系统PATH，可直接使用 'ffmpeg'
// 方法2：指定完整路径，例如：'C:/ffmpeg/bin/ffmpeg.exe'
// 方法3：通过设置页面配置（保存在config.json中）
// 优先使用前端设置的路径，默认值为'ffmpeg'
define('FFMPEG_PATH', $config['ffmpeg_path'] ?? 'ffmpeg');

// 允许的视频格式
$allowed_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'];

// 读取配置文件中的转码参数
$base_url = $config['base_url'] ?? '';
$default_segment_duration = $config['segment_duration'] ?? 10;
$default_screenshot_time = $config['screenshot_time'] ?? 10;
$default_quality = $config['quality'] ?? '1080p';
$default_use_gpu = $config['use_gpu'] ?? 0;

// 最大上传文件大小 (MB)
$max_upload_size = 500;

// 视频质量设置 - 对应具体画质
$video_quality = [
    'original' => '', // 原画质，不改变分辨率
    '1080p' => '-crf 23 -vf scale=1920:1080', // 1080P画质
    '720p' => '-crf 23 -vf scale=1280:720' // 720P画质
];

// GPU加速设置
$gpu_acceleration = [
    'none' => '',
    'cuda' => '-hwaccel cuda -c:v h264_nvenc',
    'dxva2' => '-hwaccel dxva2',
    'd3d11va' => '-hwaccel d3d11va',
    'amf' => '-hwaccel amf -c:v h264_amf'
];

// 确保目录存在且可写
function ensure_dir($dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!is_writable($dir)) {
        // 尝试更改权限，但在没有权限时抑制警告
        @chmod($dir, 0755);
    }
}

// 检查FFmpeg是否安装
function check_ffmpeg() {
    global $ffmpeg_available;
    if (isset($ffmpeg_available)) {
        return $ffmpeg_available;
    }
    
    $output = [];
    $return_var = 0;
    exec(FFMPEG_PATH . ' -version 2>&1', $output, $return_var);
    $ffmpeg_available = ($return_var === 0);
    return $ffmpeg_available;
}

// 初始化目录
ensure_dir(UPLOAD_DIR);
ensure_dir(OUTPUT_DIR);

// 检查FFmpeg状态
$ffmpeg_available = check_ffmpeg();
?>