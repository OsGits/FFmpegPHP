<?php
// 视频处理核心脚本

// 加载配置和函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// 检查是否有POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// 获取表单参数
$input_file = $_POST['input_file'] ?? '';
$output_dir = $_POST['output_dir'] ?? 'output';
$base_url = $_POST['base_url'] ?? '';
$segment_duration = (int)($_POST['segment_duration'] ?? 10);
$screenshot_time = (int)($_POST['screenshot_time'] ?? 10);
$quality = $_POST['quality'] ?? 'medium';
$use_gpu = isset($_POST['use_gpu']) && $_POST['use_gpu'] === '1';

// 保存源文件的文件名（用于数据库和json记录）
$original_filename = $input_file;

// 构建最终输出目录（在output目录下创建以10位随机数字加字母命名的子目录）
$random_dir_name = generate_random_string();
$final_output_dir = $output_dir . '/' . $random_dir_name;

// 加载硬件检测函数
require_once __DIR__ . '/includes/hardware_detection.php';
$gpu_info = detect_gpu();

// 根据use_gpu决定转码方式
// 当用户勾选GPU加速时，使用系统检测到的默认GPU加速方式
$transcode_method = $use_gpu ? $gpu_info['default'] : 'none';

// 验证参数
$errors = [];

if (empty($base_url)) {
    $errors[] = 'TS文件路径设置不能为空';
}

if (empty($input_file)) {
    $errors[] = '请选择视频文件';
}

if (empty($output_dir)) {
    $errors[] = '请指定保存目录';
}

// 验证路径安全性
if (!validate_path($output_dir)) {
    $errors[] = '保存目录路径不安全';
}

if (!validate_path($input_file)) {
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
$file_extension = pathinfo($input_file, PATHINFO_EXTENSION);
$new_filename = $timestamp . '.' . $file_extension;

// 构建完整路径，处理编码问题
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // 在Windows系统上，转换文件名编码为GBK
    $input_file_gbk = iconv('UTF-8', 'GBK//IGNORE', $input_file);
    $original_input_path = UPLOAD_DIR . '/' . $input_file_gbk;
    
    // 移动文件到ZmFinish目录并使用新文件名
    $zmfinish_dir = ROOT_DIR . '/ZmFinish';
    ensure_dir($zmfinish_dir);
    $new_filename_gbk = iconv('UTF-8', 'GBK//IGNORE', $new_filename);
    $zmfinish_input_path = $zmfinish_dir . '/' . $new_filename_gbk;
    
    if (file_exists($original_input_path)) {
        // 移动文件到ZmFinish目录并使用新文件名
        rename($original_input_path, $zmfinish_input_path);
        $input_path = $zmfinish_input_path;
    } else {
        $input_path = $original_input_path;
    }
    
    // 处理输出目录的编码问题
    // 先创建output目录（如果不存在）
    ensure_dir(ROOT_DIR . '/' . $output_dir);
    
    // 然后创建以10位随机数字加字母命名的子目录（使用GBK编码）
    $random_dir_name_gbk = iconv('UTF-8', 'GBK//IGNORE', $random_dir_name);
    $final_output_dir_gbk = ROOT_DIR . '/' . $output_dir . '/' . $random_dir_name_gbk;
    ensure_dir($final_output_dir_gbk);
} else {
    $original_input_path = UPLOAD_DIR . '/' . $input_file;
    
    // 移动文件到ZmFinish目录并使用新文件名
    $zmfinish_dir = ROOT_DIR . '/ZmFinish';
    ensure_dir($zmfinish_dir);
    $zmfinish_input_path = $zmfinish_dir . '/' . $new_filename;
    
    if (file_exists($original_input_path)) {
        // 移动文件到ZmFinish目录并使用新文件名
        rename($original_input_path, $zmfinish_input_path);
        $input_path = $zmfinish_input_path;
    } else {
        $input_path = $original_input_path;
    }
    
    // 在非Windows系统上，直接使用UTF-8编码
    $final_output_dir_gbk = ROOT_DIR . '/' . $final_output_dir;
    ensure_dir($final_output_dir_gbk);
}

if (!file_exists($input_path)) {
    $errors[] = '指定的视频文件不存在';
}

// 构建输出目录路径
if (substr($final_output_dir, 0, 1) === '/') {
    $final_output_dir = substr($final_output_dir, 1);
}
$full_output_dir = ROOT_DIR . '/' . $final_output_dir;

// 如果有错误，显示错误信息
if (!empty($errors)) {
    $error_message = implode('<br>', $errors);
    echo '<div class="error">' . $error_message . '</div>';
    echo '<a href="index.php">返回</a>';
    exit;
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

// 直接执行转码过程，不使用后台执行，以便查看详细的错误信息

// 记录开始时间
$start_time = microtime(true);

// 直接执行转码，传递随机目录名作为文件名
$transcode_result = transcode_video($input_path, $final_output_dir_gbk, $segment_duration, $quality, $transcode_method, $random_dir_name);

// 检查转码是否成功
if (isset($transcode_result['error'])) {
    // 记录转码失败
    record_transcode_failed($record_id, $transcode_result['error']);
} else {
    // 生成视频截图，传递随机目录名作为文件名
generate_screenshot($input_path, $final_output_dir_gbk, 10, $random_dir_name);
    
    // 计算文件大小
    $file_size = 0;
    $dir = opendir($final_output_dir_gbk);
    while (($file = readdir($dir)) !== false) {
        if ($file != '.' && $file != '..') {
            $file_path = $final_output_dir_gbk . '/' . $file;
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
    
    // 构建图片地址和m3u8地址
$encoded_dir_name = urlencode($random_dir_name);
$image_url = rtrim($base_url, '/') . '/m3u8/' . $encoded_dir_name . '/' . $encoded_dir_name . '.jpg';
$m3u8_url = rtrim($base_url, '/') . '/m3u8/' . $encoded_dir_name . '/' . $encoded_dir_name . '.m3u8';
    
    // 尝试保存到数据库
    try {
        // 读取配置文件
        $configFile = dirname(__FILE__) . '/config.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            
            // 检查数据库功能是否启用
            if (isset($config['mysql_enabled']) && $config['mysql_enabled'] == 1) {
                // 包含数据库操作类
                require_once dirname(__FILE__) . '/mysql/database.php';
                
                // 创建数据库实例
                $db = new Database($config);
                
                // 构建完整的链接 - 使用m3u8_full_url
                $m3u8_full_url = $config['m3u8_full_url'] ?? '';
                if (!empty($m3u8_full_url)) {
                    // 使用配置的完整链接作为基础，添加年/月/日路径
                    $year = date('Y');
                    $month = date('m');
                    $day = date('d');
                    $date_path = $year . '/' . $month . '/' . $day;
                    $final_image_url = rtrim($m3u8_full_url, '/') . '/' . $date_path . '/' . urlencode($random_dir_name) . '.jpg';
                    $final_m3u8_url = rtrim($m3u8_full_url, '/') . '/' . $date_path . '/' . urlencode($random_dir_name) . '.m3u8';
                } else {
                    // 构建包含年/月/日路径的URL
                    $year = date('Y');
                    $month = date('m');
                    $day = date('d');
                    $date_path = urlencode($year) . '/' . urlencode($month) . '/' . urlencode($day);
                    $final_image_url = rtrim($base_url, '/') . '/m3u8/' . $date_path . '/' . urlencode($random_dir_name) . '.jpg';
                    $final_m3u8_url = rtrim($base_url, '/') . '/m3u8/' . $date_path . '/' . urlencode($random_dir_name) . '.m3u8';
                }
                
                // 获取视频播放时长
                function get_video_duration($input_file) {
                    // 构建FFprobe命令获取视频时长
                    $command = "-v quiet -show_entries format=duration -of csv=p=0 \"$input_file\"";
                    $ffprobe_path = str_replace('ffmpeg.exe', 'ffprobe.exe', FFMPEG_PATH);
                    if (!file_exists($ffprobe_path)) {
                        $ffprobe_path = FFMPEG_PATH; // 如果ffprobe不存在，尝试使用ffmpeg
                    }
                    
                    $full_command = "\"$ffprobe_path\" " . $command;
                    $output = [];
                    $return_var = 0;
                    
                    exec($full_command . ' 2>&1', $output, $return_var);
                    
                    if ($return_var === 0 && !empty($output)) {
                        $duration = floatval($output[0]);
                        // 格式化时长为 HH:MM:SS 或 MM:SS
                        if ($duration >= 3600) {
                            return gmdate('H:i:s', $duration);
                        } else {
                            return gmdate('i:s', $duration);
                        }
                    }
                    
                    return '未知';
                }
                
                // 获取视频播放时长
                $video_duration = get_video_duration($input_path);
                
                // 准备视频信息，使用源文件的文件名
                $videoInfo = [
                    'vodmc' => pathinfo($original_filename, PATHINFO_FILENAME), // 视频名称（使用源文件的文件名）
                    'vodimg' => $final_image_url, // 图片地址
                    'vodurl' => $final_m3u8_url, // m3u8链接
                    'vodsj' => $video_duration, // 视频播放时长
                    'voddx' => $file_size_mb . 'MB' // 文件大小
                ];
                
                // 保存到数据库
                $result = $db->saveVideoInfo($videoInfo);
                
                if ($result['success']) {
                    error_log('视频信息已成功保存到数据库，ID: ' . $result['id']);
                } else {
                    error_log('数据库保存失败: ' . $result['message']);
                }
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
    $target_base_dir = ROOT_DIR . '/m3u8';
    $target_dir = $target_base_dir . '/' . $date_dir;
    
    // 确保目录存在
    ensure_dir($target_dir);
    
    // 只复制m3u8文件和截图到目标目录
    $m3u8_file = $final_output_dir_gbk . '/' . $random_dir_name . '.m3u8';
    $screenshot_file = $final_output_dir_gbk . '/' . $random_dir_name . '.jpg';
    
    if (file_exists($m3u8_file)) {
        copy($m3u8_file, $target_dir . '/' . basename($m3u8_file));
    }
    
    if (file_exists($screenshot_file)) {
        copy($screenshot_file, $target_dir . '/' . basename($screenshot_file));
    }
    
    // 构建新的图片地址和m3u8地址（包含年/月/日路径）
    $encoded_year = urlencode($year);
    $encoded_month = urlencode($month);
    $encoded_day = urlencode($day);
    $date_path = $encoded_year . '/' . $encoded_month . '/' . $encoded_day;
    
    $new_image_url = rtrim($base_url, '/') . '/m3u8/' . $date_path . '/' . $encoded_dir_name . '.jpg';
    $new_m3u8_url = rtrim($base_url, '/') . '/m3u8/' . $date_path . '/' . $encoded_dir_name . '.m3u8';
    
    // 记录转码完成（使用新的URL）
    record_transcode_complete($record_id, $file_size_mb, $transcode_time, $new_image_url, $new_m3u8_url);
    
    // 修改m3u8文件，更新TS文件路径（不包含日期路径）
    $m3u8_file = $target_dir . '/' . $random_dir_name . '.m3u8';
    if (file_exists($m3u8_file)) {
        $m3u8_content = file_get_contents($m3u8_file);
        // 替换TS文件路径，只使用基础的TS文件路径设置
        $new_m3u8_content = preg_replace('/(\d{6}\.ts)/', rtrim($base_url, '/') . '/m3u8/' . $random_dir_name . '/$1', $m3u8_content);
        // 保存修改后的内容
        file_put_contents($m3u8_file, $new_m3u8_content);
    }
}

// 跳转到历史页面
sleep(2);
header('Location: history.php');
exit;

// 开始处理
?>

<?php include __DIR__ . '/includes/header.php'; ?>

    <div class="card">
        <h2>处理结果</h2>
        
        <?php
        // 记录开始时间
        $start_time = microtime(true);
        
        // 1. 视频转码切割
        echo '<h3>1. 视频转码切割</h3>';
        echo '<div class="progress">';
        echo '<div class="progress-bar" style="width: 0%;">开始处理...</div>';
        echo '</div>';
        
        // 刷新输出
        ob_flush();
        flush();
        
        // 更新进度
        update_transcode_progress($record_id, 10);
        
        // 执行转码，传递用户选择的转码方式和随机目录名作为文件名
        // 当用户勾选了GPU加速时，强制使用GPU，不回退到CPU
        // 使用处理编码问题后的输出目录路径
        $transcode_result = transcode_video($input_path, $final_output_dir_gbk, $segment_duration, $quality, $transcode_method, $random_dir_name);
        
        // 更新进度
        update_transcode_progress($record_id, 50);
        
        // 刷新输出
        ob_flush();
        flush();
        
        // 检查转码是否成功，如果失败且用户选择了GPU加速，显示错误信息
        if (isset($transcode_result['error']) && $use_gpu) {
            echo '<div class="error">GPU加速失败: ' . $transcode_result['error'] . '</div>';
            echo '<div class="error">当勾选GPU加速选项时，系统会强制使用GPU进行处理，请确保您的GPU驱动已正确安装。</div>';
            echo '<a href="index.php">返回</a>';
            
            // 记录转码失败
            record_transcode_failed($record_id, $transcode_result['error']);
            exit;
        }
        
        if (isset($transcode_result['error'])) {
            echo get_error_message($transcode_result['error']);
            
            // 记录转码失败
            record_transcode_failed($record_id, $transcode_result['error']);
        } else {
            echo get_success_message('视频转码切割成功');
            
            // 更新进度
            update_transcode_progress($record_id, 70);
            
            // 刷新输出
            ob_flush();
            flush();
            
            // 2. 生成视频截图，传递随机目录名作为文件名
            echo '<h3>2. 视频截图</h3>';
            $screenshot_result = generate_screenshot($input_path, $final_output_dir_gbk, $screenshot_time, $random_dir_name);
            
            if (isset($screenshot_result['error'])) {
                echo get_error_message($screenshot_result['error']);
            } else {
                echo get_success_message('视频截图成功');
                echo '<p>截图预览:</p>';
                // 生成正确的截图URL，确保中文文件名也能正确访问
                $screenshot_url = str_replace(ROOT_DIR, '', $screenshot_result['output_file']);
                // 对路径中的中文部分进行URL编码
                $screenshot_url = preg_replace_callback('/([^\/]+)/', function($matches) {
                    return urlencode($matches[1]);
                }, $screenshot_url);
                echo '<img src="' . $screenshot_url . '" alt="视频截图" class="screenshot">';
            }
            
            // 更新进度
            update_transcode_progress($record_id, 85);
            
            // 刷新输出
            ob_flush();
            flush();
            
            // 3. 显示生成的文件
            echo '<h3>3. 生成的文件</h3>';
            echo '<div class="output-files">';
            echo '<ul>';
            foreach ($transcode_result['files'] as $file) {
                // 生成正确的文件URL，确保中文文件名也能正确访问
                $file_path = $final_output_dir . '/' . $file;
                $file_url = str_replace(ROOT_DIR, '', $file_path);
                // 对路径中的中文部分进行URL编码
                $file_url = preg_replace_callback('/([^\/]+)/', function($matches) {
                    return urlencode($matches[1]);
                }, $file_url);
                echo '<li><a href="' . $file_url . '" target="_blank">' . $file . '</a></li>';
            }
            echo '</ul>';
            echo '</div>';
            
            // 4. 显示M3U8播放链接
            echo '<h3>4. 播放链接</h3>';
            // 生成正确的M3U8 URL，确保中文文件名也能正确访问
            $m3u8_url = str_replace(ROOT_DIR, '', $transcode_result['output_file']);
            // 对路径中的中文部分进行URL编码
            $m3u8_url = preg_replace_callback('/([^\/]+)/', function($matches) {
                return urlencode($matches[1]);
            }, $m3u8_url);
            echo '<p>M3U8播放列表: <a href="' . $m3u8_url . '" target="_blank">' . $m3u8_url . '</a></p>';
            
            // 5. 显示GPU加速信息
            echo '<p>转码方式: ' . ($transcode_result['gpu_acceleration'] === 'none' ? 'CPU转码' : 'GPU加速 (' . $transcode_result['gpu_acceleration'] . ')') . '</p>';
            
            // 6. 修改m3u8文件，更新TS文件路径
            echo '<h3>6. 更新M3U8文件</h3>';
            $m3u8_file = $transcode_result['output_file'];
            if (file_exists($m3u8_file)) {
                $m3u8_content = file_get_contents($m3u8_file);
                // 替换TS文件路径 - 处理序号制度的TS文件名，例如：000001.ts
                // 使用URL编码处理随机目录名，确保路径中包含特殊字符时也能正确访问
                $encoded_dir_name = urlencode($random_dir_name);
                $new_m3u8_content = preg_replace('/(\d{6}\.ts)/', rtrim($base_url, '/') . '/m3u8/' . $encoded_dir_name . '/$1', $m3u8_content);
                // 保存修改后的内容
                if (file_put_contents($m3u8_file, $new_m3u8_content)) {
                    echo '<p style="color: green;">M3U8文件更新成功</p>';
                } else {
                    echo '<p style="color: red;">M3U8文件更新失败</p>';
                }
            } else {
                echo '<p style="color: red;">M3U8文件不存在</p>';
            }
            
            // 7. 显示处理时间
            $end_time = microtime(true);
            $process_time = round($end_time - $start_time, 2);
            echo '<p>处理时间: ' . $process_time . ' 秒</p>';
            
            // 8. 显示执行的命令（调试用）
            echo '<p><strong>执行命令:</strong></p>';
            echo '<pre style="background-color: #f5f5f5; padding: 10px; border-radius: 4px; font-size: 12px;">' . htmlspecialchars($transcode_result['command'] ?? '命令未记录') . '</pre>';
            
            // 8. 显示最终设置
            echo '<h3>7. 最终设置</h3>';
            echo '<p>视频文件名: ' . htmlspecialchars(pathinfo($original_filename, PATHINFO_FILENAME)) . '</p>';
            echo '<p>基础地址: ' . htmlspecialchars($base_url) . '</p>';
            // 显示UTF-8编码的目录路径，确保中文显示正确
            echo '<p>TS文件存储目录: ' . htmlspecialchars($final_output_dir) . '</p>';
            echo '<p>M3U8文件: ' . htmlspecialchars(str_replace(ROOT_DIR, '', $transcode_result['output_file'])) . '</p>';
            
            // 计算文件大小
            $file_size = 0;
            foreach ($transcode_result['files'] as $file) {
                $file_path = $final_output_dir_gbk . '/' . $file;
                if (file_exists($file_path)) {
                    $file_size += filesize($file_path);
                }
            }
            $file_size_mb = round($file_size / 1024 / 1024, 2);
            
            // 构建图片地址和m3u8地址
            $encoded_dir_name = urlencode($random_dir_name);
            $image_url = rtrim($base_url, '/') . '/m3u8/' . $encoded_dir_name . '/' . $encoded_dir_name . '.jpg';
            $m3u8_url = rtrim($base_url, '/') . '/m3u8/' . $encoded_dir_name . '/' . $encoded_dir_name . '.m3u8';
            
            // 更新进度
            update_transcode_progress($record_id, 100);
            
            // 记录转码完成
            record_transcode_complete($record_id, $file_size_mb, $process_time, $image_url, $m3u8_url);
        }
        
        // 刷新输出
        ob_flush();
        flush();
        
        // 跳转回转码页面
        echo '<script>setTimeout(function() { window.location.href = "history.php"; }, 3000);</script>';
        echo '<p>3秒后自动跳转到记录页面...</p>';
        ?>
        
        <div style="margin-top: 20px;">
            <a href="history.php" style="display: inline-block; padding: 10px 20px; background-color: #f5f5f5; color: #333; text-decoration: none; border-radius: 4px;">查看转码记录</a>
        </div>
        
        <div style="margin-top: 20px;">
            <a href="index.php" style="display: inline-block; padding: 10px 20px; background-color: #f5f5f5; color: #333; text-decoration: none; border-radius: 4px;">返回首页</a>
        </div>
    </div>

<?php include __DIR__ . '/includes/footer.php'; ?>
