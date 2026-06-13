<?php
// 设置管理脚本

// 检查是否需要安装（如果没有配置文件，重定向到安装页面）
$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';
if (!file_exists($config_file)) {
    header('Location: install.php');
    exit;
}

// 加载配置和硬件检测
require_once __DIR__ . '/config.php';
require_once __DIR__ . DS . 'includes/functions.php';
require_once __DIR__ . DS . 'includes/hardware_detection.php';

// 加载密码验证模块
require_once __DIR__ . DS . 'includes/auth.php';

// 处理认证动作
if (isset($_GET['action'])) {
    handle_auth_action($_GET['action']);
}

// 需要认证
require_auth();

// 处理密码修改请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['current_password'])) {
    $password_result = handle_password_change();
    if (strpos($password_result, 'error') === false && strpos($password_result, 'success') === false) {
        // 没有错误或成功消息，说明是重定向
    } else {
        $password_message = $password_result;
    }
}

// 检查URL中的密码消息参数
if (isset($_GET['password_msg'])) {
    switch ($_GET['password_msg']) {
        case 'success':
            $password_message = '<div class="success">密码修改成功，请使用新密码重新登录！</div>';
            break;
        case 'error_current':
            $password_message = '<div class="error">当前密码错误</div>';
            break;
        case 'error_length':
            $password_message = '<div class="error">新密码长度至少4位</div>';
            break;
        case 'error_mismatch':
            $password_message = '<div class="error">两次输入的密码不一致</div>';
            break;
        case 'error_save':
            $password_message = '<div class="error">密码修改失败，请检查目录权限</div>';
            break;
    }
}

// 检测系统硬件信息
$system_info = detect_system();
$gpu_info = $system_info['gpu'];

// 使用 config.php 中定义的配置文件路径
$config_file = defined('CONFIG_FILE_PATH') ? CONFIG_FILE_PATH : safe_path(__DIR__ . DS . 'data' . DS . 'config.php');

// 判断配置文件格式
function get_config_format($config_file) {
    if (strpos($config_file, '.php') !== false) {
        return 'php';
    }
    return 'json';
}

// 读取现有配置
function read_config() {
    global $config_file;
    global $config;
    $config_format = get_config_format($config_file);

    if (file_exists($config_file)) {
        if ($config_format === 'php') {
            $saved_config = [];
            @include $config_file;
            if (isset($saved_config) && is_array($saved_config)) {
                return $saved_config;
            }
            return [];
        } else {
            $content = @file_get_contents($config_file);
            $loaded = json_decode($content, true) ?? [];
            return $loaded;
        }
    }
    return $config ?? [];
}

// 保存配置
function save_config($config) {
    global $config_file;
    $config_format = get_config_format($config_file);

    $dir = dirname($config_file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    if (!is_writable($dir)) {
        return false;
    }

    $result = false;

    if ($config_format === 'php') {
        $php_content = '<?php
/**
 * 配置文件 - PHP 格式
 * 此文件包含敏感信息，请确保 Web 服务器禁止直接访问 .php 文件
 *
 * 配置将通过设置页面自动管理
 */

$saved_config = ' . var_export($config, true) . ';
';
        $result = @file_put_contents($config_file, $php_content);
    } else {
        $content = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $result = @file_put_contents($config_file, $content);
    }

    return $result !== false;
}

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = read_config();

    if (isset($_POST['ffmpeg_path']) || isset($_POST['input_dir']) || isset($_POST['output_dir']) || isset($_POST['base_url']) || isset($_POST['segment_duration']) || isset($_POST['screenshot_time']) || isset($_POST['quality']) || isset($_POST['use_gpu'])) {
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
        $config['use_gpu'] = isset($_POST['use_gpu']) ? 1 : 0;
    }

    if (isset($_POST['mysql_host']) || isset($_POST['mysql_port']) || isset($_POST['mysql_db']) || isset($_POST['mysql_user']) || isset($_POST['mysql_password']) || isset($_POST['m3u8_full_url']) || isset($_POST['mysql_enabled'])) {
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

// 检查函数是否被禁用
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

        $root_dir = __DIR__;
        $root_writable = is_writable($root_dir);
        $data_dir = __DIR__ . DS . 'data';
        $data_dir_exists = is_dir($data_dir);
        $data_writable = $data_dir_exists && is_writable($data_dir);
        ?>
        <div class="status-row <?php echo $is_writable ? 'status-success' : 'status-error'; ?>">
            <span class="status-label">配置目录状态：</span>
            <span><?php echo $is_writable ? '可写入' : '不可写入'; ?></span>
            <code><?php echo htmlspecialchars($config_dir); ?></code>
        </div>
        <div class="status-row <?php echo $config_exists ? 'status-info' : 'status-warning'; ?>">
            <span class="status-label">配置文件状态：</span>
            <span><?php echo $config_exists ? '已存在' : '不存在'; ?></span>
            <code><?php echo htmlspecialchars($config_file); ?></code>
        </div>

        <?php if (!$data_writable): ?>
        <div class="warning">
            <strong>目录权限提示：</strong>
            <ul class="warning-list">
                <li>data目录 (<?php echo htmlspecialchars($data_dir); ?>): <?php echo $data_writable ? '可写' : ($data_dir_exists ? '不可写' : '不存在'); ?></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- 选项卡导航 -->
    <div class="tab-navigation">
        <button class="tab-button active" onclick="openTab(event, 'basic-settings')">基础设置</button>
        <button class="tab-button" onclick="openTab(event, 'database-settings')">数据库</button>
        <button class="tab-button" onclick="openTab(event, 'password-settings')">修改密码</button>
    </div>

    <!-- 基础设置选项卡 -->
    <div id="basic-settings" class="tab-content active">
        <div class="card">
            <h2>基础设置</h2>
            <?php echo $message ?? ''; ?>

            <form method="post">
            <div class="form-group">
                <label for="ffmpeg_path">FFmpeg路径(必填)</label>
                <input type="text" id="ffmpeg_path" name="ffmpeg_path" value="<?php echo htmlspecialchars($current_ffmpeg_path); ?>" placeholder="例如: C:/ffmpeg/bin/ffmpeg.exe">
                <small>如果已添加到系统PATH，可直接使用 'ffmpeg'</small>
                <small>这里是ffmpeg程序路径，不是目录路径，需要包含ffmpeg.exe文件</small>
                <small>状态: <span class="status-<?php echo $ffmpeg_status === '可用' ? 'success' : 'error'; ?>"><?php echo $ffmpeg_status; ?></span></small>
            </div>

            <div class="form-group">
                <label for="ffprobe_path">FFprobe路径(必填)</label>
                <input type="text" id="ffprobe_path" name="ffprobe_path" value="<?php echo htmlspecialchars($current_ffprobe_path); ?>" placeholder="例如: C:/ffmpeg/bin/ffprobe.exe">
                <small>如果已添加到系统PATH，可直接使用 'ffprobe'</small>
                <small>用于获取视频信息，可选但推荐配置,一般情况与FFmpeg路径相同</small>
                <small>状态: <span class="status-<?php echo $ffprobe_status === '可用' ? 'success' : 'error'; ?>"><?php echo $ffprobe_status; ?></span></small>
            </div>

            <div class="form-group">
                <label for="input_dir">待转码目录(必填)</label>
                <input type="text" id="input_dir" name="input_dir" value="<?php echo htmlspecialchars($current_input_dir); ?>" placeholder="./vodoss/">
                <small>默认为 ./vodoss/，表示根目录下的vodoss文件夹</small>
            </div>

            <div class="form-group">
                <label for="output_dir">转码后保存目录(必填)</label>
                <input type="text" id="output_dir" name="output_dir" value="<?php echo htmlspecialchars($current_output_dir); ?>" placeholder="./m3u8/">
                <small>默认为 ./m3u8/，表示转码后保存的文件目录</small>
            </div>

            <div class="form-group">
                <label for="base_url">TS文件路径设置(必填)</label>
                <input type="text" id="base_url" name="base_url" value="<?php echo htmlspecialchars($current_base_url); ?>" placeholder="例如: http://your-domain/output/ 结尾加'/'">
                <small>TS文件的基础访问地址，会添加到m3u8文件中的每个TS文件路径前！</small>
                <small>最终在m3u8文件中合成的路径为：http(s)://TS文件基础地址/转码后保存目录/m3u8/视频文件名/index.m3u8</small>
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
                <div class="tip-box">
                    <strong>提示：</strong>
                    <ul class="tip-list">
                        <li><strong>原画质</strong>：直接复制视频流不重新编码，处理速度极快，画质无损失</li>
                        <li><strong>1080P/720P</strong>：重新编码到指定画质，兼容性更好，但处理速度较慢</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="use_gpu">使用GPU加速(可选)</label>
                <div class="checkbox-row">
                    <input type="checkbox" id="use_gpu" name="use_gpu" value="1" <?php echo $current_use_gpu == 1 ? 'checked' : ''; ?> <?php echo !$gpu_info['available'] ? 'disabled' : ''; ?>>
                    <label for="use_gpu" class="status-<?php echo $gpu_info['available'] ? 'success' : 'error'; ?>">
                        <?php echo $gpu_info['available'] ? '使用GPU加速' : '未检测到GPU，无法使用GPU加速'; ?>
                    </label>
                </div>
                <small><?php echo $gpu_info['available'] ? '勾选后使用GPU加速转码' : '未检测到GPU，只能使用CPU'; ?></small>
            </div>

            <div class="form-group">
                <label>当前状态</label>
                <div class="status-summary">
                    <div><span>FFmpeg路径:</span> <code><?php echo htmlspecialchars($current_ffmpeg_path); ?></code></div>
                    <div><span>状态:</span> <span class="status-<?php echo $ffmpeg_status === '可用' ? 'success' : 'error'; ?>"><?php echo $ffmpeg_status; ?></span></div>
                    <div><span>待转码目录:</span> <code><?php echo htmlspecialchars($current_input_dir); ?></code></div>
                    <div><span>转码后目录:</span> <code><?php echo htmlspecialchars($current_output_dir); ?></code></div>
                    <div><span>基础地址:</span> <code><?php echo htmlspecialchars($current_base_url); ?></code></div>
                    <div><span>切片时长:</span> <?php echo $current_segment_duration; ?> 秒</div>
                    <div><span>截图时间点:</span> <?php echo $current_screenshot_time; ?> 秒</div>
                    <div><span>画质选择:</span> <?php echo $current_quality; ?></div>
                    <div><span>使用GPU加速:</span> <?php echo ($current_use_gpu == 1 ? '是' : '否'); ?></div>
                </div>
            </div>

            <div class="form-actions">
                <input type="submit" value="保存设置">
            </div>
            </form>
        </div>

        <div class="card">
            <h2>设置说明</h2>
            <ul class="info-list">
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
            <ol class="info-list">
                <li>
                    <strong>修改目录权限：</strong>
                    <p>确保以下目录有写入权限（755或777）：</p>
                    <ul class="info-list">
                        <li>根目录：<?php echo htmlspecialchars(__DIR__); ?></li>
                        <li>或 data 目录：<?php echo htmlspecialchars(__DIR__ . DS . 'data'); ?></li>
                    </ul>
                    <p>在 Linux 系统上，可以执行：</p>
                    <pre class="code-block">chmod 755 <?php echo htmlspecialchars(__DIR__); ?></pre>
                    或者
                    <pre class="code-block">mkdir -p <?php echo htmlspecialchars(__DIR__ . DS . 'data'); ?> && chmod 755 <?php echo htmlspecialchars(__DIR__ . DS . 'data'); ?></pre>
                </li>
                <li>
                    <strong>手动修改配置文件：</strong>
                    <p>创建配置文件并填入以下内容：</p>
                    <p><strong>配置文件位置：</strong><code><?php echo htmlspecialchars($config_file); ?></code></p>
                    <p><strong>config.php 格式示例：</strong></p>
                    <pre class="code-block"><?php echo htmlspecialchars('<?php
/**
 * 配置文件 - PHP 格式
 */

$saved_config = [
    \'ffmpeg_path\' => \'ffmpeg\',
    \'ffprobe_path\' => \'ffprobe\',
    \'input_dir\' => \'./vodoss/\',
    \'output_dir\' => \'./m3u8/\',
    \'base_url\' => \'\',
    \'segment_duration\' => 10,
    \'screenshot_time\' => 10,
    \'quality\' => \'original\',
    \'use_gpu\' => 0,
    \'mysql_enabled\' => 0,
    \'mysql_host\' => \'localhost\',
    \'mysql_port\' => \'3306\',
    \'mysql_db\' => \'vod_system\',
    \'mysql_user\' => \'root\',
    \'mysql_password\' => \'\',
    \'m3u8_full_url\' => \'\',
];'); ?></pre>
                </li>
            </ol>
        </div>
    </div>

    <!-- 数据库设置选项卡 -->
    <div id="database-settings" class="tab-content">
        <div class="card">
            <h2>数据库设置</h2>
            <?php echo $message ?? ''; ?>

            <div id="test-result" class="test-result"></div>

            <form method="post">
            <div class="form-group">
                <label for="mysql_enabled">是否启动数据库</label>
                <div class="checkbox-row">
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
                <div class="status-summary">
                    <div><span>是否启动数据库:</span> <?php echo ($current_mysql_enabled == 1 ? '是' : '否'); ?></div>
                    <div><span>数据库IP:</span> <code><?php echo htmlspecialchars($current_mysql_host); ?></code></div>
                    <div><span>数据库端口:</span> <?php echo $current_mysql_port; ?></div>
                    <div><span>数据库名称:</span> <code><?php echo htmlspecialchars($current_mysql_db); ?></code></div>
                    <div><span>用户名:</span> <code><?php echo htmlspecialchars($current_mysql_user); ?></code></div>
                    <div><span>密码:</span> <?php echo (empty($current_mysql_password) ? '未设置' : '已设置'); ?></div>
                    <div><span>m3u8完整链接:</span> <code><?php echo htmlspecialchars($current_m3u8_full_url); ?></code></div>
                </div>
            </div>

            <div class="form-actions">
                <input type="submit" value="保存设置">
                <button type="button" onclick="testDatabaseConnection()" class="btn btn-success">测试连接</button>
            </div>
            </form>
        </div>
    </div>

    <!-- 密码修改选项卡 -->
    <div id="password-settings" class="tab-content">
        <div class="card">
            <h2>修改访问密码</h2>
            <?php echo $password_message ?? ''; ?>

            <form method="post" action="settings.php?action=change_password">
            <div class="form-group">
                <label for="current_password">当前密码</label>
                <input type="password" id="current_password" name="current_password" placeholder="请输入当前密码" required>
            </div>

            <div class="form-group">
                <label for="new_password">新密码</label>
                <input type="password" id="new_password" name="new_password" placeholder="请输入新密码（至少4位）" required minlength="4">
            </div>

            <div class="form-group">
                <label for="confirm_new_password">确认新密码</label>
                <input type="password" id="confirm_new_password" name="confirm_new_password" placeholder="请再次输入新密码" required minlength="4">
            </div>

            <div class="form-actions">
                <input type="submit" value="修改密码" class="btn btn-primary">
            </div>
            </form>
        </div>
    </div>

<script>
// 选项卡切换功能
function openTab(evt, tabName) {
    var tabContents = document.getElementsByClassName("tab-content");
    for (var i = 0; i < tabContents.length; i++) {
        tabContents[i].style.display = "none";
        tabContents[i].classList.remove("active");
    }

    var tabButtons = document.getElementsByClassName("tab-button");
    for (var i = 0; i < tabButtons.length; i++) {
        tabButtons[i].classList.remove("active");
    }

    document.getElementById(tabName).style.display = "block";
    document.getElementById(tabName).classList.add("active");
    evt.currentTarget.classList.add("active");
}

// 测试数据库连接
function testDatabaseConnection() {
    var mysql_enabled = document.getElementById('mysql_enabled').checked ? 1 : 0;
    var mysql_host = document.getElementById('mysql_host').value;
    var mysql_port = document.getElementById('mysql_port').value;
    var mysql_db = document.getElementById('mysql_db').value;
    var mysql_user = document.getElementById('mysql_user').value;
    var mysql_password = document.getElementById('mysql_password').value;

    if (!mysql_enabled) {
        showTestResult('error', '请先启用数据库功能');
        return;
    }

    if (!mysql_host || !mysql_port || !mysql_db || !mysql_user) {
        showTestResult('error', '请填写完整的数据库连接信息');
        return;
    }

    var testButton = event.target;
    var originalText = testButton.innerHTML;
    testButton.innerHTML = '测试中...';
    testButton.disabled = true;

    document.getElementById('test-result').style.display = 'none';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'mysql/database_connection.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            testButton.innerHTML = originalText;
            testButton.disabled = false;

            try {
                var response = JSON.parse(xhr.responseText);

                if (response.success) {
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
                    showTestResult('error', response.message);
                }
            } catch (e) {
                showTestResult('error', '测试过程中发生错误：' + e.message);
            }
        }
    };

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

    if (type === 'success') {
        resultDiv.className = 'test-result test-success';
    } else {
        resultDiv.className = 'test-result test-error';
    }

    resultDiv.innerHTML = message;

    resultDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<style>
    /* 设置页面专用样式 */
    .status-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: var(--radius-md);
        margin-bottom: 10px;
        flex-wrap: wrap;
    }

    .status-row.status-success {
        background-color: #ECFDF5;
    }

    .status-row.status-error {
        background-color: #FEF2F2;
    }

    .status-row.status-info {
        background-color: var(--bg-active);
    }

    .status-row.status-warning {
        background-color: #FFFBEB;
    }

    .status-label {
        font-weight: 600;
        min-width: 100px;
    }

    .status-success {
        color: #059669;
        font-weight: 600;
    }

    .status-error {
        color: #dc2626;
        font-weight: 600;
    }

    .warning-list {
        margin-top: 8px;
        margin-left: 20px;
    }

    /* 选项卡样式 */
    .tab-navigation {
        display: flex;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--border-color);
        gap: 4px;
    }

    .tab-button {
        background-color: var(--bg-page);
        border: none;
        border-bottom: 3px solid transparent;
        padding: 12px 24px;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 500;
        transition: all var(--transition-fast);
        color: var(--text-secondary);
        border-radius: var(--radius-md) var(--radius-md) 0 0;
    }

    .tab-button:hover {
        background-color: var(--bg-hover);
        color: var(--text-primary);
    }

    .tab-button.active {
        background-color: var(--bg-card);
        border-bottom-color: var(--primary-color);
        color: var(--primary-color);
        font-weight: 600;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    /* 表单样式 */
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 20px;
    }

    .checkbox-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .checkbox-row label {
        font-weight: normal;
    }

    .tip-box {
        margin-top: 12px;
        padding: 12px;
        background-color: var(--bg-active);
        border-radius: var(--radius-md);
        font-size: 0.85rem;
    }

    .tip-list {
        margin: 8px 0 0 20px;
        padding: 0;
    }

    .tip-list li {
        margin-bottom: 4px;
        color: var(--text-secondary);
    }

    .status-summary {
        display: flex;
        flex-direction: column;
        gap: 6px;
        padding: 12px;
        background-color: var(--bg-page);
        border-radius: var(--radius-md);
    }

    .status-summary > div {
        display: flex;
        gap: 8px;
        font-size: 0.9rem;
    }

    .status-summary span {
        font-weight: 600;
        min-width: 100px;
        color: var(--text-secondary);
    }

    .info-list {
        margin-left: 20px;
    }

    .info-list li {
        margin-bottom: 8px;
    }

    .code-block {
        background-color: var(--bg-page);
        padding: 12px;
        border-radius: var(--radius-md);
        overflow-x: auto;
        font-family: 'SF Mono', 'Consolas', monospace;
        font-size: 0.8rem;
        margin: 8px 0;
    }

    /* 测试结果 */
    .test-result {
        margin-bottom: 16px;
        padding: 14px;
        border-radius: var(--radius-md);
        font-size: 0.9rem;
        display: none;
    }

    .test-success {
        background-color: #ECFDF5;
        color: var(--success-dark);
        border: 1px solid #A7F3D0;
    }

    .test-error {
        background-color: #FEF2F2;
        color: var(--danger-dark);
        border: 1px solid #FECACA;
    }

    @media (max-width: 768px) {
        .status-row {
            flex-direction: column;
            align-items: flex-start;
        }

        .status-label {
            min-width: auto;
        }

        .tab-navigation {
            flex-direction: column;
            border-bottom: none;
        }

        .tab-button {
            border-radius: var(--radius-md);
            border-bottom: none;
            border-left: 3px solid transparent;
        }

        .tab-button.active {
            border-left-color: var(--primary-color);
            border-bottom: none;
        }

        .form-actions {
            flex-direction: column;
        }

        .form-actions input[type="submit"],
        .form-actions .btn {
            width: 100%;
        }

        .status-summary > div {
            flex-direction: column;
            gap: 2px;
        }

        .status-summary span {
            min-width: auto;
        }
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
