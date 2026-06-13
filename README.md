# FFmpegPHP

FFmpegPHP 是一个基于 PHP + FFmpeg 构建的视频转码切片系统，专门用于将本地视频文件转换为 HLS (HTTP Live Streaming) 流媒体格式。

**更新日志**: [查看完整更新记录](https://github.com/OsGits/FFmpegPHP/tree/main/UpgradeLog)

<br />

## 核心功能

- **视频转码**：支持 MP4、AVI、MOV、WMV、FLV、MKV 等多种格式转码为 HLS
- **智能切片**：自动将视频切割为指定时长的 TS 片段
- **M3U8 生成**：自动生成标准的 HLS 播放列表文件
- **缩略图生成**：在指定时间点自动截取视频封面
- **GPU 加速**：支持 CUDA、DXVA2、D3D11VA、AMF 等多种硬件加速方案
- **多画质输出**：支持原始画质、1080p、720p 等多种分辨率
- **数据库集成**：可选集成 MySQL 存储视频元数据
- **批量处理**：支持批量转码多个视频文件

## 快速开始

### 环境要求

- PHP 7.0+
- FFmpeg 5.0+（需配置到系统 PATH 或在设置中指定路径）
- Windows/Linux 操作系统
- Web 服务器（Apache、Nginx、IIS）

### PHP 函数禁用配置

系统运行需要确保以下 PHP 函数未被禁用（在 `php.ini` 的 `disable_functions` 中移除这些函数）：

| 函数名                   | 用途              |
| --------------------- | --------------- |
| `exec()`              | 执行 FFmpeg 命令行工具 |
| `file_exists()`       | 检查文件或目录是否存在     |
| `file_get_contents()` | 读取文件内容          |
| `file_put_contents()` | 写入文件内容          |
| `mkdir()`             | 创建目录            |
| `unlink()`            | 删除文件            |
| `rename()`            | 重命名/移动文件        |
| `copy()`              | 复制文件            |
| `iconv()`             | 字符编码转换（支持中文文件名） |

> **注意**：最关键的是 `exec()` 函数，必须确保未被禁用，否则无法执行视频转码操作。

### 安装步骤

1. **部署项目**
   ```bash
   git clone https://github.com/OsGits/FFmpegPHP.git
   ```
2. **配置目录权限**
   - `vodoss/` - 待转码目录（需可写）
   - `m3u8/` - 输出目录（需可写）
   - `data/` - 配置目录（需可写）
3. **访问安装向导**
   ```
   http://your-domain/install.php
   ```
4. **上传视频**
   - 将视频文件放入 `vodoss/` 目录
   - 登录转码页面开始处理

## 使用流程

```
1. 上传视频到 vodoss/ 目录
       ↓
2. 配置转码参数（切片时长、画质、GPU加速）
       ↓
3. 选择视频文件开始转码
       ↓
4. 系统自动生成 TS 片段和 M3U8 文件
       ↓
5. 在 history.php 查看转码结果
```

## 配置说明

### 基础配置

| 配置项                | 说明           | 默认值         |
| ------------------ | ------------ | ----------- |
| `ffmpeg_path`      | FFmpeg 执行路径  | `ffmpeg`    |
| `input_dir`        | 待转码目录        | `./vodoss/` |
| `output_dir`       | 输出目录         | `./m3u8/`   |
| `base_url`         | TS 文件的基础 URL | 空           |
| `segment_duration` | 切片时长（秒）      | 10          |
| `screenshot_time`  | 截图时间点（秒）     | 10          |
| `quality`          | 输出画质         | `1080p`     |
| `use_gpu`          | 是否启用 GPU 加速  | 0           |

### GPU 加速支持

| 加速方式    | 适用平台          | 硬件要求             |
| ------- | ------------- | ---------------- |
| CUDA    | Windows/Linux | NVIDIA GPU       |
| AMF     | Windows       | AMD GPU          |
| DXVA2   | Windows       | Intel/NVIDIA/AMD |
| D3D11VA | Windows       | 现代显卡             |
| VAAPI   | Linux         | Intel/NVIDIA     |

## 输出结构

转码完成后，输出目录结构如下：

```
m3u8/
└── 2024/
    └── 01/
        └── 15/
            └── abc123xyz4/
                ├── abc123xyz4.m3u8   # 播放列表
                ├── abc123xyz4.jpg    # 封面截图
                ├── 000001.ts         # TS 片段
                ├── 000002.ts
                └── ...
```

## 安全特性

- 路径遍历防护
- 命令注入防护
- 文件名编码处理（支持中文文件名）
- 输入验证和参数过滤

## 许可证

MIT License

## 项目地址

<https://github.com/OsGits/FFmpegPHP>
