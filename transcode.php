<?php
// 转码控制面板

// 加载配置和函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/hardware_detection.php';

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
        <div class="form-group">
            <p>如果有需要转码切片的视频，请上传到待转目录：</p>
            <p style="font-weight: bold; color: blue;"><?php echo UPLOAD_DIR; ?></p>
            <small>支持格式: <?php echo implode(', ', $allowed_extensions); ?></small>
            <small>为了考虑系统识别文件的正确性，建议视频文件名不要有特殊符号。</small>
        </div>
    </div>

    <!-- 视频处理 -->
    <div class="card">
        <h2>视频转码切割</h2>
        <!-- 已设置好的信息展示 -->
        <div class="form-group">
            <label>当前转码设置</label>
            <div style="background-color: #f5f5f5; padding: 15px; border-radius: 4px;">
                <div><strong>基础地址:</strong> <?php echo htmlspecialchars($base_url); ?></div>
                <div><strong>切片时长:</strong> <?php echo $default_segment_duration; ?> 秒</div>
                <div><strong>截图时间点:</strong> <?php echo $default_screenshot_time; ?> 秒</div>
                <div><strong>画质选择:</strong> <?php echo $default_quality; ?></div>
                <div><strong>使用GPU加速:</strong> <?php echo ($default_use_gpu == 1 && $gpu_info['available']) ? '是' : '否'; ?></div>
                <div><strong>转码后保存目录:</strong> <?php echo OUTPUT_DIR; ?></div>
            </div>
            <small>如需修改转码设置，请前往 <a href="settings.php">设置</a> 页面</small>
        </div>
        <!-- 转码状态提示 -->
        <?php if ($is_transcoding): ?>
            <div style="background-color: #fff3cd; color: #856404; padding: 10px; border-radius: 4px; margin-top: 10px; border: 1px solid #ffeaa7;">
                <strong>提示:</strong> 当前有转码任务正在执行（<?php echo htmlspecialchars($current_transcode['filename'] ?? '未知文件'); ?>），暂时无法启动新的转码任务。
            </div>
        <?php endif; ?>
    </div>

    <!-- 服务器文件列表 -->
    <?php if (!empty($server_files)): ?>
        <div class="card">
            <h2>服务器文件列表</h2>
            <ul class="file-list">
                <?php foreach ($paged_files as $file_info): ?>
                    <?php $file = $file_info['name']; ?>
                    <li style="padding: 10px; border-bottom: 1px solid #eee;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <form action="process.php" method="post" style="display: inline;">
                                <!-- 隐藏字段，传递设置参数 -->
                                <input type="hidden" name="input_file" value="<?php echo htmlspecialchars($file); ?>">
                                <input type="hidden" name="base_url" value="<?php echo htmlspecialchars($base_url); ?>">
                                <input type="hidden" name="segment_duration" value="<?php echo $default_segment_duration; ?>">
                                <input type="hidden" name="screenshot_time" value="<?php echo $default_screenshot_time; ?>">
                                <input type="hidden" name="quality" value="<?php echo $default_quality; ?>">
                                <input type="hidden" name="use_gpu" value="<?php echo $default_use_gpu; ?>">
                                <input type="hidden" name="output_dir" value="m3u8">
                                <?php if ($is_transcoding): ?>
                                    <button type="button" class="transcode-btn" disabled style="background-color: #9e9e9e; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: not-allowed; display: inline-block; white-space: nowrap;">稍后再来</button>
                                <?php else: ?>
                                    <button type="submit" class="transcode-btn" style="background-color: #4CAF50; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; display: inline-block; white-space: nowrap;">开始转码</button>
                                <?php endif; ?>
                            </form>
                            <span style="font-weight: bold; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($file); ?></span>
                        </div>
                        <div style="font-size: 14px; color: #666; display: flex; gap: 20px;">
                            <?php 
                                // 处理Windows系统的文件名编码问题
                                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                                    $file_gbk = iconv('UTF-8', 'GBK//IGNORE', $file);
                                    $file_path = UPLOAD_DIR . '/' . $file_gbk;
                                } else {
                                    $file_path = UPLOAD_DIR . '/' . $file;
                                }
                                $file_size = 0;
                                $file_time = '';
                                if (file_exists($file_path)) {
                                    $file_size = round(filesize($file_path) / 1024 / 1024, 2);
                                    $file_time = date('Y-m-d H:i:s', $file_info['time']);
                                }
                            ?>
                            <span>大小: <?php echo $file_size; ?> MB</span>
                            <span>修改时间: <?php echo $file_time; ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            
            <!-- 分页导航 -->
            <?php if ($total_pages > 1): ?>
                <div style="margin-top: 20px; display: flex; justify-content: center; gap: 10px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>" style="padding: 8px 16px; background-color: #f0f0f0; border-radius: 4px; text-decoration: none; color: #333;">上一页</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span style="padding: 8px 16px; background-color: #4CAF50; color: white; border-radius: 4px;"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>" style="padding: 8px 16px; background-color: #f0f0f0; border-radius: 4px; text-decoration: none; color: #333;"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" style="padding: 8px 16px; background-color: #f0f0f0; border-radius: 4px; text-decoration: none; color: #333;">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<script>
// 转码表单提交后跳转到记录页面
document.querySelectorAll('form[action="process.php"]').forEach(function(form) {
    form.addEventListener('submit', function() {
        // 显示转码开始提示
        alert('转码开始，请在记录页面查看转码进度');
        // 跳转到记录页面
        setTimeout(function() {
            window.location.href = 'history.php';
        }, 1000);
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>