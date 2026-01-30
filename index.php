<?php
// 后台管理首页

// 加载配置和函数
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/hardware_detection.php';

// 检测系统硬件信息
$system_info = detect_system();
$gpu_info = $system_info['gpu'];
?>

<?php include __DIR__ . '/includes/header.php'; ?>

    <!-- 系统状态 -->
    <div class="card">
        <h2>系统状态</h2>
        <div>
            <strong>FFmpeg状态:</strong> 
            <?php if ($ffmpeg_available): ?>
                <span style="color: green;">已安装</span>
            <?php else: ?>
                <span style="color: red;">未安装</span>
                <p class="error">FFmpeg未安装，无法进行视频处理。请先安装FFmpeg并添加到系统PATH。</p>
            <?php endif; ?>
        </div>
        <div>
            <strong>GPU状态:</strong> 
            <?php if ($gpu_info['available']): ?>
                <span style="color: green;">已检测到</span>
                <p style="color: green;">GPU型号: <?php echo $gpu_info['model']; ?></p>
                <p style="color: green;">可用的GPU加速方式: <?php echo implode(', ', $gpu_info['methods']); ?></p>
            <?php else: ?>
                <span style="color: red;">未检测到</span>
                <p style="color: red;">未检测到可用的GPU加速，只能使用CPU转码</p>
            <?php endif; ?>
        </div>
        <div>
            <strong>待转码目录:</strong> <?php echo UPLOAD_DIR; ?> (<?php echo is_writable(UPLOAD_DIR) ? '可写' : '不可写'; ?>)
        </div>
        <div>
            <strong>转码后保存目录:</strong> <?php echo OUTPUT_DIR; ?> (<?php echo is_writable(OUTPUT_DIR) ? '可写' : '不可写'; ?>)
        </div>
        <div>
            <strong>基础地址:</strong> <?php echo htmlspecialchars($base_url); ?>
        </div>
        <div>
            <strong>切片时长:</strong> <?php echo $default_segment_duration; ?> 秒
        </div>
        <div>
            <strong>截图时间点:</strong> <?php echo $default_screenshot_time; ?> 秒
        </div>
        <div>
            <strong>画质选择:</strong> <?php echo $default_quality; ?>
        </div>
        <div>
            <strong>使用GPU加速:</strong> <?php echo ($default_use_gpu == 1 && $gpu_info['available']) ? '是' : '否'; ?>
        </div>
    </div>

    <!-- 版本信息 -->
    <div class="card">
        <h2>版本信息</h2>
        <div>
            <strong>版本号:</strong> V0.0.1
        </div>
    </div>

    <!-- 使用说明 -->
    <div class="card">
        <h2>使用说明</h2>
        <div>
            <h3>功能介绍</h3>
            <ul>
                <li><strong>首页:</strong> 显示系统状态、版本号和使用说明</li>
                <li><strong>转码:</strong> 提供视频转码控制面板，支持选择文件、设置参数并开始转码</li>
                <li><strong>记录:</strong> 显示当前转码进度和已完成的转码记录</li>
                <li><strong>设置:</strong> 配置转码选项，包括GPU/CPU选择和目录设置</li>
            </ul>
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

<?php include __DIR__ . '/includes/footer.php'; ?>
