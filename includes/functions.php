<?php
// 工具函数

// 加载配置
require_once ROOT_DIR . '/config.php';

// 生成唯一文件名
function generate_unique_filename($extension) {
    return uniqid() . '.' . $extension;
}

// 生成10个随机字母或数字的字符串
function generate_random_string() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $length = 10;
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $random_string;
}

// 验证文件扩展名
function validate_extension($filename) {
    global $allowed_extensions;
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowed_extensions);
}

// 验证路径安全性（防止路径遍历）
function validate_path($path) {
    // 检查是否包含路径遍历字符
    if (strpos($path, '..') !== false || strpos($path, '\\') !== false) {
        return false;
    }
    // 检查是否为绝对路径
    if (substr($path, 0, 1) === '/' || substr($path, 1, 2) === ':/') {
        return false;
    }
    return true;
}

// 上传文件
function upload_file($file) {
    global $max_upload_size;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => '上传失败，错误码: ' . $file['error']];
    }
    
    if ($file['size'] > $max_upload_size * 1024 * 1024) {
        return ['error' => '文件大小超过限制 (' . $max_upload_size . 'MB)'];
    }
    
    if (!validate_extension($file['name'])) {
        return ['error' => '不支持的文件格式'];
    }
    
    // 处理文件名编码，确保中文文件名正确
    $original_name = $file['name'];
    // 在Windows系统上，可能需要转换编码
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $original_name = iconv('UTF-8', 'GBK//IGNORE', $original_name);
    }
    
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $filename = generate_unique_filename($extension);
    $target_path = UPLOAD_DIR . '/' . $filename;
    
    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['error' => '文件移动失败'];
    }
    
    return ['success' => true, 'filename' => $filename, 'path' => $target_path, 'original_name' => $original_name];
}

// 获取服务器上的视频文件列表
function get_server_files() {
    $files = [];
    $dir = opendir(UPLOAD_DIR);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            // 在Windows系统上，转换文件名编码为UTF-8
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $file_utf8 = iconv('GBK', 'UTF-8//IGNORE', $file);
                // 构建正确的文件路径，避免双斜杠
                $file_path = rtrim(UPLOAD_DIR, '/') . '/' . $file;
            } else {
                $file_utf8 = $file;
                $file_path = rtrim(UPLOAD_DIR, '/') . '/' . $file;
            }
            
            if (validate_extension($file_utf8)) {
                // 安全地获取文件修改时间，避免open_basedir限制错误
                $file_time = 0;
                try {
                    if (file_exists($file_path)) {
                        $file_time = filemtime($file_path);
                    }
                } catch (Exception $e) {
                    // 忽略错误，设置默认值
                }
                $files[] = [
                    'name' => $file_utf8,
                    'time' => $file_time
                ];
            }
        }
    }
    closedir($dir);
    return $files;
}

// 执行FFmpeg命令（带命令注入防护）
function execute_ffmpeg($command, &$output = null, &$error = null) {
    // 基本的命令注入防护 - 允许GPU加速相关参数
    $safe_commands = [
        '-i', '-c:v', '-c:a', '-hls_time', '-hls_list_size', '-f', '-ss', 
        '-vframes', '-q:v', '-crf', '-hwaccel', '-t',
        'libx264', 'aac', 'hls', 'h264_nvenc', 'h264_amf',
        'cuda', 'dxva2', 'd3d11va', 'd3d12va', 'amf'
    ];
    
    // 检查命令是否包含安全的参数
    $command_parts = explode(' ', $command);
    $in_quotes = false;
    $current_part = '';
    
    foreach ($command_parts as $part) {
        // 处理带引号的参数
        if (strpos($part, '"') === 0) {
            $in_quotes = true;
            $current_part = $part;
        } elseif (strpos($part, '"') !== false && $in_quotes) {
            $current_part .= ' ' . $part;
            $in_quotes = false;
            // 带引号的参数（通常是文件路径）跳过验证
            continue;
        } elseif ($in_quotes) {
            $current_part .= ' ' . $part;
            continue;
        } else {
            // 普通参数
            if (substr($part, 0, 1) === '-' && !in_array($part, $safe_commands)) {
                // 允许一些特殊参数，如 -hwaccel 后的参数
                if (substr($part, 0, 8) === '-hwaccel') {
                    continue;
                }
                $error = '不安全的FFmpeg命令参数: ' . $part;
                return false;
            }
        }
    }
    
    // 确保FFMPEG_PATH被正确包围在双引号中，以处理路径中的空格
    $full_command = '"' . FFMPEG_PATH . '" ' . $command;
    exec($full_command . ' 2>&1', $output, $return_var);
    
    if ($return_var !== 0) {
        $error = implode('\n', $output);
        return false;
    }
    
    return true;
}

// 视频转码切割
function transcode_video($input_file, $output_dir, $segment_duration = 10, $quality = '1080p', $gpu_method = 'none', $random_string = null) {
    global $video_quality, $gpu_acceleration;
    
    // 确保输出目录存在
    ensure_dir($output_dir);
    
    // 生成输出文件名 - 使用随机字符串.m3u8
    if ($random_string) {
        $filename = $random_string;
    } else {
        // 从输入文件名中提取文件名（不含扩展名）
        $filename = pathinfo($input_file, PATHINFO_FILENAME);
    }
    $output_file = $output_dir . '/' . $filename . '.m3u8';
    
    // 获取GPU加速参数
    $gpu_param = $gpu_acceleration[$gpu_method] ?? '';
    
    // 构建FFmpeg命令
    $quality_param = $video_quality[$quality] ?? $video_quality['1080p'];
    
    // 生成TS文件名格式 - 序号制度，例如：000001.ts
    $ts_filename_pattern = $output_dir . '/%06d.ts';
    
    // 根据GPU加速方法调整命令
    if ($gpu_method === 'none') {
        // 无GPU加速，使用默认的CPU编码
        if (empty($quality_param)) {
            // 原画质，不改变分辨率
            $command = "-i \"$input_file\" -c:v libx264 -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
        } else {
            // 指定了画质，添加相应参数
            $command = "-i \"$input_file\" -c:v libx264 $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
        }
    } else {
        // 使用GPU加速，根据不同的GPU类型构建不同的命令
        switch ($gpu_method) {
            case 'cuda':
                // NVIDIA CUDA加速
                if (empty($quality_param)) {
                    // 原画质，不改变分辨率
                    $command = "-hwaccel cuda -i \"$input_file\" -c:v h264_nvenc -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
                } else {
                    // 指定了画质，添加相应参数
                    $command = "-hwaccel cuda -i \"$input_file\" -c:v h264_nvenc $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
                }
                break;
            case 'amf':
                // AMD AMF加速
                if (empty($quality_param)) {
                    // 原画质，不改变分辨率
                    $command = "-hwaccel amf -i \"$input_file\" -c:v h264_amf -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
                } else {
                    // 指定了画质，添加相应参数
                    $command = "-hwaccel amf -i \"$input_file\" -c:v h264_amf $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
                }
                break;
            case 'dxva2':
                // DXVA2加速
                if (empty($quality_param)) {
                    // 原画质，不改变分辨率
                    $command = "-hwaccel dxva2 -i \"$input_file\" -c:v libx264 -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
                } else {
                    // 指定了画质，添加相应参数
                    $command = "-hwaccel dxva2 -i \"$input_file\" -c:v libx264 $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
                }
                break;
            case 'd3d11va':
                // D3D11VA加速
                if (empty($quality_param)) {
                    // 原画质，不改变分辨率
                    $command = "-hwaccel d3d11va -i \"$input_file\" -c:v libx264 -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
                } else {
                    // 指定了画质，添加相应参数
                    $command = "-hwaccel d3d11va -i \"$input_file\" -c:v libx264 $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
                }
                break;
            default:
                // 默认使用CPU编码
                if (empty($quality_param)) {
                    // 原画质，不改变分辨率
                    $command = "-i \"$input_file\" -c:v libx264 -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
                } else {
                    // 指定了画质，添加相应参数
                    $command = "-i \"$input_file\" -c:v libx264 $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
                }
                break;
        }
    }
    
    // 添加调试信息，显示构建的命令
    error_log('FFmpeg command: ' . $command);
    
    // 执行命令
    $output = [];
    $error = '';
    $success = execute_ffmpeg($command, $output, $error);
    
    if (!$success) {
        return ['error' => '转码失败: ' . $error];
    }
    
    // 获取生成的文件列表
    $files = [];
    $dir = opendir($output_dir);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $files[] = $file;
        }
    }
    closedir($dir);
    
    return [
        'success' => true,
        'output_file' => $output_file,
        'files' => $files,
        'm3u8_url' => str_replace(ROOT_DIR, '', $output_file),
        'gpu_acceleration' => $gpu_method,
        'command' => $command
    ];
}

// 生成视频截图
function generate_screenshot($input_file, $output_dir, $time = 10, $random_string = null) {
    // 确保输出目录存在
    ensure_dir($output_dir);
    
    // 生成输出文件名 - 使用随机字符串.jpg
    if ($random_string) {
        $filename = $random_string;
    } else {
        // 从输入文件名中提取文件名（不含扩展名）
        $filename = pathinfo($input_file, PATHINFO_FILENAME);
    }
    $output_file = $output_dir . '/' . $filename . '.jpg';
    
    // 构建FFmpeg命令
    $command = "-i \"$input_file\" -ss $time -vframes 1 -q:v 2 \"$output_file\"";
    
    // 执行命令
    $output = [];
    $error = '';
    $success = execute_ffmpeg($command, $output, $error);
    
    if (!$success) {
        return ['error' => '截图失败: ' . $error];
    }
    
    return [
        'success' => true,
        'output_file' => $output_file,
        'screenshot_url' => str_replace(ROOT_DIR, '', $output_file)
    ];
}

// 格式化时间
function format_time($seconds) {
    // 确保输入是数字
    if (!is_numeric($seconds)) {
        return '00:00:00';
    }
    
    // 转换为浮点数
    $seconds = (float)$seconds;
    
    // 计算时、分、秒
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = floor($seconds % 60);
    
    // 格式化为时:分:秒
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}

// 获取视频信息
function get_video_info($input_file) {
    $command = "-i \"$input_file\"";
    $output = [];
    $error = '';
    execute_ffmpeg($command, $output, $error);
    
    // 解析输出获取视频信息
    $info = [
        'duration' => 0,
        'width' => 0,
        'height' => 0,
        'format' => ''
    ];
    
    foreach ($output as $line) {
        if (preg_match('/Duration: (\d+):(\d+):(\d+\.\d+)/', $line, $matches)) {
            $info['duration'] = $matches[1] * 3600 + $matches[2] * 60 + $matches[3];
        }
        if (preg_match('/Stream #0:0.*, (\d+)x(\d+)/', $line, $matches)) {
            $info['width'] = $matches[1];
            $info['height'] = $matches[2];
        }
        if (preg_match('/Input #0, (\w+)/', $line, $matches)) {
            $info['format'] = $matches[1];
        }
    }
    
    return $info;
}

// 删除文件
function delete_file($file_path) {
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    return false;
}

// 清理输出目录
function cleanup_output($output_dir) {
    $dir = opendir($output_dir);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            unlink($output_dir . '/' . $file);
        }
    }
    closedir($dir);
}

// 获取错误信息
function get_error_message($error) {
    return '<div class="error">' . htmlspecialchars($error) . '</div>';
}

// 获取成功信息
function get_success_message($message) {
    return '<div class="success">' . htmlspecialchars($message) . '</div>';
}

// 转码记录相关函数

// 转码记录文件路径
function get_transcode_record_file() {
    return ROOT_DIR . '/ting.json';
}

// 读取转码记录
function read_transcode_records() {
    $record_file = get_transcode_record_file();
    if (file_exists($record_file)) {
        $content = file_get_contents($record_file);
        return json_decode($content, true) ?? [];
    }
    return [];
}

// 保存转码记录
function save_transcode_records($records) {
    $record_file = get_transcode_record_file();
    $content = json_encode($records, JSON_PRETTY_PRINT);
    
    // 确保文件所在目录存在
    $dir = dirname($record_file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // 确保文件有写入权限
    if (file_exists($record_file) && !is_writable($record_file)) {
        chmod($record_file, 0644);
    }
    
    return file_put_contents($record_file, $content);
}

// 获取当前转码任务文件路径
function get_current_transcode_file() {
    return ROOT_DIR . '/current_transcode.json';
}

// 记录转码开始
function record_transcode_start($filename, $options) {
    $records = read_transcode_records();
    
    // 创建包含必要信息的记录
    $record = [
        'id' => uniqid(),
        'filename' => $filename,
        'start_time' => date('Y-m-d H:i:s'),
        'end_time' => '',
        'image_url' => '',
        'm3u8_url' => '',
        'options' => $options
    ];
    
    // 保存到临时文件，用于跟踪当前转码任务
    $current_transcode_file = get_current_transcode_file();
    file_put_contents($current_transcode_file, json_encode($record));
    
    $records[] = $record;
    save_transcode_records($records);
    return $record['id'];
}

// 更新转码进度
function update_transcode_progress($record_id, $progress) {
    // 由于我们只保存简洁记录，不需要更新进度
    // 此函数保留仅为兼容性
}

// 记录转码完成
function record_transcode_complete($record_id, $file_size, $duration, $image_url = '', $m3u8_url = '') {
    // 读取现有记录
    $existing_records = read_transcode_records();
    
    // 创建新的简洁记录数组
    $new_records = [];
    
    // 处理每条记录
    foreach ($existing_records as $record) {
        // 如果是当前完成的记录，跳过，稍后将其放在数组开头
        if ($record['id'] === $record_id) {
            continue;
        }
        // 对于其他记录，只保留必要信息
        $new_record = [
            'filename' => $record['filename'],
            'end_time' => $record['end_time'] ?? '',
            'duration' => $record['duration'] ?? 0,
            'file_size' => $record['file_size'] ?? 0,
            'image_url' => $record['image_url'] ?? '',
            'm3u8_url' => $record['m3u8_url'] ?? ''
        ];
        $new_records[] = $new_record;
    }
    
    // 创建当前完成的记录，包含所有必要信息
    // 查找原始记录以获取文件名
    $original_filename = '';
    foreach ($existing_records as $record) {
        if ($record['id'] === $record_id) {
            $original_filename = $record['filename'];
            break;
        }
    }
    
    // 创建新记录
    $current_record = [
        'filename' => $original_filename,
        'end_time' => date('Y-m-d H:i:s'),
        'duration' => $duration,
        'file_size' => $file_size,
        'image_url' => $image_url,
        'm3u8_url' => $m3u8_url
    ];
    
    // 将当前完成的记录放在数组开头
    array_unshift($new_records, $current_record);
    
    // 保存简洁记录
    save_transcode_records($new_records);
    
    // 删除临时文件，清除当前转码任务跟踪
    $current_transcode_file = get_current_transcode_file();
    if (file_exists($current_transcode_file)) {
        unlink($current_transcode_file);
    }
}

// 记录转码失败
function record_transcode_failed($record_id, $error) {
    // 读取现有记录
    $existing_records = read_transcode_records();
    
    // 创建新的简洁记录数组
    $new_records = [];
    
    // 处理每条记录
    foreach ($existing_records as $record) {
        // 如果是当前失败的记录
        if ($record['id'] === $record_id) {
            // 创建简洁记录，只包含必要信息
            $new_record = [
                'filename' => $record['filename'],
                'end_time' => date('Y-m-d H:i:s'),
                'image_url' => '',
                'm3u8_url' => ''
            ];
            $new_records[] = $new_record;
        } else {
            // 对于其他记录，只保留必要信息
            $new_record = [
                'filename' => $record['filename'],
                'end_time' => $record['end_time'] ?? '',
                'image_url' => $record['image_url'] ?? '',
                'm3u8_url' => $record['m3u8_url'] ?? ''
            ];
            $new_records[] = $new_record;
        }
    }
    
    // 保存简洁记录
    save_transcode_records($new_records);
    
    // 删除临时文件，清除当前转码任务跟踪
    $current_transcode_file = get_current_transcode_file();
    if (file_exists($current_transcode_file)) {
        unlink($current_transcode_file);
    }
}

// 获取当前正在转码的任务
function get_current_transcode_task() {
    $current_transcode_file = get_current_transcode_file();
    if (file_exists($current_transcode_file)) {
        $content = file_get_contents($current_transcode_file);
        return json_decode($content, true) ?? null;
    }
    return null;
}

// 获取已完成的转码记录
function get_completed_transcode_records() {
    $records = read_transcode_records();
    // 所有记录都是简洁格式，直接返回并按时间倒序排列
    usort($records, function($a, $b) {
        return strcmp($b['end_time'], $a['end_time']);
    });
    return $records;
}

// 清理转码记录
function clear_transcode_records() {
    $record_file = get_transcode_record_file();
    // 写入空数组到记录文件
    return file_put_contents($record_file, json_encode([], JSON_PRETTY_PRINT));
}
?>