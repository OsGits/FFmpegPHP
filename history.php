<?php
// 转码记录页面

// 加载配置和函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// 处理清理记录请求
if (isset($_POST['clear_records'])) {
    clear_transcode_records();
    // 重定向以刷新页面
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
                        <span class="meta-item"><strong>大小:</strong> <?php echo isset($current_transcode['file_size']) ? $current_transcode['file_size'] : '0'; ?> MB</span>
                        <span class="meta-item"><strong>时长:</strong> <?php echo isset($current_transcode['duration']) ? format_time($current_transcode['duration']) : '00:00:00'; ?></span>
                        <span class="meta-item"><strong>转码时间:</strong> <?php echo isset($current_transcode['start_time']) ? $current_transcode['start_time'] : '未知'; ?></span>
                        <span class="meta-item status-processing"><strong>状态:</strong> 转码中</span>
                    </div>
                    <div class="transcode-url">
                        <strong>M3U8地址:</strong> 
                        <?php 
                        // 构建完整地址
                        $base_url = isset($current_transcode['options']['base_url']) ? $current_transcode['options']['base_url'] : '';
                        $folder_name = pathinfo($current_transcode['filename'], PATHINFO_FILENAME);
                        $full_url = rtrim($base_url, '/') . '/m3u8/' . $folder_name . '/index.m3u8';
                        
                        // 生成省略号版本的地址
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
                        <button class="copy-btn" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($full_url); ?>')">复制</button>
                    </div>
                    <div class="transcode-url">
                        <strong>图片地址:</strong> 
                        <?php 
                        // 构建完整地址
                        $image_url = rtrim($base_url, '/') . '/m3u8/' . $folder_name . '/index.jpg';
                        
                        // 生成省略号版本的地址
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
                        <button class="copy-btn" onclick="copyToClipboard(this, '<?php echo htmlspecialchars($image_url); ?>')">复制</button>
                    </div>
                </li>
            </ul>
        <?php else: ?>
            <div class="transcode-item">
                <strong>当前没有正在转码的任务</strong>
            </div>
        <?php endif; ?>
    </div>

    <!-- 已完成转码记录 -->
    <div class="card">
        <div class="card-header">
            <h2>已完成转码记录</h2>
            <form method="post" onsubmit="return confirm('确定要清理所有转码记录吗？此操作不可恢复。');">
                <button type="submit" name="clear_records" class="clear-btn">清理记录</button>
            </form>
        </div>
        <?php 
        // 限制只显示30条记录
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
                <strong>提示:</strong> 记录只显示30条，多余记录可前往 <code>e:\wwwroot\z.m\ting.json</code> 查看
            </div>
        <?php else: ?>
            <div class="transcode-item">
                <strong>暂无转码记录</strong>
            </div>
        <?php endif; ?>
    </div>

<script>
// 复制地址到剪切板
function copyToClipboard(element, fullUrl) {
    // 如果是点击地址文本
    if (!fullUrl) {
        fullUrl = element.getAttribute('data-full-url');
    }
    
    navigator.clipboard.writeText(fullUrl).then(() => {
        // 显示复制成功
        if (element.tagName === 'BUTTON') {
            const originalText = element.textContent;
            element.textContent = '已复制';
            element.classList.add('copied');
            
            // 2秒后恢复
            setTimeout(() => {
                element.textContent = originalText;
                element.classList.remove('copied');
            }, 2000);
        } else {
            // 如果是点击地址文本，显示提示
            const tempBtn = document.createElement('span');
            tempBtn.className = 'copy-tip';
            tempBtn.textContent = '已复制';
            element.parentNode.appendChild(tempBtn);
            
            // 2秒后移除提示
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
/* 响应式样式 */
@media (max-width: 768px) {
    .transcode-meta {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .meta-item {
        margin-right: 0;
        margin-bottom: 5px;
    }
    
    .transcode-url {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .url-display {
        margin-bottom: 10px;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}

/* 转码记录样式 */
.transcode-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.transcode-item {
    background-color: #f9f9f9;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.transcode-info {
    margin-bottom: 10px;
}

.transcode-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #eee;
}

.meta-item {
    font-size: 14px;
    color: #666;
}

.meta-item.status-processing {
    color: #f39c12;
    font-weight: bold;
}

.meta-item.status-success {
    color: #27ae60;
    font-weight: bold;
}

.note {
    margin-top: 20px;
    padding: 10px;
    background-color: #f0f8ff;
    border-left: 4px solid #3498db;
    border-radius: 4px;
    font-size: 14px;
}

code {
    background-color: #f4f4f4;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: monospace;
}

/* 转码地址样式 */
.transcode-url {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #eee;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    position: relative;
}

.url-display {
    flex: 1;
    word-break: break-all;
    font-size: 14px;
    color: #3498db;
    cursor: pointer;
}

.url-display:hover {
    text-decoration: underline;
}

.copy-btn {
    padding: 4px 12px;
    background-color: #3498db;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s;
}

.copy-btn:hover {
    background-color: #2980b9;
}

.copy-btn.copied {
    background-color: #27ae60;
}

.copy-tip {
    position: absolute;
    right: 0;
    top: 5px;
    background-color: #27ae60;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
}

/* 卡片头部样式 */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.clear-btn {
    padding: 8px 16px;
    background-color: #e74c3c;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    transition: background-color 0.3s;
}

.clear-btn:hover {
    background-color: #c0392b;
}

/* JSON 代码块样式 */
.json-records {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.json-record {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 15px;
    overflow-x: auto;
    cursor: pointer;
    transition: all 0.3s ease;
}

.json-record:hover {
    border-color: #3498db;
    box-shadow: 0 2px 4px rgba(52, 152, 219, 0.1);
}

.json-record pre {
    margin: 0;
    font-family: 'Courier New', Courier, monospace;
    font-size: 14px;
    line-height: 1.5;
    color: #343a40;
}

.json-record pre code {
    background-color: transparent;
    padding: 0;
    border-radius: 0;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// 移除自动刷新功能，避免系统负荷增加
// 用户可手动刷新页面查看最新状态
</script>