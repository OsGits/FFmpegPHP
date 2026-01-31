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
    // 读取当前配置
    $config = read_config();
    
    // 只更新实际被提交的字段
    // 基础设置
    if (isset($_POST['ffmpeg_path'])) {
        $config['ffmpeg_path'] = $_POST['ffmpeg_path'] ?? 'ffmpeg';
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
    if (isset($_POST['use_gpu'])) {
        $config['use_gpu'] = $_POST['use_gpu'] ?? 0;
    }
    
    // MySQL设置
    if (isset($_POST['mysql_enabled'])) {
        $config['mysql_enabled'] = $_POST['mysql_enabled'] ?? 0;
    }
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

// MySQL配置
$current_mysql_enabled = $current_config['mysql_enabled'] ?? 0;
$current_mysql_host = $current_config['mysql_host'] ?? 'localhost';
$current_mysql_port = $current_config['mysql_port'] ?? '3306';
$current_mysql_db = $current_config['mysql_db'] ?? 'vod_system';
$current_mysql_user = $current_config['mysql_user'] ?? 'root';
$current_mysql_password = $current_config['mysql_password'] ?? '';
$current_m3u8_full_url = $current_config['m3u8_full_url'] ?? '';

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