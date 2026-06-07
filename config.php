<?php
// 配置文件

// 项目根目录
define('ROOT_DIR', __DIR__);

// 跨平台路径分隔符常量
define('DS', DIRECTORY_SEPARATOR);

// 定义可能的配置文件位置
$possible_config_locations = [
    __DIR__ . DS . 'config.json',              // 根目录（首选）
    __DIR__ . DS . 'data' . DS . 'config.json', // data目录
];

// 查找存在的配置文件，或者使用第一个可写的位置
$config_file = null;
$used_location = 0;
foreach ($possible_config_locations as $i => $location) {
    if (file_exists($location)) {
        $config_file = $location;
        $used_location = $i;
        break;
    }
}

// 如果没有找到存在的文件，找第一个可写的位置
if ($config_file === null) {
    foreach ($possible_config_locations as $i => $location) {
        $dir = dirname($location);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            $config_file = $location;
            $used_location = $i;
            break;
        }
    }
}

// 如果找不到可写位置，就使用第一个位置（只读模式）
if ($config_file === null) {
    $config_file = $possible_config_locations[0];
}

// 读取配置
$config = [];
$default_config = [
    'ffmpeg_path' => 'ffmpeg',
    'ffprobe_path' => 'ffprobe',
    'input_dir' => './vodoss/',
    'output_dir' => './m3u8/',
    'base_url' => '',
    'segment_duration' => 10,
    'screenshot_time' => 10,
    'quality' => '1080p',
    'use_gpu' => 0,
    'mysql_enabled' => 0,
    'mysql_host' => 'localhost',
    'mysql_port' => '3306',
    'mysql_db' => 'vod_system',
    'mysql_user' => 'root',
    'mysql_password' => '',
    'm3u8_full_url' => ''
];

if (file_exists($config_file)) {
    $content = @file_get_contents($config_file);
    $config = json_decode($content, true) ?? [];
    // 合并默认配置，确保所有配置项都存在
    $config = array_merge($default_config, $config);
} else {
    $config = $default_config;
    // 尝试创建配置文件
    $dir = dirname($config_file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 定义配置文件路径常量供其他文件使用
define('CONFIG_FILE_PATH', $config_file);

// 待转码目录
$input_dir = $config['input_dir'] ?? './vodoss/';
// 移除开头的./
$input_dir = ltrim($input_dir, './');
define('UPLOAD_DIR', ROOT_DIR . DS . $input_dir);

// 转码后保存目录
$output_dir = $config['output_dir'] ?? './m3u8/';
// 移除开头的./
$output_dir = ltrim($output_dir, './');
define('OUTPUT_DIR', ROOT_DIR . DS . $output_dir);

// FFmpeg 路径配置
// 方法1：如果已添加到系统PATH，可直接使用 'ffmpeg'
// 方法2：指定完整路径，例如：'C:/ffmpeg/bin/ffmpeg.exe'
// 方法3：通过设置页面配置（保存在config.json中）
// 优先使用前端设置的路径，默认值为'ffmpeg'
define('FFMPEG_PATH', $config['ffmpeg_path'] ?? 'ffmpeg');
define('FFPROBE_PATH', $config['ffprobe_path'] ?? 'ffprobe');

// 检测操作系统类型
define('IS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

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
        // 使用递归创建目录，处理跨平台权限问题
        if (!mkdir($dir, 0755, true)) {
            return false;
        }
    }
    if (!is_writable($dir)) {
        // 尝试更改权限，但在没有权限时抑制警告
        @chmod($dir, 0755);
    }
    return true;
}

// 检查函数是否被禁用
function is_function_disabled($func_name) {
    $disabled = explode(',', ini_get('disable_functions'));
    $disabled = array_map('trim', $disabled);
    return in_array($func_name, $disabled);
}

// 安全执行命令函数
function safe_exec($command, &$output = null, &$return_var = null) {
    // 检查exec函数是否可用
    if (is_function_disabled('exec')) {
        $return_var = -1;
        $output = ['exec() 函数被禁用'];
        return false;
    }
    
    // 在Windows上处理命令
    if (IS_WINDOWS) {
        // Windows上使用cmd /c来执行，确保正确处理路径
        $command = 'cmd /c ' . escapeshellarg($command);
    }
    return exec($command, $output, $return_var);
}

// 检查FFmpeg是否安装
function check_ffmpeg() {
    global $ffmpeg_available;
    if (isset($ffmpeg_available)) {
        return $ffmpeg_available;
    }
    
    // 如果exec函数被禁用，假设FFmpeg不可用
    if (is_function_disabled('exec')) {
        $ffmpeg_available = false;
        return $ffmpeg_available;
    }
    
    $output = [];
    $return_var = 0;
    
    // 使用FFMPEG_PATH检查
    $test_cmd = escapeshellarg(FFMPEG_PATH) . ' -version 2>&1';
    safe_exec($test_cmd, $output, $return_var);
    $ffmpeg_available = ($return_var === 0);
    return $ffmpeg_available;
}

// 获取正确的ffprobe路径
function get_ffprobe_path() {
    // 优先使用配置的ffprobe路径
    if (defined('FFPROBE_PATH') && FFPROBE_PATH) {
        return FFPROBE_PATH;
    }
    
    // 尝试从ffmpeg路径派生
    $ffmpeg_path = FFMPEG_PATH;
    if (IS_WINDOWS) {
        // Windows: 替换.exe
        $ffprobe_path = str_replace('.exe', '', $ffmpeg_path) . '.exe';
        $ffprobe_path = str_replace('ffmpeg', 'ffprobe', $ffprobe_path);
    } else {
        // Linux/Mac: 替换ffmpeg为ffprobe
        $ffprobe_path = str_replace('ffmpeg', 'ffprobe', $ffmpeg_path);
    }
    
    return $ffprobe_path;
}

// 初始化目录
ensure_dir(UPLOAD_DIR);
ensure_dir(OUTPUT_DIR);

// 检查FFmpeg状态
$ffmpeg_available = check_ffmpeg();
?>