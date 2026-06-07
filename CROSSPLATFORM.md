# 跨平台兼容性优化说明

本文档说明了对FFmpegPHP项目进行的跨平台兼容性优化，使其能够在Windows和Linux/Unix系统上都能正常运行。

## 主要优化内容

### 1. 路径分隔符处理
- 使用 `DIRECTORY_SEPARATOR` 常量代替硬编码的 `/` 或 `\`
- 定义了 `DS` 常量作为 `DIRECTORY_SEPARATOR` 的简写
- 添加了 `safe_path()` 函数来统一处理路径分隔符

### 2. 操作系统检测
- 添加了 `IS_WINDOWS` 常量，用于检测当前是否在Windows系统上运行
- 在不同操作系统上使用不同的命令执行方式

### 3. 命令执行优化
- Windows系统使用 `cmd /c` 包装命令
- 所有外部命令都经过适当的转义处理
- 添加了 `safe_exec()` 函数用于安全执行命令

### 4. FFprobe支持
- 添加了独立的FFprobe路径配置
- 添加了 `get_ffprobe_path()` 函数来获取正确的ffprobe路径
- 支持从ffmpeg路径自动推导ffprobe路径

### 5. GPU检测优化
- 根据操作系统检测合适的GPU加速方式
- Windows系统支持：CUDA, DirectX, AMF
- Linux系统支持：CUDA, VAAPI, VDPAU
- 添加了跨平台的GPU型号检测

### 6. 文件名编码处理
- 优化了Windows系统上的文件名编码处理
- 添加了 `safe_filename()` 和 `safe_readdir()` 函数
- 改善了中文文件名的支持

### 7. 配置文件更新
- 配置文件格式保持向后兼容
- 新增了 `ffprobe_path` 配置项
- 默认配置已更新

## 文件修改列表

### 核心文件
- `config.php` - 添加跨平台常量和函数
- `includes/functions.php` - 添加路径处理和命令执行函数
- `includes/hardware_detection.php` - 优化GPU检测

### 页面文件
- `index.php` - 更新路径引用
- `transcode.php` - 更新路径引用和文件处理
- `process.php` - 重构为跨平台版本
- `z.php` - 重构为跨平台版本
- `history.php` - 更新路径引用
- `settings.php` - 添加FFprobe配置

### 数据库
- `mysql/database.php` - 更新路径引用

### 新增文件
- `check_compatibility.php` - 兼容性检查脚本
- `CROSSPLATFORM.md` - 本文档

## 配置说明

### config.json 新结构
```json
{
  "ffmpeg_path": "ffmpeg",
  "ffprobe_path": "ffprobe",
  "input_dir": "./vodoss/",
  "output_dir": "./m3u8/",
  "base_url": "",
  "segment_duration": 10,
  "screenshot_time": 10,
  "quality": "1080p",
  "use_gpu": 0,
  "mysql_enabled": 0,
  "mysql_host": "localhost",
  "mysql_port": "3306",
  "mysql_db": "vod_system",
  "mysql_user": "root",
  "mysql_password": "",
  "m3u8_full_url": ""
}
```

## 兼容性检查

运行 `check_compatibility.php` 脚本来检查当前环境的兼容性：

```bash
php check_compatibility.php
```

该脚本会检查：
- PHP版本
- 必需的扩展和函数
- 目录权限
- FFmpeg和FFprobe可用性
- 配置文件状态

## 平台特定说明

### Windows系统
- 确保FFmpeg和FFprobe的路径使用正斜杠或双反斜杠
- 中文文件名需要确保系统编码正确
- 推荐使用PHP 7.0或更高版本

### Linux/Unix系统
- 确保FFmpeg和FFprobe已正确安装并添加到PATH
- 确保web服务器对相关目录有写权限
- 注意区分大小写

## 向后兼容性

所有修改保持向后兼容：
- 原有的配置文件格式仍然有效
- 旧版本的代码逻辑仍然支持
- 新增配置项有合理的默认值

## 常见问题

### Q: Windows上文件名乱码怎么办？
A: 确保系统区域设置正确，并使用UTF-8编码的PHP文件。

### Q: Linux上GPU加速不工作？
A: 检查是否安装了正确的GPU驱动和FFmpeg编译选项。

### Q: 如何手动指定FFprobe路径？
A: 在设置页面中配置FFprobe路径，或直接编辑config.json文件。

## 更新日志

### V1.1.0 (跨平台优化版本)
- 添加完整的跨平台支持
- 优化路径处理
- 添加FFprobe支持
- 改进GPU检测
- 添加兼容性检查脚本
