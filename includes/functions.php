<?php
// 工具函数

// 加载配置
require_once ROOT_DIR . '/config.php';

// 生成唯一文件名
function generate_unique_filename($extension) {
    return uniqid() . '.' . $extension;
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
function transcode_video($input_file, $output_dir, $segment_duration = 10, $quality = 'medium', $gpu_method = 'none') {
    global $video_quality, $gpu_acceleration;
    
    // 确保输出目录存在
    ensure_dir($output_dir);
    
    // 生成输出文件名 - 使用index.m3u8
    $output_file = $output_dir . '/index.m3u8';
    
    // 获取GPU加速参数
    $gpu_param = $gpu_acceleration[$gpu_method] ?? '';
    
    // 构建FFmpeg命令
    $quality_param = $video_quality[$quality] ?? $video_quality['original'];
    
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
function generate_screenshot($input_file, $output_dir, $time = 10) {
    // 确保输出目录存在
    ensure_dir($output_dir);
    
    // 生成输出文件名 - 使用index.jpg
    $output_file = $output_dir . '/index.jpg';
    
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

// 记录转码开始
function record_transcode_start($filename, $options) {
    $records = read_transcode_records();
    
    $record = [
        'id' => uniqid(),
        'filename' => $filename,
        'status' => 'processing',
        'progress' => 0,
        'options' => $options,
        'start_time' => date('Y-m-d H:i:s'),
        'end_time' => null,
        'duration' => null,
        'file_size' => null
    ];
    
    $records[] = $record;
    save_transcode_records($records);
    return $record['id'];
}

// 更新转码进度
function update_transcode_progress($record_id, $progress) {
    $records = read_transcode_records();
    
    foreach ($records as &$record) {
        if ($record['id'] === $record_id) {
            $record['progress'] = min($progress, 100);
            break;
        }
    }
    
    save_transcode_records($records);
}

// 记录转码完成
function record_transcode_complete($record_id, $file_size, $duration) {
    $records = read_transcode_records();
    
    foreach ($records as &$record) {
        if ($record['id'] === $record_id) {
            $record['status'] = 'completed';
            $record['progress'] = 100;
            $record['end_time'] = date('Y-m-d H:i:s');
            $record['duration'] = $duration;
            $record['file_size'] = $file_size;
            break;
        }
    }
    
    save_transcode_records($records);
}

// 记录转码失败
function record_transcode_failed($record_id, $error) {
    $records = read_transcode_records();
    
    foreach ($records as &$record) {
        if ($record['id'] === $record_id) {
            $record['status'] = 'failed';
            $record['error'] = $error;
            $record['end_time'] = date('Y-m-d H:i:s');
            break;
        }
    }
    
    save_transcode_records($records);
}

// 获取当前正在转码的任务
function get_current_transcode_task() {
    $records = read_transcode_records();
    
    foreach (array_reverse($records) as $record) {
        if ($record['status'] === 'processing') {
            return $record;
        }
    }
    
    return null;
}

// 获取已完成的转码记录
function get_completed_transcode_records() {
    $records = read_transcode_records();
    $completed = [];
    
    foreach ($records as $record) {
        if (isset($record['status']) && $record['status'] === 'completed') {
            $completed[] = $record;
        }
    }
    
    return array_reverse($completed); // 按时间倒序排列
}

// 清理转码记录
function clear_transcode_records() {
    $record_file = get_transcode_record_file();
    // 写入空数组到记录文件
    return file_put_contents($record_file, json_encode([], JSON_PRETTY_PRINT));
}
?>