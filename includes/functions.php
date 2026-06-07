<?php
// 工具函数

// 加载配置
require_once ROOT_DIR . DS . 'config.php';

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
    if (strpos($path, '..') !== false) {
        return false;
    }
    // 检查是否为绝对路径（Windows和Linux）
    if (substr($path, 0, 1) === '/' || substr($path, 1, 2) === ':/' || substr($path, 1, 2) === ':\\') {
        return false;
    }
    return true;
}

// 跨平台文件路径处理函数
function safe_path($path) {
    // 统一路径分隔符
    $path = str_replace(['/', '\\'], DS, $path);
    // 移除多余的路径分隔符
    $path = preg_replace('#' . preg_quote(DS, '#') . '+#', DS, $path);
    return $path;
}

// 安全获取文件名（处理编码问题）
function safe_filename($filename) {
    if (IS_WINDOWS) {
        // Windows系统上尝试处理文件名编码
        if (function_exists('iconv')) {
            $filename = iconv('UTF-8', 'GBK//IGNORE', $filename);
        }
    }
    return $filename;
}

// 安全读取目录文件名
function safe_readdir($filename) {
    if (IS_WINDOWS) {
        // Windows系统上转换编码
        if (function_exists('iconv')) {
            return iconv('GBK', 'UTF-8//IGNORE', $filename);
        }
    }
    return $filename;
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
    $original_name = safe_filename($original_name);
    
    $extension = pathinfo($original_name, PATHINFO_EXTENSION);
    $filename = generate_unique_filename($extension);
    $target_path = safe_path(UPLOAD_DIR . DS . $filename);
    
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
            // 安全处理文件名编码
            $file_utf8 = safe_readdir($file);
            // 构建正确的文件路径
            $file_path = safe_path(UPLOAD_DIR . DS . $file);
            
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
                    'time' => $file_time,
                    'raw_name' => $file // 保留原始文件名用于文件操作
                ];
            }
        }
    }
    closedir($dir);
    return $files;
}

// 执行FFmpeg命令（带命令注入防护）
function execute_ffmpeg($command, &$output = null, &$error = null) {
    // 检查exec函数是否被禁用
    if (is_function_disabled('exec')) {
        $error = 'exec() 函数被禁用，无法执行FFmpeg命令';
        return false;
    }
    
    // 基本的命令注入防护 - 允许GPU加速相关参数
    $safe_commands = [
        '-i', '-c:v', '-c:a', '-hls_time', '-hls_list_size', '-f', '-ss', 
        '-vframes', '-q:v', '-crf', '-hwaccel', '-t', '-vf', '-preset',
        'libx264', 'aac', 'hls', 'h264_nvenc', 'h264_amf', 'copy',
        'cuda', 'dxva2', 'd3d11va', 'd3d12va', 'amf', 'vaapi', 'vdpau'
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
    
    // 构建完整命令，确保FFMPEG_PATH被正确处理
    $ffmpeg_path = FFMPEG_PATH;
    // 处理路径中的空格
    if (strpos($ffmpeg_path, ' ') !== false && strpos($ffmpeg_path, '"') === false) {
        $ffmpeg_path = '"' . $ffmpeg_path . '"';
    }
    
    $full_command = $ffmpeg_path . ' ' . $command;
    
    // 使用跨平台方式执行命令
    $output = [];
    $return_var = 0;
    
    if (IS_WINDOWS) {
        // Windows上需要特殊处理
        $full_command = 'cmd /c ' . escapeshellarg($full_command) . ' 2>&1';
    } else {
        $full_command = $full_command . ' 2>&1';
    }
    
    exec($full_command, $output, $return_var);
    
    if ($return_var !== 0) {
        $error = implode(PHP_EOL, $output);
        return false;
    }
    
    return true;
}

// 视频转码切割
function transcode_video($input_file, $output_dir, $segment_duration = 10, $quality = '1080p', $gpu_method = 'none', $random_string = null) {
    global $video_quality, $gpu_acceleration;
    
    // 确保输出目录存在
    ensure_dir($output_dir);
    
    // 安全处理路径
    $input_file = safe_path($input_file);
    $output_dir = safe_path($output_dir);
    
    // 生成输出文件名 - 使用随机字符串.m3u8
    if ($random_string) {
        $filename = $random_string;
    } else {
        // 从输入文件名中提取文件名（不含扩展名）
        $filename = pathinfo($input_file, PATHINFO_FILENAME);
    }
    $output_file = safe_path($output_dir . DS . $filename . '.m3u8');
    
    // 获取视频信息，用于调试
    $video_info = get_video_info($input_file);
    error_log('Input video info: ' . json_encode($video_info));
    
    // 生成TS文件名格式 - 序号制度，例如：000001.ts
    $ts_filename_pattern = safe_path($output_dir . DS . '%06d.ts');
    
    // 如果选择源画质，直接使用 -c copy 复制流，不转码
    if ($quality === 'original') {
        error_log('使用源画质模式，直接复制流不转码');
        $command = "-i \"$input_file\" -c copy -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
        $gpu_method = 'none'; // 复制流不需要GPU加速
    } else {
        // 构建FFmpeg转码命令
        $quality_param = $video_quality[$quality] ?? $video_quality['1080p'];
        
        // 构建基础命令，让FFmpeg自动处理输入编码
        // 只指定输出编码器，不指定输入编码器
        $base_command = "-i \"$input_file\" -c:v libx264 -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
        
        // 根据GPU加速方法和画质调整命令
        if (!empty($quality_param)) {
            $base_command = "-i \"$input_file\" -c:v libx264 $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
        }
        
        // 根据GPU加速方法调整命令
        $command = $base_command;
        
        // 检查GPU方法是否适用于当前平台
        $is_gpu_available = true;
        if ($gpu_method !== 'none') {
            // 在非Windows平台上检查GPU方法兼容性
            if (!IS_WINDOWS && in_array($gpu_method, ['dxva2', 'd3d11va', 'amf'])) {
                error_log("GPU方法 $gpu_method 不适用于当前平台，使用CPU编码");
                $is_gpu_available = false;
                $gpu_method = 'none';
            }
        }
        
        if ($is_gpu_available && $gpu_method === 'cuda') {
            // NVIDIA CUDA加速 (Windows/Linux)
            if (empty($quality_param)) {
                $command = "-hwaccel cuda -i \"$input_file\" -c:v h264_nvenc -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
            } else {
                $command = "-hwaccel cuda -i \"$input_file\" -c:v h264_nvenc $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
            }
        } elseif ($is_gpu_available && $gpu_method === 'amf') {
            // AMD AMF加速 (仅Windows)
            if (empty($quality_param)) {
                $command = "-hwaccel amf -i \"$input_file\" -c:v h264_amf -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
            } else {
                $command = "-hwaccel amf -i \"$input_file\" -c:v h264_amf $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
            }
        } elseif ($is_gpu_available && $gpu_method === 'dxva2') {
            // DXVA2加速 (仅Windows)
            if (empty($quality_param)) {
                $command = "-hwaccel dxva2 -i \"$input_file\" -c:v libx264 -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
            } else {
                $command = "-hwaccel dxva2 -i \"$input_file\" -c:v libx264 $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
            }
        } elseif ($is_gpu_available && $gpu_method === 'd3d11va') {
            // D3D11VA加速 (仅Windows)
            if (empty($quality_param)) {
                $command = "-hwaccel d3d11va -i \"$input_file\" -c:v libx264 -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
            } else {
                $command = "-hwaccel d3d11va -i \"$input_file\" -c:v libx264 $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
            }
        } elseif ($is_gpu_available && $gpu_method === 'vaapi' && !IS_WINDOWS) {
            // VAAPI加速 (仅Linux)
            if (empty($quality_param)) {
                $command = "-hwaccel vaapi -i \"$input_file\" -c:v h264_vaapi -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
            } else {
                $command = "-hwaccel vaapi -i \"$input_file\" -c:v h264_vaapi $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
            }
        }
    }
    
    // 添加调试信息，显示构建的命令
    error_log('FFmpeg command: ' . $command);
    
    // 执行命令
    $output = [];
    $error = '';
    $success = execute_ffmpeg($command, $output, $error);
    
    if (!$success && $quality === 'original') {
        // 源画质模式失败，尝试回退到转码模式
        error_log('源画质模式失败，回退到转码模式');
        $quality = '1080p';
        $quality_param = $video_quality[$quality] ?? $video_quality['1080p'];
        $base_command = "-i \"$input_file\" -c:v libx264 $quality_param -c:a aac -hls_time $segment_duration -hls_list_size 0 -hls_segment_filename \"$ts_filename_pattern\" -f hls \"$output_file\"";
        $command = $base_command;
        $output = [];
        $error = '';
        $success = execute_ffmpeg($command, $output, $error);
    } elseif (!$success) {
        // 转码失败，尝试使用更通用的CPU编码命令
        error_log('转码失败，尝试使用通用CPU编码命令');
        
        // 构建通用的CPU编码命令
        $fallback_command = $base_command;
        error_log('尝试使用的备用命令: ' . $fallback_command);
        
        $output = [];
        $error = '';
        $success = execute_ffmpeg($fallback_command, $output, $error);
        
        if (!$success) {
            // 再次失败，返回详细错误信息
            error_log('转码完全失败: ' . $error);
            return ['error' => '转码失败: ' . $error];
        }
        
        // 备用命令成功，使用备用命令的结果
        $command = $fallback_command;
        $gpu_method = 'none';
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
    
    // 安全处理路径
    $input_file = safe_path($input_file);
    $output_dir = safe_path($output_dir);
    
    // 生成输出文件名 - 使用随机字符串.jpg
    if ($random_string) {
        $filename = $random_string;
    } else {
        // 从输入文件名中提取文件名（不含扩展名）
        $filename = pathinfo($input_file, PATHINFO_FILENAME);
    }
    $output_file = safe_path($output_dir . DS . $filename . '.jpg');
    
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
    $input_file = safe_path($input_file);
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

// 使用ffprobe获取视频时长（更可靠的方法）
function get_video_duration_ffprobe($input_file) {
    // 检查exec函数是否被禁用
    if (is_function_disabled('exec')) {
        return 0;
    }
    
    $input_file = safe_path($input_file);
    $ffprobe_path = get_ffprobe_path();
    
    // 构建ffprobe命令
    $command = '-v quiet -show_entries format=duration -of csv=p=0 "' . $input_file . '"';
    
    // 执行命令
    $output = [];
    $return_var = 0;
    
    if (IS_WINDOWS) {
        $full_command = 'cmd /c ' . escapeshellarg('"' . $ffprobe_path . '" ' . $command) . ' 2>&1';
    } else {
        $full_command = '"' . $ffprobe_path . '" ' . $command . ' 2>&1';
    }
    
    exec($full_command, $output, $return_var);
    
    if ($return_var === 0 && !empty($output)) {
        $duration = floatval($output[0]);
        return $duration;
    }
    
    return 0; // 失败时返回0
}

// 删除文件
function delete_file($file_path) {
    $file_path = safe_path($file_path);
    if (file_exists($file_path)) {
        return unlink($file_path);
    }
    return false;
}

// 清理输出目录
function cleanup_output($output_dir) {
    $output_dir = safe_path($output_dir);
    if (!is_dir($output_dir)) {
        return;
    }
    
    $dir = opendir($output_dir);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $file_path = safe_path($output_dir . DS . $file);
            if (is_file($file_path)) {
                unlink($file_path);
            }
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

// 获取可能的转码记录文件位置
function get_possible_record_files($filename) {
    return [
        safe_path(ROOT_DIR . DS . $filename),
        safe_path(ROOT_DIR . DS . 'data' . DS . $filename)
    ];
}

// 查找转码记录文件（先找现有文件，再找可写目录）
function find_record_file($filename) {
    $possible_files = get_possible_record_files($filename);
    
    // 先检查是否有现有文件
    foreach ($possible_files as $file) {
        if (file_exists($file)) {
            return $file;
        }
    }
    
    // 没有现有文件，找第一个可写的目录创建
    foreach ($possible_files as $file) {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return $file;
        }
    }
    
    // 如果都不可写，返回第一个位置
    return $possible_files[0];
}

// 转码记录文件路径
function get_transcode_record_file() {
    return find_record_file('ting.json');
}

// 读取转码记录（尝试从所有可能的位置读取，避免丢失旧记录）
function read_transcode_records() {
    $possible_files = get_possible_record_files('ting.json');
    
    foreach ($possible_files as $file) {
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            $records = json_decode($content, true) ?? [];
            if (!empty($records)) {
                return $records;
            }
        }
    }
    return [];
}

// 保存转码记录
function save_transcode_records($records) {
    $record_file = get_transcode_record_file();
    $content = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // 确保文件所在目录存在
    $dir = dirname($record_file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    // 尝试保存
    $result = @file_put_contents($record_file, $content);
    return $result !== false;
}

// 获取当前转码任务文件路径
function get_current_transcode_file() {
    return find_record_file('current_transcode.json');
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
        // 安全获取 id，避免警告
        $record_id_key = isset($record['id']) ? $record['id'] : (isset($record['filename']) ? md5($record['filename'] . (isset($record['start_time']) ? $record['start_time'] : '')) : uniqid());
        
        // 如果是当前完成的记录，跳过，稍后将其放在数组开头
        if ($record_id_key === $record_id) {
            continue;
        }
        
        // 对于其他记录，只保留必要信息
        $new_record = [
            'id' => $record_id_key,
            'filename' => isset($record['filename']) ? $record['filename'] : '',
            'end_time' => isset($record['end_time']) ? $record['end_time'] : '',
            'duration' => isset($record['duration']) ? $record['duration'] : 0,
            'file_size' => isset($record['file_size']) ? $record['file_size'] : 0,
            'image_url' => isset($record['image_url']) ? $record['image_url'] : '',
            'm3u8_url' => isset($record['m3u8_url']) ? $record['m3u8_url'] : ''
        ];
        $new_records[] = $new_record;
    }
    
    // 创建当前完成的记录，包含所有必要信息
    // 查找原始记录以获取文件名
    $original_filename = '';
    foreach ($existing_records as $record) {
        $record_id_key = isset($record['id']) ? $record['id'] : (isset($record['filename']) ? md5($record['filename'] . (isset($record['start_time']) ? $record['start_time'] : '')) : uniqid());
        if ($record_id_key === $record_id) {
            $original_filename = isset($record['filename']) ? $record['filename'] : '';
            break;
        }
    }
    
    // 创建新记录
    $current_record = [
        'id' => $record_id,
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
    
    // 删除临时文件，清除当前转码任务跟踪（从所有可能的位置）
    $possible_current_files = get_possible_record_files('current_transcode.json');
    foreach ($possible_current_files as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
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
        // 安全获取 id，避免警告
        $record_id_key = isset($record['id']) ? $record['id'] : (isset($record['filename']) ? md5($record['filename'] . (isset($record['start_time']) ? $record['start_time'] : '')) : uniqid());
        
        // 如果是当前失败的记录
        if ($record_id_key === $record_id) {
            // 创建简洁记录，只包含必要信息
            $new_record = [
                'id' => $record_id_key,
                'filename' => isset($record['filename']) ? $record['filename'] : '',
                'end_time' => date('Y-m-d H:i:s'),
                'image_url' => '',
                'm3u8_url' => ''
            ];
            $new_records[] = $new_record;
        } else {
            // 对于其他记录，只保留必要信息
            $new_record = [
                'id' => $record_id_key,
                'filename' => isset($record['filename']) ? $record['filename'] : '',
                'end_time' => isset($record['end_time']) ? $record['end_time'] : '',
                'image_url' => isset($record['image_url']) ? $record['image_url'] : '',
                'm3u8_url' => isset($record['m3u8_url']) ? $record['m3u8_url'] : ''
            ];
            $new_records[] = $new_record;
        }
    }
    
    // 保存简洁记录
    save_transcode_records($new_records);
    
    // 删除临时文件，清除当前转码任务跟踪（从所有可能的位置）
    $possible_current_files = get_possible_record_files('current_transcode.json');
    foreach ($possible_current_files as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }
}

// 获取当前正在转码的任务（尝试从多个位置读取）
function get_current_transcode_task() {
    $possible_files = get_possible_record_files('current_transcode.json');
    
    foreach ($possible_files as $file) {
        if (file_exists($file)) {
            $content = @file_get_contents($file);
            return json_decode($content, true) ?? null;
        }
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
    // 确保目录存在
    $dir = dirname($record_file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    // 写入空数组到记录文件
    $result = @file_put_contents($record_file, json_encode([], JSON_PRETTY_PRINT));
    return $result !== false;
}
?>