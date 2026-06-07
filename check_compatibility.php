<?php
// 跨平台兼容性检查脚本
// 用于验证代码在Windows和Linux下的兼容性

echo "=== 跨平台兼容性检查 ===\n\n";

// 1. 检查PHP版本
echo "1. PHP版本检查\n";
echo "   PHP版本: " . PHP_VERSION . "\n";
echo "   推荐版本: 7.0或更高\n";
echo "   " . (version_compare(PHP_VERSION, '7.0.0', '>=') ? "✅ 符合要求" : "❌ 需要升级") . "\n\n";

// 2. 检查操作系统
echo "2. 操作系统检查\n";
echo "   操作系统: " . PHP_OS . "\n";
echo "   操作系统类型: " . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? "Windows" : "Unix/Linux") . "\n\n";

// 3. 检查必需的函数
echo "3. 必需函数检查\n";
$required_functions = ['exec', 'json_encode', 'json_decode', 'file_get_contents', 'file_put_contents'];
foreach ($required_functions as $func) {
    echo "   $func: " . (function_exists($func) ? "✅ 可用" : "❌ 不可用") . "\n";
}
echo "\n";

// 4. 检查必需的扩展
echo "4. 必需扩展检查\n";
$required_extensions = ['json', 'mbstring'];
foreach ($required_extensions as $ext) {
    echo "   $ext: " . (extension_loaded($ext) ? "✅ 已加载" : "❌ 未加载") . "\n";
}
echo "\n";

// 5. 检查目录权限
echo "5. 目录权限检查\n";
$ds = DIRECTORY_SEPARATOR;
$dirs_to_check = [
    __DIR__ . $ds . 'vodoss',
    __DIR__ . $ds . 'm3u8',
    __DIR__ . $ds . 'ZmFinish',
    __DIR__ . $ds . 'data'
];

foreach ($dirs_to_check as $dir) {
    $dir_exists = is_dir($dir);
    $dir_writable = $dir_exists && is_writable($dir);
    echo "   $dir: " . ($dir_exists ? "存在" : "不存在");
    echo ", " . ($dir_writable ? "可写✅" : "不可写❌") . "\n";
    
    // 如果目录不存在，尝试创建
    if (!$dir_exists) {
        echo "   尝试创建目录...";
        if (@mkdir($dir, 0755, true)) {
            echo "成功✅\n";
        } else {
            echo "失败❌\n";
        }
    }
}

// 检查根目录是否可写
echo "\n6. 根目录可写性检查\n";
$root_writable = is_writable(__DIR__);
echo "   根目录 (".__DIR__."): " . ($root_writable ? "可写✅" : "不可写❌") . "\n";

// 检查配置文件状态
echo "\n7. 配置文件检查\n";
require_once __DIR__ . '/config.php';
echo "   配置文件路径: " . CONFIG_FILE_PATH . "\n";
echo "   配置文件存在: " . (file_exists(CONFIG_FILE_PATH) ? "是✅" : "否❌") . "\n";
echo "\n";

// 8. 检查转码记录文件
echo "8. 转码记录文件检查\n";
require_once __DIR__ . '/includes/functions.php';
$ting_file = get_transcode_record_file();
echo "   记录文件路径: " . $ting_file . "\n";
echo "   记录文件存在: " . (file_exists($ting_file) ? "是✅" : "否❌") . "\n";
if (file_exists($ting_file)) {
    $size = filesize($ting_file);
    echo "   文件大小: " . $size . " 字节\n";
    if ($size > 0) {
        $content = @file_get_contents($ting_file);
        $records = json_decode($content, true);
        if (is_array($records)) {
            echo "   记录数量: " . count($records) . "\n";
        }
    }
}
echo "\n";

// 9. 检查FFmpeg可用性
echo "9. FFmpeg可用性检查\n";
require_once __DIR__ . '/config.php';
echo "   FFmpeg路径: " . FFMPEG_PATH . "\n";

// 本地检查函数
function is_exec_disabled() {
    $disabled = explode(',', ini_get('disable_functions'));
    $disabled = array_map('trim', $disabled);
    return in_array('exec', $disabled);
}

// 检查exec函数是否可用
if (is_exec_disabled()) {
    echo "   FFmpeg: ❌ 无法检查（exec()函数被禁用）\n";
} else {
    // 尝试执行FFmpeg
    $output = [];
    $return_var = 0;
    if (IS_WINDOWS) {
        $cmd = 'cmd /c ' . escapeshellarg(FFMPEG_PATH . ' -version') . ' 2>&1';
    } else {
        $cmd = FFMPEG_PATH . ' -version 2>&1';
    }

    exec($cmd, $output, $return_var);

    if ($return_var === 0) {
        echo "   FFmpeg: ✅ 可用\n";
        if (!empty($output)) {
            echo "   版本: " . trim($output[0]) . "\n";
        }
    } else {
        echo "   FFmpeg: ❌ 不可用\n";
        echo "   错误信息:\n";
        foreach ($output as $line) {
            echo "      $line\n";
        }
    }
}
echo "\n";

// 10. 检查FFprobe可用性
echo "10. FFprobe可用性检查\n";
$ffprobe_path = FFPROBE_PATH;
echo "   FFprobe路径: " . $ffprobe_path . "\n";

if (is_exec_disabled()) {
    echo "   FFprobe: ❌ 无法检查（exec()函数被禁用）\n";
} else {
    if (IS_WINDOWS) {
        $cmd = 'cmd /c ' . escapeshellarg($ffprobe_path . ' -version') . ' 2>&1';
    } else {
        $cmd = $ffprobe_path . ' -version 2>&1';
    }

    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);

    if ($return_var === 0) {
        echo "   FFprobe: ✅ 可用\n";
    } else {
        echo "   FFprobe: ⚠️ 不可用（可选，但推荐）\n";
    }
}
echo "\n";

// 11. 总结
echo "=== 检查完成 ===\n";
echo "如果所有标记为✅的项目都通过，则代码应该能正常运行。\n";
echo "如果有❌的项目，请根据提示解决相关问题。\n";
?>