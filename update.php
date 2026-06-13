<?php
/**
 * 在线更新功能
 * 从 GitHub 下载最新版本并自动更新
 */

// 检查是否需要安装
$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'config.php';
if (!file_exists($config_file)) {
    header('Location: install.php');
    exit;
}

// 读取版本信息
$version_file = __DIR__ . DIRECTORY_SEPARATOR . 'version.json';
$version_info = [];
if (file_exists($version_file)) {
    $version_info = json_decode(file_get_contents($version_file), true) ?? [];
}

// 从 GitHub 获取最新版本信息
function get_github_release_info($api_url) {
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
        return json_decode($response, true);
    }
    return null;
}

// 获取下载链接
function get_download_url($release_data) {
    if (isset($release_data['zipball_url'])) {
        return $release_data['zipball_url'];
    }
    return null;
}

// 创建备份
function create_backup() {
    // 使用 data 目录存储备份
    $backup_dir = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'backup';
    if (!is_dir($backup_dir)) {
        if (!@mkdir($backup_dir, 0755, true)) {
            // 如果无法创建，返回 null 让用户手动创建
            return null;
        }
    }
    
    $backup_file = $backup_dir . DIRECTORY_SEPARATOR . 'backup_' . date('YmdHis') . '.zip';
    
    // 使用 PHP 的 ZipArchive 创建备份
    // 改用更可靠的备份方式：先创建到临时文件，再移动
    $temp_zip = tempnam(sys_get_temp_dir(), 'backup_');
    if (!$temp_zip) {
        return null;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            $file_path = $file->getPathname();
            // 跳过不需要备份的目录和文件
            if (is_dir($file_path)) continue;
            if (strpos($file_path, 'backup') !== false) continue;
            if (strpos($file_path, 'vodoss') !== false) continue;
            if (strpos($file_path, 'm3u8') !== false) continue;
            if (strpos($file_path, 'data' . DIRECTORY_SEPARATOR . 'config.php') !== false) continue;
            if (strpos($file_path, 'ting.json') !== false) continue;
            
            $relative_path = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $file_path);
            $zip->addFile($file_path, $relative_path);
        }
        
        $zip->close();
        
        // 移动到备份目录
        if (@rename($temp_zip, $backup_file)) {
            return $backup_file;
        } else {
            // 如果移动失败，尝试复制
            if (@copy($temp_zip, $backup_file)) {
                @unlink($temp_zip);
                return $backup_file;
            }
            @unlink($temp_zip);
            return null;
        }
    }
    
    @unlink($temp_zip);
    return null;
}

// 检查更新所需权限
function check_update_permissions(&$errors) {
    $errors = [];
    
    // 检查 data 目录是否可写
    $data_dir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
    if (!is_writable($data_dir)) {
        $errors[] = 'data 目录不可写，无法创建备份';
    }
    
    // 检查主要 PHP 文件是否可写（用于更新）
    $main_files = ['index.php', 'version.json', 'settings.php'];
    foreach ($main_files as $file) {
        $file_path = __DIR__ . DIRECTORY_SEPARATOR . $file;
        if (file_exists($file_path) && !is_writable($file_path)) {
            $errors[] = "$file 文件不可写";
        }
    }
    
    return empty($errors);
}

// 执行更新
function perform_update($zipball_url, &$message, &$success) {
    global $errors;
    
    // 先检查权限
    if (!check_update_permissions($errors)) {
        $message = '权限检查失败: ' . implode(', ', $errors);
        $success = false;
        return false;
    }
    
    // 创建备份
    $message = '正在创建备份...';
    $backup_file = create_backup();
    if (!$backup_file) {
        $message = '备份目录创建失败，请手动创建 data/backup 目录并设置可写权限';
        $success = false;
        return false;
    }
    $message = '备份已创建: ' . basename($backup_file);
    
    // 下载更新包
    $message = '正在下载更新包...';
    $temp_dir = sys_get_temp_dir();
    $temp_file = $temp_dir . DIRECTORY_SEPARATOR . 'update_' . uniqid() . '.zip';
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: FFmpegPHP'
            ]
        ]
    ]);
    
    $content = @file_get_contents($zipball_url, false, $context);
    if (!$content) {
        $message = '下载更新包失败，请检查网络连接';
        $success = false;
        return false;
    }
    
    if (@file_put_contents($temp_file, $content) === false) {
        $message = '无法在临时目录保存更新包，请检查系统临时目录权限';
        $success = false;
        return false;
    }
    
    // 解压更新包
    $message = '正在解压更新包...';
    $zip = new ZipArchive();
    if ($zip->open($temp_file) !== true) {
        $message = '无法打开更新包';
        $success = false;
        @unlink($temp_file);
        return false;
    }
    
    // 使用 data 目录来解压
    $extract_base = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'update_temp';
    
    // 清理旧解压目录
    if (is_dir($extract_base)) {
        $dir = @opendir($extract_base);
        if ($dir) {
            while (($file = @readdir($dir)) !== false) {
                if ($file != '.' && $file != '..') {
                    $path = $extract_base . DIRECTORY_SEPARATOR . $file;
                    if (is_dir($path)) {
                        @rmdir($path);
                    } else {
                        @unlink($path);
                    }
                }
            }
            @closedir($dir);
        }
        @rmdir($extract_base);
    }
    
    if (!@mkdir($extract_base, 0755, true) && !is_dir($extract_base)) {
        $message = '无法创建临时解压目录';
        $success = false;
        $zip->close();
        @unlink($temp_file);
        return false;
    }
    
    $zip->extractTo($extract_base);
    $zip->close();
    
    // 获取项目根目录名称
    $dir = @opendir($extract_base);
    $project_dir = null;
    if ($dir) {
        while (($file = @readdir($dir)) !== false) {
            if ($file != '.' && $file != '..' && is_dir($extract_base . DIRECTORY_SEPARATOR . $file)) {
                $project_dir = $file;
                break;
            }
        }
        @closedir($dir);
    }
    
    if (!$project_dir) {
        $message = '无法识别更新包结构';
        $success = false;
        @unlink($temp_file);
        return false;
    }
    
    // 复制文件（排除 config.php 和用户数据）
    $message = '正在安装更新...';
    $source_dir = $extract_base . DIRECTORY_SEPARATOR . $project_dir;
    $failed_files = [];
    $success_count = 0;
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($files as $file) {
        $relative_path = str_replace($source_dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
        
        // 跳过某些文件
        if (strpos($relative_path, 'backup') !== false) continue;
        if (strpos($relative_path, 'vodoss') !== false) continue;
        if (strpos($relative_path, 'm3u8') !== false) continue;
        if (strpos($relative_path, 'config.php') !== false) continue;
        if (strpos($relative_path, 'ting.json') !== false) continue;
        if (strpos($relative_path, '.git') !== false) continue;
        if (strpos($relative_path, 'data' . DIRECTORY_SEPARATOR . 'config.php') !== false) continue;
        
        $target_path = __DIR__ . DIRECTORY_SEPARATOR . $relative_path;
        
        if (is_dir($file->getPathname())) {
            if (!is_dir($target_path)) {
                if (!@mkdir($target_path, 0755, true)) {
                    $failed_files[] = $relative_path;
                }
            }
        } else {
            $target_dir = dirname($target_path);
            if (!is_dir($target_dir)) {
                @mkdir($target_dir, 0755, true);
            }
            if (@copy($file->getPathname(), $target_path)) {
                $success_count++;
            } else {
                // 如果复制失败，尝试继续更新其他文件
                $failed_files[] = $relative_path;
            }
        }
    }
    
    // 清理临时文件
    @unlink($temp_file);
    if (is_dir($extract_base)) {
        $dir = @opendir($extract_base);
        if ($dir) {
            while (($file = @readdir($dir)) !== false) {
                if ($file != '.' && $file != '..') {
                    $path = $extract_base . DIRECTORY_SEPARATOR . $file;
                    if (is_dir($path)) {
                        @rmdir($path);
                    } else {
                        @unlink($path);
                    }
                }
            }
            @closedir($dir);
        }
        @rmdir($extract_base);
    }
    
    // 清理临时目录
    function deleteDir($dir) {
        if (!is_dir($dir)) return;
        $dir = rtrim($dir, DIRECTORY_SEPARATOR);
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? deleteDir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
    deleteDir($extract_base);
    
    if (empty($failed_files)) {
        $message = "更新完成！已成功更新 $success_count 个文件";
        $success = true;
    } else {
        $message = "更新完成，但有 " . count($failed_files) . " 个文件更新失败: " . implode(', ', array_slice($failed_files, 0, 5));
        $success = true; // 部分成功也算成功
    }
    return true;
}

// 处理更新请求
$result = null;
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $api_url = $version_info['github_api'] ?? 'https://api.github.com/repos/OsGits/FFmpegPHP/releases/latest';
    $release_info = get_github_release_info($api_url);
    
    if ($release_info) {
        $download_url = get_download_url($release_info);
        if ($download_url) {
            perform_update($download_url, $message, $success);
            $result = [
                'success' => $success,
                'message' => $message
            ];
        } else {
            $result = [
                'success' => false,
                'message' => '无法获取下载链接'
            ];
        }
    } else {
        $result = [
            'success' => false,
            'message' => '无法连接到 GitHub，请检查网络'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>在线更新 - FFmpegPHP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .version-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .version-info p {
            margin: 5px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #45a049;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .btn-back {
            background: #666;
            margin-right: 10px;
        }
        .btn-back:hover {
            background: #555;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .loading {
            text-align: center;
            padding: 20px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #4CAF50;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>在线更新</h1>
        
        <?php if ($result): ?>
            <?php if ($result['success']): ?>
                <div class="success">
                    <strong>更新成功！</strong><br>
                    <?php echo htmlspecialchars($result['message']); ?>
                </div>
                <p>系统已更新到最新版本，请刷新页面查看。</p>
                <a href="index.php" class="btn">返回首页</a>
            <?php else: ?>
                <div class="error">
                    <strong>更新失败</strong><br>
                    <?php echo htmlspecialchars($result['message']); ?>
                </div>
                <a href="index.php" class="btn btn-back">返回首页</a>
                <button class="btn" onclick="location.reload()">重试</button>
            <?php endif; ?>
        <?php else: ?>
            <div class="version-info">
                <p><strong>当前版本:</strong> <?php echo htmlspecialchars($version_info['version'] ?? 'Unknown'); ?></p>
                <?php if (!empty($version_info['github_api'])): ?>
                    <?php 
                    $release = get_github_release_info($version_info['github_api']);
                    if ($release): 
                    ?>
                        <p><strong>最新版本:</strong> <?php echo htmlspecialchars($release['tag_name'] ?? '-'); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php 
            // 检查权限
            $perm_errors = [];
            $can_update = check_update_permissions($perm_errors);
            if (!$can_update): 
            ?>
                <div class="error">
                    <strong>权限不足，无法进行更新</strong><br>
                    <?php echo implode('<br>', array_map('htmlspecialchars', $perm_errors)); ?>
                    <br><br>
                    <strong>解决方案：</strong>
                    <p style="margin: 10px 0;">在线更新需要给网站根目录设置 <strong>777</strong> 权限，请通过 SSH 执行以下命令：</p>
                    <pre style="background: #f8f9fa; padding: 15px; border-radius: 4px; overflow-x: auto;">chmod -R 777 /www/wwwroot/你的网站目录</pre>
                    <p style="margin-top: 10px; color: #666;">或者通过宝塔面板 / FTP 工具手动设置权限。</p>
                </div>
            <?php endif; ?>
            
            <?php if ($can_update): ?>
            <div class="warning">
                <strong>更新须知：</strong>
                <ul style="margin: 10px 0 0 20px;">
                    <li>更新前需要想对全文件夹和文件设置 <strong>WWW用户777权限</strong> 来确保更新过程中的写入操作。</li>
                    <li>更新前系统会自动创建备份</li>
                    <li>配置文件和用户数据不会被影响</li>
                </ul>
            </div>
            
            <form method="post" id="updateForm">
                <input type="hidden" name="action" value="update">
                <a href="index.php" class="btn btn-back">返回</a>
                <button type="submit" class="btn" id="updateBtn">确认更新</button>
            </form>
            
            <div id="loading" class="loading" style="display: none;">
                <div class="spinner"></div>
                <p id="statusText">正在准备更新...</p>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        document.getElementById('updateForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (!confirm('确定要进行在线更新吗？\n更新前会创建备份。')) {
                return;
            }
            
            document.getElementById('updateForm').style.display = 'none';
            document.getElementById('loading').style.display = 'block';
            
            const statusMessages = [
                '正在创建备份...',
                '备份创建完成，正在下载更新包...',
                '下载完成，正在解压...',
                '正在安装更新文件...',
                '安装完成，正在清理临时文件...'
            ];
            
            let index = 0;
            const statusInterval = setInterval(() => {
                if (index < statusMessages.length) {
                    document.getElementById('statusText').textContent = statusMessages[index];
                    index++;
                }
            }, 2000);
            
            this.submit();
        });
    </script>
</body>
</html>
