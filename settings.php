<?php
// 设置管理脚本

// 加载配置和硬件检测
require_once __DIR__ . '/config.php';
require_once __DIR__ . DS . 'includes/functions.php';
require_once __DIR__ . DS . 'includes/hardware_detection.php';

// 检测系统硬件信息
$system_info = detect_system();
$gpu_info = $system_info['gpu'];

// 使用 config.php 中定义的配置文件路径
$config_file = defined('CONFIG_FILE_PATH') ? CONFIG_FILE_PATH : safe_path(__DIR__ . DS . 'config.json');

// 读取现有配置
function read_config() {
    global $config_file;
    if (file_exists($config_file)) {
        $content = @file_get_contents($config_file);
        return json_decode($content, true) ?? [];
    }
    return [];
}

// 保存配置
function save_config($config) {
    global $config_file;
    $content = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // 检查目录是否可写
    $dir = dirname($config_file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    if (!is_writable($dir)) {
        return false;
    }
    
    // 尝试保存
    $result = @file_put_contents($config_file, $content);
    return $result !== false;
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 读取当前配置
    $config = read_config();
    
    // 检查是否是基础设置表单提交
    if (isset($_POST['ffmpeg_path']) || isset($_POST['input_dir']) || isset($_POST['output_dir']) || isset($_POST['base_url']) || isset($_POST['segment_duration']) || isset($_POST['screenshot_time']) || isset($_POST['quality']) || isset($_POST['use_gpu'])) {
        // 基础设置
        if (isset($_POST['ffmpeg_path'])) {
            $config['ffmpeg_path'] = $_POST['ffmpeg_path'] ?? 'ffmpeg';
        }
        if (isset($_POST['ffprobe_path'])) {
            $config['ffprobe_path'] = $_POST['ffprobe_path'] ?? 'ffprobe';
        }
        if (isset($_POST['gpu_acceleration'])) {
            $config['gpu_acceleration'] = $_POST['gpu_acceleration'] ?? 'none';
        }
        if (isset($_POST['input_dir'])) {
            $config['input_dir'] = $_POST['input_dir'] ?? './vodoss/';
        }
        if (isset($_POST['output_dir'])) {
            $config['output_dir'] = $_POST['output_dir'] ?? './m3u8/';
        }
        if (isset($_POST['base_url'])) {
            $config['base_url'] = $_POST['base_url'] ?? '';
        }
        if (isset($_POST['segment_duration'])) {
            $config['segment_duration'] = $_POST['segment_duration'] ?? 10;
        }
        if (isset($_POST['screenshot_time'])) {
            $config['screenshot_time'] = $_POST['screenshot_time'] ?? 10;
        }
        if (isset($_POST['quality'])) {
            $config['quality'] = $_POST['quality'] ?? '1080p';
        }
        // 无论是否勾选，都更新use_gpu配置（只有在基础设置表单提交时）
        $config['use_gpu'] = isset($_POST['use_gpu']) ? 1 : 0;
    }
    
    // 检查是否是数据库设置表单提交
    if (isset($_POST['mysql_host']) || isset($_POST['mysql_port']) || isset($_POST['mysql_db']) || isset($_POST['mysql_user']) || isset($_POST['mysql_password']) || isset($_POST['m3u8_full_url']) || isset($_POST['mysql_enabled'])) {
        // MySQL设置
        // 无论是否勾选，都更新mysql_enabled配置（只有在数据库设置表单提交时）
        $config['mysql_enabled'] = isset($_POST['mysql_enabled']) ? 1 : 0;
        if (isset($_POST['mysql_host'])) {
            $config['mysql_host'] = $_POST['mysql_host'] ?? 'localhost';
        }
        if (isset($_POST['mysql_port'])) {
            $config['mysql_port'] = $_POST['mysql_port'] ?? '3306';
        }
        if (isset($_POST['mysql_db'])) {
            $config['mysql_db'] = $_POST['mysql_db'] ?? 'vod_system';
        }
        if (isset($_POST['mysql_user'])) {
            $config['mysql_user'] = $_POST['mysql_user'] ?? 'root';
        }
        if (isset($_POST['mysql_password'])) {
            $config['mysql_password'] = $_POST['mysql_password'] ?? '';
        }
        if (isset($_POST['m3u8_full_url'])) {
            $config['m3u8_full_url'] = $_POST['m3u8_full_url'] ?? '';
        }
    }
    
    // 保存配置
    $dir = dirname($config_file);
    $dir_writable = is_writable($dir);
    
    if (save_config($config)) {
        $message = '<div class="success">设置保存成功</div>';
    } else {
        $error_msg = '设置保存失败';
        if (!$dir_writable) {
            $error_msg .= ' - 目录无写入权限，请检查 ' . htmlspecialchars($dir) . ' 的权限';
        }
        $message = '<div class="error">' . $error_msg . '</div>';
    }
}

// 读取当前配置
$current_config = read_config();
$current_ffmpeg_path = $current_config['ffmpeg_path'] ?? FFMPEG_PATH;
$current_ffprobe_path = $current_config['ffprobe_path'] ?? FFPROBE_PATH;
$current_input_dir = $current_config['input_dir'] ?? './vodoss/';
$current_output_dir = $current_config['output_dir'] ?? './m3u8/';
$current_base_url = $current_config['base_url'] ?? '';
$current_segment_duration = $current_config['segment_duration'] ?? 10;
$current_screenshot_time = $current_config['screenshot_time'] ?? 10;
$current_quality = $current_config['quality'] ?? '1080p';
$current_use_gpu = $current_config['use_gpu'] ?? 0;

// MySQL配置
$current_mysql_enabled = $current_config['mysql_enabled'] ?? 0;
$current_mysql_host = $current_config['mysql_host'] ?? 'localhost';
$current_mysql_port = $current_config['mysql_port'] ?? '3306';
$current_mysql_db = $current_config['mysql_db'] ?? 'vod_system';
$current_mysql_user = $current_config['mysql_user'] ?? 'root';
$current_mysql_password = $current_config['mysql_password'] ?? '';
$current_m3u8_full_url = $current_config['m3u8_full_url'] ?? '';

// 检查函数是否被禁用（本地副本）
function is_func_disabled($func_name) {
    $disabled = explode(',', ini_get('disable_functions'));
    $disabled = array_map('trim', $disabled);
    return in_array($func_name, $disabled);
}

// 验证FFmpeg路径
function test_ffmpeg_path($path) {
    if (is_func_disabled('exec')) {
        return false;
    }
    $output = [];
    $return_var = 0;
    if (IS_WINDOWS) {
        $full_command = 'cmd /c ' . escapeshellarg($path . ' -version') . ' 2>&1';
    } else {
        $full_command = $path . ' -version 2>&1';
    }
    @exec($full_command, $output, $return_var);
    return $return_var === 0;
}

// 验证FFprobe路径
function test_ffprobe_path($path) {
    if (is_func_disabled('exec')) {
        return false;
    }
    $output = [];
    $return_var = 0;
    if (IS_WINDOWS) {
        $full_command = 'cmd /c ' . escapeshellarg($path . ' -version') . ' 2>&1';
    } else {
        $full_command = $path . ' -version 2>&1';
    }
    @exec($full_command, $output, $return_var);
    return $return_var === 0;
}

$ffmpeg_status = test_ffmpeg_path($current_ffmpeg_path) ? '可用' : '不可用';
$ffprobe_status = test_ffprobe_path($current_ffprobe_path) ? '可用' : '不可用';
?>

<?php include __DIR__ . '/includes/header.php'; ?>

    <!-- 权限信息 -->
    <div class="card">
        <h2>系统状态</h2>
        <?php
        $config_dir = dirname($config_file);
        $is_writable = is_writable($config_dir);
        $config_exists = file_exists($config_file);
        
        // 检查根目录和 data 目录的情况
        $root_dir = __DIR__;
        $root_writable = is_writable($root_dir);
        $data_dir = __DIR__ . DS . 'data';
        $data_dir_exists = is_dir($data_dir);
        $data_writable = $data_dir_exists && is_writable($data_dir);
        ?>
        <div style="padding: 10px; background: <?php echo $is_writable ? '#d4edda' : '#f8d7da'; ?>; border-radius: 4px; margin-bottom: 10px;">
            <strong>配置目录状态：</strong>
            <span style="color: <?php echo $is_writable ? '#155724' : '#721c24'; ?>;">
                <?php echo $is_writable ? '可写入' : '不可写入'; ?>
            </span>
            (<?php echo htmlspecialchars($config_dir); ?>)
        </div>
        <div style="padding: 10px; background: <?php echo $config_exists ? '#d1ecf1' : '#fff3cd'; ?>; border-radius: 4px; margin-bottom: 10px;">
            <strong>配置文件状态：</strong>
            <span style="color: <?php echo $config_exists ? '#0c5460' : '#856404'; ?>;">
                <?php echo $config_exists ? '已存在' : '不存在'; ?>
            </span>
            (<?php echo htmlspecialchars($config_file); ?>)
        </div>
        
        <?php if (!$root_writable || !$data_writable): ?>
        <div style="padding: 10px; background: #fff3cd; border-radius: 4px; margin-bottom: 10px;">
            <strong>目录权限提示：</strong>
            <ul style="margin-top: 5px; margin-left: 20px;">
                <li>根目录 (<?php echo htmlspecialchars($root_dir); ?>): <?php echo $root_writable ? '可写' : '不可写'; ?></li>
                <li>data目录 (<?php echo htmlspecialchars($data_dir); ?>): <?php echo $data_writable ? '可写' : ($data_dir_exists ? '不可写' : '不存在'); ?></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- 选项卡导航 -->
    <div class="tab-navigation">
        <button class="tab-button active" onclick="openTab(event, 'basic-settings')">基础设置</button>
        <button class="tab-button" onclick="openTab(event, 'database-settings')">数据库</button>
    </div>

    <!-- 基础设置选项卡 -->
    <div id="basic-settings" class="tab-content active">
        <div class="card">
            <h2>基础设置</h2>
            <?php echo $message ?? ''; ?>
            
            <form method="post">
            <div class="form-group">
                <label for="ffmpeg_path">FFmpeg路径</label>
                <input type="text" id="ffmpeg_path" name="ffmpeg_path" value="<?php echo htmlspecialchars($current_ffmpeg_path); ?>" placeholder="例如: C:/ffmpeg/bin/ffmpeg.exe">
                <small>如果已添加到系统PATH，可直接使用 'ffmpeg'</small>
                <small>这里是ffmpeg程序路径，不是目录路径，需要包含ffmpeg.exe文件</small>
                <small>状态: <span style="color: <?php echo $ffmpeg_status === '可用' ? 'green' : 'red'; ?>;"><?php echo $ffmpeg_status; ?></span></small>
            </div>
            
            <div class="form-group">
                <label for="ffprobe_path">FFprobe路径</label>
                <input type="text" id="ffprobe_path" name="ffprobe_path" value="<?php echo htmlspecialchars($current_ffprobe_path); ?>" placeholder="例如: C:/ffmpeg/bin/ffprobe.exe">
                <small>如果已添加到系统PATH，可直接使用 'ffprobe'</small>
                <small>用于获取视频信息，可选但推荐配置</small>
                <small>状态: <span style="color: <?php echo $ffprobe_status === '可用' ? 'green' : 'red'; ?>;"><?php echo $ffprobe_status; ?></span></small>
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
                <div style="margin-top: 8px; padding: 10px; background-color: #e8f4f8; border-radius: 4px;">
                    <strong>💡 提示：</strong>
                    <ul style="margin: 5px 0 0 20px; padding: 0;">
                        <li><strong>原画质</strong>：直接复制视频流不重新编码，处理速度极快，画质无损失</li>
                        <li><strong>1080P/720P</strong>：重新编码到指定画质，兼容性更好，但处理速度较慢</li>
                    </ul>
                </div>
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
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="submit" value="保存设置" style="width: auto; padding: 8px 16px;">
            </div>
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
        
        <div class="card">
            <h2>权限问题提示</h2>
            <p>如果设置无法保存，可能是目录无写入权限。您可以：</p>
            <ol>
                <li>
                    <strong>修改目录权限：</strong>
                    <p>确保以下目录有写入权限（755或777）：</p>
                    <ul>
                        <li>根目录：<?php echo htmlspecialchars(__DIR__); ?></li>
                        <li>或 data 目录：<?php echo htmlspecialchars(__DIR__ . DS . 'data'); ?></li>
                    </ul>
                    <p>在 Linux 系统上，可以执行：</p>
                    <pre style="background: #f5f5f5; padding: 8px; border-radius: 4px;">chmod 755 <?php echo htmlspecialchars(__DIR__); ?></pre>
                    或者
                    <pre style="background: #f5f5f5; padding: 8px; border-radius: 4px;">mkdir -p <?php echo htmlspecialchars(__DIR__ . DS . 'data'); ?> && chmod 755 <?php echo htmlspecialchars(__DIR__ . DS . 'data'); ?></pre>
                </li>
                <li>
                    <strong>手动修改配置文件：</strong>
                    <p>创建配置文件并填入以下内容：</p>
                    <p><strong>配置文件位置：</strong><?php echo htmlspecialchars($config_file); ?></p>
                    <p><strong>config.json 格式示例：</strong></p>
                    <pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto;">{
    "ffmpeg_path": "ffmpeg",
    "ffprobe_path": "ffprobe",
    "input_dir": "./vodoss/",
    "output_dir": "./m3u8/",
    "base_url": "",
    "segment_duration": 10,
    "screenshot_time": 10,
    "quality": "1080p",
    "use_gpu": 0,
    "mysql_enabled": 0,
    "mysql_host": "localhost",
    "mysql_port": "3306",
    "mysql_db": "vod_system",
    "mysql_user": "root",
    "mysql_password": "",
    "m3u8_full_url": ""
}</pre>
                </li>
            </ol>
        </div>
    </div>

    <!-- 数据库设置选项卡 -->
    <div id="database-settings" class="tab-content">
        <div class="card">
            <h2>数据库设置</h2>
            <?php echo $message ?? ''; ?>
            
            <!-- 测试结果提示区域 -->
            <div id="test-result" style="margin-bottom: 15px; padding: 10px; border-radius: 4px; display: none;"></div>
            
            <form method="post">
            <div class="form-group">
                <label for="mysql_enabled">是否启动数据库</label>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" id="mysql_enabled" name="mysql_enabled" value="1" <?php echo $current_mysql_enabled == 1 ? 'checked' : ''; ?>>
                    <label for="mysql_enabled">启用MySQL数据库</label>
                </div>
                <small>勾选后启用MySQL数据库功能</small>
            </div>
            
            <div class="form-group">
                <label for="mysql_host">数据库IP</label>
                <input type="text" id="mysql_host" name="mysql_host" value="<?php echo htmlspecialchars($current_mysql_host); ?>" placeholder="例如: localhost 或 127.0.0.1">
                <small>MySQL数据库服务器的IP地址</small>
            </div>
            
            <div class="form-group">
                <label for="mysql_port">数据库端口</label>
                <input type="number" id="mysql_port" name="mysql_port" value="<?php echo $current_mysql_port; ?>" min="1" max="65535">
                <small>MySQL数据库服务器的端口，默认为3306</small>
            </div>
            
            <div class="form-group">
                <label for="mysql_db">数据库</label>
                <input type="text" id="mysql_db" name="mysql_db" value="<?php echo htmlspecialchars($current_mysql_db); ?>" placeholder="例如: vod_system">
                <small>要使用的数据库名称</small>
            </div>
            
            <div class="form-group">
                <label for="mysql_user">用户账号</label>
                <input type="text" id="mysql_user" name="mysql_user" value="<?php echo htmlspecialchars($current_mysql_user); ?>" placeholder="例如: root">
                <small>MySQL数据库的用户名</small>
            </div>
            
            <div class="form-group">
                <label for="mysql_password">数据库密码</label>
                <input type="password" id="mysql_password" name="mysql_password" value="<?php echo htmlspecialchars($current_mysql_password); ?>" placeholder="输入数据库密码">
                <small>MySQL数据库的密码</small>
            </div>
            
            <div class="form-group">
                <label for="m3u8_full_url">m3u8完整链接</label>
                <input type="text" id="m3u8_full_url" name="m3u8_full_url" value="<?php echo htmlspecialchars($current_m3u8_full_url); ?>" placeholder="例如: http://your-domain/m3u8/">
                <small>m3u8文件的完整访问链接</small>
            </div>
            
            <div class="form-group">
                <label>当前状态</label>
                <div><?php echo '是否启动数据库: ' . ($current_mysql_enabled == 1 ? '是' : '否'); ?></div>
                <div><?php echo '数据库IP: ' . htmlspecialchars($current_mysql_host); ?></div>
                <div><?php echo '数据库端口: ' . $current_mysql_port; ?></div>
                <div><?php echo '数据库名称: ' . htmlspecialchars($current_mysql_db); ?></div>
                <div><?php echo '用户名: ' . htmlspecialchars($current_mysql_user); ?></div>
                <div><?php echo '密码: ' . (empty($current_mysql_password) ? '未设置' : '已设置'); ?></div>
                <div><?php echo 'm3u8完整链接: ' . htmlspecialchars($current_m3u8_full_url); ?></div>
            </div>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="submit" value="保存设置" style="width: auto; padding: 8px 16px;">
                <button type="button" onclick="testDatabaseConnection()" style="padding: 8px 16px; background-color: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">测试连接</button>
            </div>
            </form>
        </div>
    </div>

<script>
// 选项卡切换功能
function openTab(evt, tabName) {
    // 隐藏所有选项卡内容
    var tabContents = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].style.display = "none";
        tabContents[i].classList.remove("active");
    }
    
    // 移除所有选项卡按钮的活动状态
    var tabButtons = document.getElementsByClassName("tab-button");
    for (var i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove("active");
    }
    
    // 显示当前选项卡内容并激活按钮
    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}

// 测试数据库连接
function testDatabaseConnection() {
    // 获取表单数据
    var mysql_enabled = document.getElementById('mysql_enabled').checked ? 1 : 0;
    var mysql_host = document.getElementById('mysql_host').value;
    var mysql_port = document.getElementById('mysql_port').value;
    var mysql_db = document.getElementById('mysql_db').value;
    var mysql_user = document.getElementById('mysql_user').value;
    var mysql_password = document.getElementById('mysql_password').value;
    
    // 简单验证
    if (!mysql_enabled) {
        showTestResult('error', '请先启用数据库功能');
        return;
    }
    
    if (!mysql_host || !mysql_port || !mysql_db || !mysql_user) {
        showTestResult('error', '请填写完整的数据库连接信息');
        return;
    }
    
    // 显示加载状态
    var testButton = event.target;
    var originalText = testButton.innerHTML;
    testButton.innerHTML = '测试中...';
    testButton.disabled = true;
    
    // 清空之前的测试结果
    document.getElementById('test-result').style.display = 'none';
    
    // 使用AJAX调用后端脚本
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'mysql/database_connection.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            // 恢复按钮状态
            testButton.innerHTML = originalText;
            testButton.disabled = false;
            
            try {
                var response = JSON.parse(xhr.responseText);
                
                if (response.success) {
                    // 显示成功结果
                    var message = response.message + '<br>';
                    if (response.details && response.details.length > 0) {
                        message += '<br><strong>操作详情：</strong><ul>';
                        response.details.forEach(function(detail) {
                            message += '<li>' + detail + '</li>';
                        });
                        message += '</ul>';
                    }
                    showTestResult('success', message);
                } else {
                    // 显示错误结果
                    showTestResult('error', response.message);
                }
            } catch (e) {
                // 显示解析错误
                showTestResult('error', '测试过程中发生错误：' + e.message);
            }
        }
    };
    
    // 发送请求
    var params = 'mysql_enabled=' + mysql_enabled + 
                 '&mysql_host=' + encodeURIComponent(mysql_host) + 
                 '&mysql_port=' + encodeURIComponent(mysql_port) + 
                 '&mysql_db=' + encodeURIComponent(mysql_db) + 
                 '&mysql_user=' + encodeURIComponent(mysql_user) + 
                 '&mysql_password=' + encodeURIComponent(mysql_password);
    xhr.send(params);
}

// 显示测试结果
function showTestResult(type, message) {
    var resultDiv = document.getElementById('test-result');
    resultDiv.style.display = 'block';
    
    // 设置样式
    if (type === 'success') {
        resultDiv.style.backgroundColor = '#d4edda';
        resultDiv.style.color = '#155724';
        resultDiv.style.border = '1px solid #c3e6cb';
    } else {
        resultDiv.style.backgroundColor = '#f8d7da';
        resultDiv.style.color = '#721c24';
        resultDiv.style.border = '1px solid #f5c6cb';
    }
    
    // 设置内容
    resultDiv.innerHTML = message;
    
    // 滚动到结果区域
    resultDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<style>
/* 选项卡样式 */
.tab-navigation {
    display: flex;
    margin-bottom: 20px;
    border-bottom: 1px solid #ddd;
}

.tab-button {
    background-color: #f1f1f1;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 10px 20px;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.3s ease;
}

.tab-button:hover {
    background-color: #ddd;
}

.tab-button.active {
    background-color: white;
    border-bottom-color: #4CAF50;
    font-weight: bold;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>