<?php
// 转码记录页面

// 检查是否需要安装（如果没有配置文件，重定向到安装页面）
$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';
if (!file_exists($config_file)) {
    header('Location: install.php');
    exit;
}

// 加载配置和函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . DS . 'includes/functions.php';

// 加载密码验证模块
require_once __DIR__ . DS . 'includes/auth.php';

// 需要认证
require_auth();

// 处理清理记录请求
if (isset($_POST['clear_records'])) {
    clear_transcode_records();
    header('Location: history.php');
    exit;
}

// 检查是否有正在转码的任务
$current_transcode = get_current_transcode_task();

// 获取已完成的转码记录
$completed_transcodes = get_completed_transcode_records();
?>

<?php include __DIR__ . '/includes/header.php'; ?>

    <!-- 当前转码进度 -->
    <div class="card">
        <h2>当前转码进度</h2>
        <?php if ($current_transcode): ?>
            <ul class="transcode-list">
                <li class="transcode-item">
                    <div class="transcode-info">
                        <strong>视频名:</strong> <?php echo htmlspecialchars($current_transcode['filename']); ?>
                    </div>
                    <div class="transcode-meta">
                        <span class="meta-tag"><strong>大小:</strong> <?php echo isset($current_transcode['file_size']) ? $current_transcode['file_size'] : '0'; ?> MB</span>
                        <span class="meta-tag"><strong>时长:</strong> <?php echo isset($current_transcode['duration']) ? format_time($current_transcode['duration']) : '00:00:00'; ?></span>
                        <span class="meta-tag"><strong>转码时间:</strong> <?php echo isset($current_transcode['start_time']) ? $current_transcode['start_time'] : '未知'; ?></span>
                        <span class="meta-tag status-processing"><strong>状态:</strong> 转码中</span>
                    </div>
                    <div class="transcode-url">
                        <strong>M3U8地址:</strong>
                        <?php
                        $base_url = isset($current_transcode['options']['base_url']) ? $current_transcode['options']['base_url'] : '';
                        $folder_name = pathinfo($current_transcode['filename'], PATHINFO_FILENAME);
                        $full_url = rtrim($base_url, '/') . '/m3u8/' . $folder_name . '/' . $folder_name . '.m3u8';

                        $display_url = $full_url;
                        if (strlen($full_url) > 50) {
                            $start = substr($full_url, 0, 20);
                            $end = substr($full_url, -20);
                            $display_url = $start . '...' . $end;
                        }
                        ?>
                        <span class="url-display" data-full-url="<?php echo htmlspecialchars($full_url); ?>" onclick="copyToClipboard(this)">
                            <?php echo htmlspecialchars($display_url); ?>
                        </span>
                        <button class="btn-copy" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($full_url); ?>')">复制</button>
                    </div>
                    <div class="transcode-url">
                        <strong>图片地址:</strong>
                        <?php
                        $image_url = rtrim($base_url, '/') . '/m3u8/' . $folder_name . '/' . $folder_name . '.jpg';

                        $display_image_url = $image_url;
                        if (strlen($image_url) > 50) {
                            $start = substr($image_url, 0, 20);
                            $end = substr($image_url, -20);
                            $display_image_url = $start . '...' . $end;
                        }
                        ?>
                        <span class="url-display" data-full-url="<?php echo htmlspecialchars($image_url); ?>" onclick="copyToClipboard(this)">
                            <?php echo htmlspecialchars($display_image_url); ?>
                        </span>
                        <button class="btn-copy" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($image_url); ?>')">复制</button>
                    </div>
                </li>
            </ul>
        <?php else: ?>
            <div class="empty-state">
                <strong>当前没有正在转码的任务</strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- 已完成转码记录 -->
    <div class="card">
        <div class="card-header">
            <h2>已完成转码记录</h2>
            <form method="post" onsubmit="return confirm('确定要清理所有转码记录吗？此操作不可恢复。');">
                <button type="submit" name="clear_records" class="btn btn-danger">清理记录</button>
            </form>
        </div>

        <!-- 记录文件信息 -->
        <div class="record-info">
            <div class="info-row">
                <span class="info-label">记录文件位置:</span>
                <code><?php echo htmlspecialchars(get_transcode_record_file()); ?></code>
            </div>
            <div class="info-row">
                <span class="info-label">记录文件状态:</span>
                <?php
                $record_file = get_transcode_record_file();
                if (file_exists($record_file)) {
                    echo '<span class="badge badge-success">存在</span>';
                    $filesize = filesize($record_file);
                    echo '<span class="badge badge-secondary">' . $filesize . ' 字节</span>';
                } else {
                    echo '<span class="badge badge-danger">不存在</span>';
                }
                ?>
            </div>
            <div class="info-row">
                <span class="info-label">目录可写:</span>
                <?php
                $dir = dirname(get_transcode_record_file());
                if (is_writable($dir)) {
                    echo '<span class="badge badge-success">可写</span>';
                } else {
                    echo '<span class="badge badge-danger">不可写</span>';
                }
                ?>
            </div>
        </div>

        <?php
        $limited_transcodes = array_slice($completed_transcodes, 0, 30);
        if (!empty($limited_transcodes)): ?>
            <div class="json-records">
                <?php foreach ($limited_transcodes as $index => $transcode): ?>
                    <div class="json-record" onclick="selectCode(this)">
                        <pre><?php echo json_encode($transcode, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?><?php echo ($index < count($limited_transcodes) - 1) ? ',' : ''; ?></pre>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="note">
                <strong>提示:</strong> 记录只显示30条，多余记录可前往 <code><?php echo htmlspecialchars(get_transcode_record_file()); ?></code> 查看
            </div>
        <?php else: ?>
            <div class="empty-state">
                <strong>暂无转码记录</strong>
                <p class="empty-hint">如果您之前有转码过但看不到记录，可能是因为：</p>
                <ul class="empty-list">
                    <li>记录文件在其他位置（如根目录），请检查文件系统</li>
                    <li>目录没有写入权限，导致无法保存新记录</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>

<script>
// 复制地址到剪切板
function copyToClipboard(element, fullUrl) {
    if (!fullUrl) {
        fullUrl = element.getAttribute('data-full-url');
    }

    navigator.clipboard.writeText(fullUrl).then(() => {
        if (element.tagName === 'BUTTON') {
            const originalText = element.textContent;
            element.textContent = '已复制';
            element.classList.add('copied');

            setTimeout(() => {
                element.textContent = originalText;
                element.classList.remove('copied');
            }, 2000);
        } else {
            const tempBtn = document.createElement('span');
            tempBtn.className = 'copy-tip';
            tempBtn.textContent = '已复制';
            element.parentNode.appendChild(tempBtn);

            setTimeout(() => {
                tempBtn.remove();
            }, 2000);
        }
    }).catch(err => {
        console.error('复制失败:', err);
        alert('复制失败，请手动复制');
    });
}

// 点击代码框自动全选
function selectCode(element) {
    const pre = element.querySelector('pre');
    const range = document.createRange();
    range.selectNodeContents(pre);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
}
</script>

<style>
    /* 历史页面专用样式 */
    .transcode-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .transcode-item {
        background-color: var(--bg-page);
        border-radius: var(--radius-md);
        padding: 16px;
        margin-bottom: 12px;
        border: 1px solid var(--border-color);
    }

    .transcode-info {
        margin-bottom: 12px;
        font-size: 0.95rem;
    }

    .transcode-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        padding: 12px 0;
        border-top: 1px solid var(--border-light);
        border-bottom: 1px solid var(--border-light);
    }

    .meta-tag {
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .meta-tag.status-processing {
        color: #d97706;
        font-weight: 600;
        background-color: #fef3c7;
        padding: 2px 8px;
        border-radius: 10px;
    }

    .meta-tag.status-success {
        color: #059669;
        font-weight: 600;
        background-color: #d1fae5;
        padding: 2px 8px;
        border-radius: 10px;
    }

    .transcode-url {
        margin-top: 12px;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        position: relative;
    }

    .transcode-url strong {
        min-width: 80px;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .url-display {
        flex: 1;
        word-break: break-all;
        font-size: 0.85rem;
        color: var(--primary-color);
        cursor: pointer;
        min-width: 200px;
    }

    .url-display:hover {
        text-decoration: underline;
    }

    .btn-copy {
        padding: 4px 12px;
        background-color: var(--primary-color);
        color: white;
        border: none;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: 0.8rem;
        transition: all var(--transition-fast);
    }

    .btn-copy:hover {
        background-color: var(--primary-dark);
    }

    .btn-copy.copied {
        background-color: var(--success-color);
    }

    .copy-tip {
        position: absolute;
        right: 0;
        top: -8px;
        background-color: var(--success-color);
        color: white;
        padding: 2px 8px;
        border-radius: var(--radius-sm);
        font-size: 0.75rem;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .card-header h2 {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .record-info {
        padding: 16px;
        background-color: var(--bg-active);
        border-radius: var(--radius-md);
        margin-bottom: 20px;
    }

    .info-row {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 6px 0;
        flex-wrap: wrap;
    }

    .info-label {
        font-weight: 600;
        min-width: 100px;
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

    .badge-secondary {
        background-color: var(--bg-page);
        color: var(--text-secondary);
    }

    .note {
        margin-top: 16px;
        padding: 12px;
        background-color: var(--bg-active);
        border-left: 4px solid var(--primary-color);
        border-radius: var(--radius-sm);
        font-size: 0.85rem;
    }

    .json-records {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 16px;
    }

    .json-record {
        background-color: var(--bg-page);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 14px;
        overflow-x: auto;
        cursor: pointer;
        transition: all var(--transition-fast);
    }

    .json-record:hover {
        border-color: var(--primary-color);
        box-shadow: var(--shadow-sm);
    }

    .json-record pre {
        margin: 0;
        font-family: 'SF Mono', 'Consolas', monospace;
        font-size: 0.8rem;
        line-height: 1.5;
        color: var(--text-primary);
    }

    .json-record pre code {
        background-color: transparent;
        padding: 0;
        border-radius: 0;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted);
    }

    .empty-hint {
        margin-top: 12px;
        color: var(--text-muted);
    }

    .empty-list {
        text-align: left;
        max-width: 400px;
        margin: 12px auto;
    }

    @media (max-width: 768px) {
        .transcode-meta {
            flex-direction: column;
            gap: 8px;
        }

        .transcode-url {
            flex-direction: column;
            align-items: flex-start;
        }

        .url-display {
            width: 100%;
        }

        .card-header {
            flex-direction: column;
            gap: 12px;
            align-items: flex-start;
        }

        .info-row {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .info-label {
            min-width: auto;
        }
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// 移除自动刷新功能
</script>
