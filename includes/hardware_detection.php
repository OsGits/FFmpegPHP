<?php
// 硬件检测函数

// 检测系统GPU信息
function detect_gpu() {
    global $gpu_acceleration;
    
    $gpu_info = [
        'available' => false,
        'methods' => [],
        'default' => 'none',
        'model' => '未知'
    ];
    
    // 检测FFmpeg是否支持各种GPU加速方式
    $test_methods = [
        'cuda' => 'NVIDIA CUDA',
        'dxva2' => 'DXVA2',
        'd3d11va' => 'D3D11VA',
        'amf' => 'AMD AMF'
    ];
    
    foreach ($test_methods as $method => $name) {
        // 构建测试命令
        $test_command = FFMPEG_PATH . ' -hwaccel ' . $method . ' -version 2>&1';
        
        $output = [];
        $return_var = 0;
        exec($test_command, $output, $return_var);
        
        // 检查命令是否成功执行
        // 注意：-hwaccel 命令在版本信息中可能会失败，但只要不是完全无法识别，就认为支持
        $success = true;
        foreach ($output as $line) {
            if (strpos($line, 'Unrecognized option hwaccel') !== false ||
                strpos($line, 'Error while parsing') !== false) {
                $success = false;
                break;
            }
        }
        
        if ($success) {
            $gpu_info['available'] = true;
            $gpu_info['methods'][$method] = $name;
            
            // 尝试检测GPU型号
            if ($gpu_info['model'] === '未知') {
                $gpu_info['model'] = detect_gpu_model($method);
            }
            
            // 设置默认GPU加速方式
            if ($gpu_info['default'] === 'none') {
                $gpu_info['default'] = $method;
            }
        }
    }
    
    return $gpu_info;
}

// 检测GPU型号
function detect_gpu_model($method) {
    // 不同的GPU加速方式使用不同的检测方法
    $test_commands = [];
    
    switch ($method) {
        case 'cuda':
            // 检测NVIDIA GPU型号，先尝试nvidia-smi，失败则使用通用方法
            $test_commands[] = 'nvidia-smi --query-gpu=name --format=csv,noheader 2>&1';
            $test_commands[] = 'wmic path win32_VideoController get name 2>&1';
            break;
        default:
            // 通用检测方法
            $test_commands[] = 'wmic path win32_VideoController get name 2>&1';
            break;
    }
    
    // 尝试所有检测命令
    foreach ($test_commands as $test_command) {
        $output = [];
        $return_var = 0;
        exec($test_command, $output, $return_var);
        
        // 处理输出，提取GPU型号
        $model = '未知';
        $valid = false;
        
        foreach ($output as $line) {
            $line = trim($line);
            // 跳过错误信息和空行
            if (empty($line) || $line === 'Name' || 
                strpos($line, 'Failed to initialize NVML') !== false ||
                strpos($line, 'nvidia-smi') !== false) {
                continue;
            }
            $model = $line;
            $valid = true;
            break;
        }
        
        if ($valid) {
            return $model;
        }
    }
    
    return '未知';
}

// 检测系统信息
function detect_system() {
    $system_info = [
        'os' => PHP_OS,
        'php_version' => PHP_VERSION,
        'ffmpeg_available' => false,
        'ffmpeg_version' => '',
        'gpu' => detect_gpu()
    ];
    
    // 检测FFmpeg是否可用
    $ffmpeg_command = FFMPEG_PATH . ' -version 2>&1';
    $output = [];
    $return_var = 0;
    exec($ffmpeg_command, $output, $return_var);
    
    if ($return_var === 0 && !empty($output)) {
        $system_info['ffmpeg_available'] = true;
        $system_info['ffmpeg_version'] = $output[0];
    }
    
    return $system_info;
}
?>