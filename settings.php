<?php
// 设置管理脚本

// 加载配置和硬件检测
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/hardware_detection.php';

// 检测系统硬件信息
$system_info = detect_system();
$gpu_info = $system_info['gpu'];

// 配置文件路径
$config_file = __DIR__ . '/config.json';

// 读取现有配置
function read_config() {
    global $config_file;
    if (file_exists($config_file)) {
        $content = file_get_contents($config_file);
        return json_decode($content, true);
    }
    return [];
}

// 保存配置
function save_config($config) {
    global $config_file;
    $content = json_encode($config, JSON_PRETTY_PRINT);
    return file_put_contents($config_file, $content);
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ffmpeg_path = $_POST['ffmpeg_path'] ?? 'ffmpeg';
    $gpu_acceleration = $_POST['gpu_acceleration'] ?? 'none';
    $input_dir = $_POST['input_dir'] ?? './vodoss/';
    $output_dir = $_POST['output_dir'] ?? './m3u8/';
    $base_url = $_POST['base_url'] ?? '';
    $segment_duration = $_POST['segment_duration'] ?? 10;
    $screenshot_time = $_POST['screenshot_time'] ?? 10;
    $quality = $_POST['quality'] ?? '1080p';
    $use_gpu = $_POST['use_gpu'] ?? 0;
    
    // 验证路径
    $config = read_config();
    $config['ffmpeg_path'] = $ffmpeg_path;
    $config['gpu_acceleration'] = $gpu_acceleration;
    $config['input_dir'] = $input_dir;
    $config['output_dir'] = $output_dir;
    $config['base_url'] = $base_url;
    $config['segment_duration'] = $segment_duration;
    $config['screenshot_time'] = $screenshot_time;
    $config['quality'] = $quality;
    $config['use_gpu'] = $use_gpu;
    
    // 保存配置
    if (save_config($config)) {
        $message = '<div class="success">设置保存成功</div>';
    } else {
        $message = '<div class="error">设置保存失败</div>';
    }
}

// 读取当前配置
$current_config = read_config();
$current_ffmpeg_path = $current_config['ffmpeg_path'] ?? FFMPEG_PATH;
$current_input_dir = $current_config['input_dir'] ?? './vodoss/';
$current_output_dir = $current_config['output_dir'] ?? './m3u8/';
$current_base_url = $current_config['base_url'] ?? '';
$current_segment_duration = $current_config['segment_duration'] ?? 10;
$current_screenshot_time = $current_config['screenshot_time'] ?? 10;
$current_quality = $current_config['quality'] ?? '1080p';
$current_use_gpu = $current_config['use_gpu'] ?? 0;

// 验证FFmpeg路径
function test_ffmpeg_path($path) {
    $output = [];
    $return_var = 0;
    exec($path . ' -version 2>&1', $output, $return_var);
    return $return_var === 0;
}

$ffmpeg_status = test_ffmpeg_path($current_ffmpeg_path) ? '可用' : '不可用';
?>

<?php include __DIR__ . '/includes/header.php'; ?>

    <div class="card">
        <h2>系统设置</h2>
        <?php echo $message ?? ''; ?>
        
        <form method="post">
            <div class="form-group">
                <label for="ffmpeg_path">FFmpeg路径</label>
                <input type="text" id="ffmpeg_path" name="ffmpeg_path" value="<?php echo htmlspecialchars($current_ffmpeg_path); ?>" placeholder="例如: C:/ffmpeg/bin/ffmpeg.exe">
                <small>如果已添加到系统PATH，可直接使用 'ffmpeg'</small>
                <small>这里是ffmpeg程序路径，不是目录路径，需要包含ffmpeg.exe文件</small>
            </div>
            

            
            <div class="form-group">
                <label for="input_dir">待转码目录</label>
                <input type="text" id="input_dir" name="input_dir" value="<?php echo htmlspecialchars($current_input_dir); ?>" placeholder="./vodoss/">
                <small>默认为 ./vodoss/，表示根目录下的vodoss文件夹</small>
            </div>
            
            <div class="form-group">
                <label for="output_dir">转码后保存目录</label>
                <input type="text" id="output_dir" name="output_dir" value="<?php echo htmlspecialchars($current_output_dir); ?>" placeholder="./m3u8/">
                <small>默认为 ./m3u8/，表示转码后保存的文件目录</small>
            </div>
            
            <div class="form-group">
                <label for="base_url">TS文件路径设置</label>
                <input type="text" id="base_url" name="base_url" value="<?php echo htmlspecialchars($current_base_url); ?>" placeholder="例如: http://your-domain/output/ 结尾加‘/’">
                <small>TS文件的基础访问地址，会添加到m3u8文件中的每个TS文件路径前！</small>
                <small>最终在m3u8文件中合成的路径为：http(s)://TS文件基础地址/转码后保存目录/视频文件名/index.m3u8</small>
            </div>
            
            <div class="form-group">
                <label for="segment_duration">切片时长 (秒)</label>
                <input type="number" id="segment_duration" name="segment_duration" value="<?php echo $current_segment_duration; ?>" min="1" max="60">
                <small>每个TS切片的时长，默认为10秒</small>
            </div>
            
            <div class="form-group">
                <label for="screenshot_time">截图时间点 (秒)</label>
                <input type="number" id="screenshot_time" name="screenshot_time" value="<?php echo $current_screenshot_time; ?>" min="0">
                <small>视频截图的时间点，默认为10秒</small>
            </div>
            
            <div class="form-group">
                <label for="quality">画质选择</label>
                <select id="quality" name="quality">
                    <option value="original" <?php echo $current_quality === 'original' ? 'selected' : ''; ?>>原画质</option>
                    <option value="1080p" <?php echo $current_quality === '1080p' ? 'selected' : ''; ?>>1080P</option>
                    <option value="720p" <?php echo $current_quality === '720p' ? 'selected' : ''; ?>>720P</option>
                </select>
                <small>选择转码后的视频画质</small>
            </div>
            
            <div class="form-group">
                <label for="use_gpu">使用GPU加速</label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="use_gpu" name="use_gpu" value="1" <?php echo $current_use_gpu == 1 ? 'checked' : ''; ?> <?php echo !$gpu_info['available'] ? 'disabled' : ''; ?>>
                    <label for="use_gpu" style="color: <?php echo $gpu_info['available'] ? 'green' : 'red'; ?>">
                        <?php echo $gpu_info['available'] ? '使用GPU加速' : '未检测到GPU，无法使用GPU加速'; ?>
                    </label>
                </div>
                <small><?php echo $gpu_info['available'] ? '勾选后使用GPU加速转码' : '未检测到GPU，只能使用CPU'; ?></small>
            </div>
            
            <div class="form-group">
                <label>当前状态</label>
                <div><?php echo 'FFmpeg路径: ' . htmlspecialchars($current_ffmpeg_path); ?></div>
                <div><?php echo '状态: <span style="color: ' . ($ffmpeg_status === '可用' ? 'green' : 'red') . '">' . $ffmpeg_status . '</span>'; ?></div>
                <div><?php echo '待转码目录: ' . htmlspecialchars($current_input_dir); ?></div>
                <div><?php echo '转码后目录: ' . htmlspecialchars($current_output_dir); ?></div>
                <div><?php echo '基础地址: ' . htmlspecialchars($current_base_url); ?></div>
                <div><?php echo '切片时长: ' . $current_segment_duration . ' 秒'; ?></div>
                <div><?php echo '截图时间点: ' . $current_screenshot_time . ' 秒'; ?></div>
                <div><?php echo '画质选择: ' . $current_quality; ?></div>
                <div><?php echo '使用GPU加速: ' . ($current_use_gpu == 1 ? '是' : '否'); ?></div>
            </div>
            
            <input type="submit" value="保存设置">
        </form>
    </div>

    <div class="card">
        <h2>设置说明</h2>
        <ul>
            <li><strong>FFmpeg路径</strong>：如果FFmpeg已添加到系统PATH，可直接使用 'ffmpeg'；否则需要指定完整路径，例如 'C:/ffmpeg/bin/ffmpeg.exe'。</li>
            <li><strong>转码选择</strong>：如果检测到GPU，可以选择使用GPU加速；否则只能使用CPU。</li>
            <li><strong>待转码目录</strong>：存放需要转码的视频文件的目录，默认为 ./vodoss/。</li>
            <li><strong>转码后保存目录</strong>：存放转码完成后的文件的目录，默认为 ./m3u8/。</li>
            <li><strong>路径格式</strong>：使用相对路径，以 ./ 开头，表示项目根目录。</li>
        </ul>
    </div>

<?php include __DIR__ . '/includes/footer.php'; ?>