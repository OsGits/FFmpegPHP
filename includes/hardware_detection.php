<?php
// 硬件检测函数 - 跨平台兼容版本

// 加载config.php以获取基础常量
if (!defined('ROOT_DIR')) {
    require_once dirname(__DIR__) . '/config.php';
}

// 辅助函数：检查函数是否被禁用
function hd_is_function_disabled($func_name) {
    static $disabled = null;
    if ($disabled === null) {
        $disabled = explode(',', ini_get('disable_functions'));
        $disabled = array_map('trim', $disabled);
    }
    return in_array($func_name, $disabled);
}

// 检测系统GPU信息
function detect_gpu() {
    $gpu_info = [
        'available' => false,
        'methods' => [],
        'default' => 'none',
        'model' => '未知'
    ];
    
    // 如果exec被禁用，直接返回
    if (hd_is_function_disabled('exec')) {
        return $gpu_info;
    }
    
    // 根据平台检测可用的GPU加速方法
    $test_methods = [];
    
    if (defined('IS_WINDOWS') && IS_WINDOWS) {
        // Windows平台
        $test_methods = [
            'cuda' => 'NVIDIA CUDA',
            'd3d11va' => 'D3D11VA',
            'dxva2' => 'DXVA2',
            'amf' => 'AMD AMF'
        ];
    } else {
        // Linux/Mac平台
        $test_methods = [
            'cuda' => 'NVIDIA CUDA',
            'vaapi' => 'VAAPI (Linux)',
            'vdpau' => 'VDPAU (Linux/NVIDIA)'
        ];
    }
    
    foreach ($test_methods as $method => $name) {
        // 构建测试命令
        $ffmpeg_path = defined('FFMPEG_PATH') ? FFMPEG_PATH : 'ffmpeg';
        $test_cmd = $ffmpeg_path . ' -hide_banner -hwaccel ' . $method . ' -f lavfi -i color -t 0.1 -f null - 2>&1';
        
        $output = [];
        $return_var = 0;
        
        // 直接使用exec，但添加错误抑制
        @exec($test_cmd, $output, $return_var);
        
        // 如果没有明确报错，就认为该方法可用
        $is_supported = true;
        
        // 检查输出中是否有明确的错误信息
        foreach ($output as $line) {
            if (strpos($line, 'Invalid') !== false || 
                strpos($line, 'not found') !== false ||
                strpos($line, 'unsupported') !== false ||
                strpos($line, 'Unknown') !== false) {
                $is_supported = false;
                break;
            }
        }
        
        if ($is_supported) {
            $gpu_info['available'] = true;
            $gpu_info['methods'][$method] = $name;
            
            // 尝试检测GPU型号
            if ($gpu_info['model'] === '未知') {
                $gpu_info['model'] = detect_gpu_model($method);
            }
            
            // 设置默认GPU加速方法
            if ($gpu_info['default'] === 'none') {
                $gpu_info['default'] = $method;
            }
        }
    }
    
    return $gpu_info;
}

// 检测GPU型号
function detect_gpu_model($method) {
    // 如果exec被禁用，直接返回
    if (hd_is_function_disabled('exec')) {
        return '未知';
    }
    
    $model = '未知';
    
    if (defined('IS_WINDOWS') && IS_WINDOWS) {
        // Windows平台 - 使用WMI
        $model = detect_gpu_windows();
    } else {
        // Linux/Mac平台
        $model = detect_gpu_linux();
    }
    
    return $model;
}

// Windows平台检测GPU
function detect_gpu_windows() {
    $model = '未知';
    
    // 尝试使用WMI
    $output = [];
    $return_var = 0;
    
    @exec('wmic path win32_VideoController get name 2>&1', $output, $return_var);
    
    if ($return_var === 0 && !empty($output)) {
        foreach ($output as $line) {
            $line = trim($line);
            if (!empty($line) && $line !== 'Name') {
                $model = $line;
                break;
            }
        }
    }
    
    // 如果WMI失败，尝试其他方法
    if ($model === '未知') {
        // 尝试nvidia-smi
        @exec('nvidia-smi --query-gpu=name --format=csv,noheader 2>&1', $output, $return_var);
        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, 'Failed') === false) {
                    $model = $line;
                    break;
                }
            }
        }
    }
    
    return $model;
}

// Linux平台检测GPU
function detect_gpu_linux() {
    $model = '未知';
    
    // 尝试读取lspci
    $output = [];
    $return_var = 0;
    
    @exec('lspci 2>&1', $output, $return_var);
    
    if ($return_var === 0 && !empty($output)) {
        foreach ($output as $line) {
            if (stripos($line, 'VGA') !== false || stripos($line, '3D') !== false) {
                $model = trim(preg_replace('/^[0-9a-f:.]+\s+/i', '', $line));
                break;
            }
        }
    }
    
    // 如果lspci失败，尝试nvidia-smi
    if ($model === '未知') {
        @exec('nvidia-smi --query-gpu=name --format=csv,noheader 2>&1', $output, $return_var);
        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                $line = trim($line);
                if (!empty($line) && strpos($line, 'Failed') === false) {
                    $model = $line;
                    break;
                }
            }
        }
    }
    
    return $model;
}

// 检测系统信息
function detect_system() {
    $system_info = [
        'os' => PHP_OS,
        'os_family' => (defined('IS_WINDOWS') && IS_WINDOWS) ? 'Windows' : 'Unix',
        'php_version' => PHP_VERSION,
        'ffmpeg_available' => false,
        'ffmpeg_version' => '',
        'gpu' => detect_gpu()
    ];
    
    // 如果exec被禁用，直接返回
    if (hd_is_function_disabled('exec')) {
        return $system_info;
    }
    
    // 检测FFmpeg是否可用
    $ffmpeg_path = defined('FFMPEG_PATH') ? FFMPEG_PATH : 'ffmpeg';
    $test_cmd = $ffmpeg_path . ' -version 2>&1';
    
    $output = [];
    $return_var = 0;
    
    @exec($test_cmd, $output, $return_var);
    
    if ($return_var === 0 && !empty($output)) {
        $system_info['ffmpeg_available'] = true;
        $system_info['ffmpeg_version'] = $output[0] ?? '';
    }
    
    return $system_info;
}
