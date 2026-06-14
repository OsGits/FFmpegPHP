<?php
// 转码控制面板

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

// 需要认证
require_auth();

// 检测系统硬件信息
$system_info = detect_system();
$gpu_info = $system_info['gpu'];

// 取消前端上传功能，用户需手动将视频文件上传到待转码目录

// 获取服务器文件列表
$server_files = get_server_files();

// 按文件修改时间排序（新视频在上面）
usort($server_files, function($a, $b) {
    return $b['time'] - $a['time'];
});

// 检查是否有正在进行的转码任务
$current_transcode = get_current_transcode_task();
$is_transcoding = !empty($current_transcode);

// 分页设置
$page_size = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$total_files = count($server_files);
$total_pages = ceil($total_files / $page_size);
$start_index = ($page - 1) * $page_size;
$paged_files = array_slice($server_files, $start_index, $page_size);
?>

<?php include __DIR__ . '/includes/header.php'; ?>

    <!-- 上传视频说明 -->
    <div class="card">
        <h2>上传视频文件</h2>
        <div class="upload-info">
            <p>如果有需要转码切片的视频，请上传到待转目录：</p>
            <p class="dir-path"><?php echo UPLOAD_DIR; ?></p>
            <div class="file-hints">
                <small>支持格式: <?php echo implode(', ', $allowed_extensions); ?></small>
                <small>为了考虑系统识别文件的正确性，建议视频文件名不要有特殊符号。</small>
            </div>
        </div>
    </div>

    <!-- 视频处理 -->
    <div class="card">
        <h2>视频转码切割</h2>
        <!-- 已设置好的信息展示 -->
        <div class="settings-display">
            <div class="setting-row">
                <span class="setting-label">TS文件路径设置:</span>
                <span><?php echo htmlspecialchars($base_url); ?></span>
            </div>
            <div class="setting-row">
                <span class="setting-label">切片时长:</span>
                <span><?php echo $default_segment_duration; ?> 秒</span>
            </div>
            <div class="setting-row">
                <span class="setting-label">跳过片头:</span>
                <span><?php echo $default_skip_head_seconds; ?> 秒</span>
            </div>
            <div class="setting-row">
                <span class="setting-label">截图时间点:</span>
                <span><?php echo $default_screenshot_time; ?> 秒</span>
            </div>
            <div class="setting-row">
                <span class="setting-label">画质选择:</span>
                <span><?php echo $default_quality; ?></span>
            </div>
            <div class="setting-row">
                <span class="setting-label">使用GPU加速:</span>
                <span><?php echo ($default_use_gpu == 1 && $gpu_info['available']) ? '是' : '否'; ?></span>
            </div>
            <div class="setting-row">
                <span class="setting-label">转码后保存目录:</span>
                <span><?php echo OUTPUT_DIR; ?></span>
            </div>
        </div>
        <small class="settings-hint">如需修改默认转码设置，请前往 <a href="settings.php">设置</a> 页面</small>

        <!-- 转码状态提示 -->
        <?php if ($is_transcoding): ?>
            <div class="warning">
                <strong>提示:</strong> 当前有转码任务正在执行（<?php echo htmlspecialchars($current_transcode['filename'] ?? '未知文件'); ?>），暂时无法启动新的转码任务。
            </div>
        <?php endif; ?>
    </div>

    <!-- 服务器文件列表 -->
    <?php if (!empty($server_files)): ?>
        <div class="card">
            <form action="z.php" method="post">
                <div class="file-list-header">
                    <h2>服务器文件列表</h2>
                    <input type="hidden" name="batch_transcode" value="1">
                    <input type="hidden" name="base_url" value="<?php echo htmlspecialchars($base_url); ?>">
                    <input type="hidden" name="segment_duration" value="<?php echo $default_segment_duration; ?>">
                    <input type="hidden" name="skip_head_seconds" value="<?php echo $default_skip_head_seconds; ?>">
                    <input type="hidden" name="screenshot_time" value="<?php echo $default_screenshot_time; ?>">
                    <input type="hidden" name="quality" value="<?php echo $default_quality; ?>">
                    <input type="hidden" name="use_gpu" value="<?php echo $default_use_gpu; ?>">
                    <input type="hidden" name="output_dir" value="m3u8">
                    <?php if ($is_transcoding): ?>
                        <button type="button" class="btn btn-secondary" disabled>批量转码</button>
                    <?php else: ?>
                        <span class="tip-text">可定时访问 <a href="z.php">z.php</a> 实现自动转码功能</span>
                    <?php endif; ?>
                </div>
                <ul class="file-list">
                    <?php foreach ($paged_files as $file_info): ?>
                        <?php $file = $file_info['name']; ?>
                        <li class="file-item">
                            <div class="file-main">
                                <?php if ($is_transcoding): ?>
                                    <button type="button" class="btn btn-secondary btn-sm" disabled>稍后再来</button>
                                <?php else: ?>
                                    <button type="button" class="single-transcode-btn btn btn-success btn-sm" data-file="<?php echo htmlspecialchars($file); ?>">开始转码</button>
                                <?php endif; ?>
                                <span class="file-name"><?php echo htmlspecialchars($file); ?></span>
                            </div>
                            <div class="file-meta">
                                <?php
                                    $raw_file = isset($file_info['raw_name']) ? $file_info['raw_name'] : $file;
                                    $file_path = safe_path(UPLOAD_DIR . DS . safe_filename($raw_file));
                                    $file_size = 0;
                                    $file_time = '';
                                    if (file_exists($file_path)) {
                                        $file_size = round(filesize($file_path) / 1024 / 1024, 2);
                                        $file_time = date('Y-m-d H:i:s', $file_info['time']);
                                    }
                                ?>
                                <span class="meta-tag">大小: <?php echo $file_size; ?> MB</span>
                                <span class="meta-tag">修改时间: <?php echo $file_time; ?></span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </form>

            <!-- 分页导航 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>">上一页</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<script>
// 批量转码表单提交处理
const batchForm = document.querySelector('form[action="z.php"]');
if (batchForm) {
    batchForm.addEventListener('submit', function(event) {
        alert('批量转码开始，系统将自动处理待转码目录中的视频文件，请在z.php页面查看转码进度');
    });
}

// 单个文件转码按钮点击处理
document.querySelectorAll('.single-transcode-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        const file = this.getAttribute('data-file');
        const form = document.createElement('form');
        form.action = 'process.php';
        form.method = 'post';
        form.style.display = 'none';

        const fields = {
            'input_file': file,
            'base_url': '<?php echo htmlspecialchars($base_url); ?>',
            'segment_duration': '<?php echo $default_segment_duration; ?>',
            'skip_head_seconds': '<?php echo $default_skip_head_seconds; ?>',
            'screenshot_time': '<?php echo $default_screenshot_time; ?>',
            'quality': '<?php echo $default_quality; ?>',
            'use_gpu': '<?php echo $default_use_gpu; ?>',
            'output_dir': 'm3u8'
        };

        for (const [name, value] of Object.entries(fields)) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }

        document.body.appendChild(form);

        alert('转码开始，请在记录页面查看转码进度');

        form.submit();

        setTimeout(function() {
            window.location.href = 'history.php';
        }, 1000);
    });
});
</script>

<style>
    /* 转码页面专用样式 */
    .upload-info p {
        margin-bottom: 8px;
        color: var(--text-secondary);
    }

    .dir-path {
        font-weight: 600;
        color: var(--primary-color) !important;
        font-family: monospace;
        padding: 8px 12px;
        background-color: var(--bg-page);
        border-radius: var(--radius-sm);
        display: inline-block;
    }

    .file-hints {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-top: 8px;
    }

    .settings-display {
        background-color: var(--bg-page);
        padding: 16px;
        border-radius: var(--radius-md);
        margin-bottom: 12px;
    }

    .setting-row {
        display: flex;
        padding: 6px 0;
        border-bottom: 1px solid var(--border-light);
    }

    .setting-row:last-child {
        border-bottom: none;
    }

    .setting-label {
        font-weight: 600;
        min-width: 140px;
        color: var(--text-secondary);
    }

    .settings-hint {
        display: block;
        margin-top: 12px;
    }

    .file-list-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .file-list-header h2 {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .file-item {
        padding: 12px !important;
        border-bottom: 1px solid var(--border-light) !important;
    }

    .file-main {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }

    .file-name {
        font-weight: 500;
        flex: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .file-meta {
        display: flex;
        gap: 16px;
        padding-left: 60px;
    }

    .meta-tag {
        font-size: 0.8rem;
        color: var(--text-muted);
    }

    .btn-sm {
        padding: 6px 12px;
        font-size: 0.8rem;
    }

    .tip-text {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .tip-text a {
        color: var(--primary);
        text-decoration: none;
    }

    .tip-text a:hover {
        text-decoration: underline;
    }

    @media (max-width: 768px) {
        .file-list-header {
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
        }

        .file-main {
            flex-direction: column;
            align-items: flex-start;
        }

        .file-name {
            width: 100%;
        }

        .file-meta {
            padding-left: 0;
            flex-direction: column;
            gap: 4px;
        }

        .setting-row {
            flex-direction: column;
            gap: 4px;
        }

        .setting-label {
            min-width: auto;
        }
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
