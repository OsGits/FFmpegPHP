<?php
// 后台管理首页

// 检查是否需要安装（如果没有配置文件，重定向到安装页面）
$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';
if (!file_exists($config_file)) {
    header('Location: install.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . DS . 'includes/functions.php';
require_once __DIR__ . DS . 'includes/hardware_detection.php';

// 加载密码验证模块
require_once __DIR__ . DS . 'includes/auth.php';

// 处理认证错误
$auth_error = isset($_GET['auth_error']) ? '<div class="error">密码错误，请重试</div>' : '';

// 处理认证动作
if (isset($_GET['action'])) {
    handle_auth_action($_GET['action']);
}

// 需要认证
require_auth();

// 检测系统硬件信息
$system_info = detect_system();
$gpu_info = $system_info['gpu'];

// 读取版本信息
$version_file = __DIR__ . DIRECTORY_SEPARATOR . 'version.json';
$version_info = [
    'version' => 'Unknown',
    'release_date' => '',
    'download_url' => '',
    'changelog_url' => '',
    'github_api' => ''
];
if (file_exists($version_file)) {
    $version_data = json_decode(file_get_contents($version_file), true);
    if ($version_data) {
        $version_info = array_merge($version_info, $version_data);
    }
}
$local_version = $version_info['version'];

// 从GitHub获取最新版本号
function get_github_latest_version($api_url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: FFmpegPHP'
            ]
        ]
    ]);

    $response = @file_get_contents($api_url, false, $context);
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['tag_name'])) {
            return $data['tag_name'];
        }
    }
    return null;
}

$latest_version = get_github_latest_version($version_info['github_api']);
$has_update = ($latest_version && version_compare(str_replace('V', '', $latest_version), str_replace('V', '', $local_version), '>'));
?>

<?php include __DIR__ . '/includes/header.php'; ?>

    <?php echo $auth_error; ?>

    <!-- 系统状态 -->
    <div class="card">
        <h2>系统状态</h2>
        <div class="status-item">
            <span class="status-label">FFmpeg状态:</span>
            <?php if ($ffmpeg_available): ?>
                <span class="status-success"><span class="status-dot status-success"></span>已安装</span>
            <?php else: ?>
                <span class="status-error"><span class="status-dot status-error"></span>未安装</span>
                <p class="error">FFmpeg未安装，无法进行视频处理。请先安装FFmpeg并添加到系统PATH。</p>
            <?php endif; ?>
        </div>
        <div class="status-item">
            <span class="status-label">GPU状态:</span>
            <?php if ($gpu_info['available']): ?>
                <span class="status-success"><span class="status-dot status-success"></span>已检测到</span>
                <div class="gpu-info">
                    <span>GPU型号: <?php echo $gpu_info['model']; ?></span>
                    <span>可用的GPU加速方式: <?php echo implode(', ', $gpu_info['methods']); ?></span>
                </div>
            <?php else: ?>
                <span class="status-warning"><span class="status-dot status-warning"></span>未检测到</span>
                <p class="warning">未检测到可用的GPU加速，只能使用CPU转码</p>
            <?php endif; ?>
        </div>
        <div class="status-item">
            <span class="status-label">待转码目录:</span>
            <span><?php echo UPLOAD_DIR; ?></span>
            <span class="badge <?php echo is_writable(UPLOAD_DIR) ? 'badge-success' : 'badge-danger'; ?>"><?php echo is_writable(UPLOAD_DIR) ? '可写' : '不可写'; ?></span>
        </div>
        <div class="status-item">
            <span class="status-label">转码后保存目录:</span>
            <span><?php echo OUTPUT_DIR; ?></span>
            <span class="badge <?php echo is_writable(OUTPUT_DIR) ? 'badge-success' : 'badge-danger'; ?>"><?php echo is_writable(OUTPUT_DIR) ? '可写' : '不可写'; ?></span>
        </div>
        <div class="status-item">
            <span class="status-label">TS文件路径设置:</span>
            <span><?php echo htmlspecialchars($base_url); ?></span>
        </div>
        <div class="status-item">
            <span class="status-label">切片时长:</span>
            <span><?php echo $default_segment_duration; ?> 秒</span>
        </div>
        <div class="status-item">
            <span class="status-label">截图时间点:</span>
            <span><?php echo $default_screenshot_time; ?> 秒</span>
        </div>
        <div class="status-item">
            <span class="status-label">画质选择:</span>
            <span><?php echo $default_quality; ?></span>
        </div>
        <div class="status-item">
            <span class="status-label">使用GPU加速:</span>
            <span><?php echo ($default_use_gpu == 1 && $gpu_info['available']) ? '是' : '否'; ?></span>
        </div>
    </div>

    <!-- 版本信息 -->
    <div class="card">
        <h2>版本信息</h2>
        <div class="version-info">
            <div class="version-row">
                <span class="status-label">本地版本:</span>
                <span><?php echo htmlspecialchars($local_version); ?></span>
            </div>
            <div class="version-row">
                <span class="status-label">最新版本:</span>
                <span><?php echo $latest_version ? htmlspecialchars($latest_version) : '获取失败'; ?></span>
                <?php if ($has_update): ?>
                    <span class="badge badge-danger">有更新</span>
                <?php elseif ($latest_version): ?>
                    <span class="badge badge-success">已是最新</span>
                <?php endif; ?>
            </div>
            <?php if ($has_update): ?>
                <div class="update-actions">
                    <a href="update.php" class="btn btn-success">在线更新</a>
                    <a href="<?php echo htmlspecialchars($version_info['download_url']); ?>" target="_blank" class="btn btn-secondary">离线下载</a>
                </div>
            <?php endif; ?>
        </div>
        <div class="changelog-link">
            <a href="<?php echo htmlspecialchars($version_info['changelog_url']); ?>" target="_blank">查看更新记录</a>
        </div>
    </div>

    <!-- 使用说明 -->
    <div class="card">
        <h2>使用说明</h2>
        <div class="guide-section">
            <h3>功能介绍</h3>
            <ul>
                <li><strong>首页:</strong> 显示系统状态、版本号和使用说明</li>
                <li><strong>转码:</strong> 提供视频转码控制面板，支持选择文件、设置参数并开始转码</li>
                <li><strong>记录:</strong> 显示当前转码进度和已完成的转码记录</li>
                <li><strong>设置:</strong> 配置转码选项，包括GPU/CPU选择和目录设置</li>
            </ul>
        </div>
        <div class="guide-section">
            <h3>使用步骤</h3>
            <ol>
                <li>确保FFmpeg已正确安装并配置</li>
                <li>将需要转码的视频文件放入待转码目录</li>
                <li>在"转码"页面选择视频文件并设置参数</li>
                <li>点击"开始转码切割"按钮开始转码</li>
                <li>在"记录"页面查看转码进度和结果</li>
            </ol>
        </div>
    </div>

<style>
    /* 首页专用样式 */
    .status-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid var(--border-light);
        flex-wrap: wrap;
    }

    .status-item:last-child {
        border-bottom: none;
    }

    .status-label {
        font-weight: 600;
        min-width: 120px;
        color: var(--text-secondary);
    }

    .status-success {
        color: #059669;
        font-weight: 600;
        background-color: #d1fae5;
        padding: 2px 10px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-error {
        color: #dc2626;
        font-weight: 600;
        background-color: #fee2e2;
        padding: 2px 10px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-warning {
        color: #d97706;
        font-weight: 600;
        background-color: #fef3c7;
        padding: 2px 10px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .gpu-info {
        width: 100%;
        padding-left: 130px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        font-size: 0.875rem;
        color: var(--text-secondary);
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-success {
        background-color: #ECFDF5;
        color: var(--success-dark);
    }

    .badge-danger {
        background-color: #FEF2F2;
        color: var(--danger-dark);
    }

    .badge-warning {
        background-color: #FFFBEB;
        color: var(--warning-dark);
    }

    .version-info {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .version-row {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .update-actions {
        display: flex;
        gap: 10px;
        margin-top: 12px;
    }

    .changelog-link {
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid var(--border-light);
    }

    .guide-section {
        margin-bottom: 20px;
    }

    .guide-section:last-child {
        margin-bottom: 0;
    }

    @media (max-width: 768px) {
        .status-item {
            flex-direction: column;
            align-items: flex-start;
        }

        .status-label {
            min-width: auto;
        }

        .gpu-info {
            padding-left: 0;
        }

        .update-actions {
            flex-direction: column;
        }

        .update-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
