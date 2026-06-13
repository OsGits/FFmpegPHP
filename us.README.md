# FFmpegPHP

[中文版](README.md) | [English](us.README.md)

FFmpegPHP is a video transcoding and segmenting system built with PHP + FFmpeg, specifically designed to convert local video files into HLS (HTTP Live Streaming) format.

**Changelog**: [View Complete Update History](https://github.com/OsGits/FFmpegPHP/tree/main/UpgradeLog)

<br />

## Core Features

- **Video Transcoding**: Supports multiple formats including MP4, AVI, MOV, WMV, FLV, MKV to HLS conversion
- **Smart Segmentation**: Automatically splits videos into segments of specified duration
- **M3U8 Generation**: Automatically generates standard HLS playlist files
- **Thumbnail Generation**: Automatically captures video covers at specified time points
- **GPU Acceleration**: Supports multiple hardware acceleration solutions including CUDA, DXVA2, D3D11VA, AMF
- **Multiple Quality Output**: Supports original quality, 1080p, 720p and other resolutions
- **Database Integration**: Optional MySQL integration for storing video metadata
- **Batch Processing**: Supports batch transcoding of multiple video files
- **Automatic Task Management**: Supports automatic transcoding task management, no manual operation required

## Quick Start

### Requirements

- PHP 7.0+
- FFmpeg 5.0+ (configure in system PATH or specify path in settings)
- Windows/Linux operating system
- Web server (Apache, Nginx, IIS)
- MySQL database (optional)
- (Important) PHP `exec()` function must be configured to allow execution of FFmpeg command-line tools.
- (Important) Global directories must have write permissions for the "www" user.

### PHP Function Configuration

Ensure the following PHP functions are not disabled (remove them from `disable_functions` in `php.ini`):

| Function Name        | Purpose                        |
| -------------------- | ------------------------------ |
| `exec()`             | Execute FFmpeg command-line tools |
| `file_exists()`      | Check if file or directory exists |
| `file_get_contents()`| Read file contents             |
| `file_put_contents()`| Write file contents            |
| `mkdir()`            | Create directories             |
| `unlink()`           | Delete files                   |
| `rename()`           | Rename/move files              |
| `copy()`             | Copy files                     |
| `iconv()`            | Character encoding conversion (supports Chinese filenames) |

> **Note**: The most critical function is `exec()`, it must not be disabled, otherwise video transcoding operations cannot be performed.

### Installation Steps

1. **Deploy Project**
   ```bash
   git clone https://github.com/OsGits/FFmpegPHP.git
   ```
2. **Configure Directory Permissions**
   - It is recommended to set all directories (global directories and files) with `777` write permissions for the `www` user.

3. **Access Installation Wizard**
   ```
   http://your-domain/install.php
   ```
4. **Upload Videos**
   - Place video files into the `vodoss/` directory (can be modified in settings later)
   - Log in to the transcoding page to start processing

## Usage Flow

```
1. Upload videos to vodoss/ directory
       ↓
2. Configure transcoding parameters (segment duration, quality, GPU acceleration)
       ↓
3. Select video file to start transcoding
       ↓
4. System automatically generates TS segments and M3U8 files
       ↓
5. View transcoding results in history.php
```

## Configuration

### Basic Configuration

| Configuration Item   | Description                 | Default Value     |
| -------------------- | --------------------------- | ----------------- |
| `ffmpeg_path`        | FFmpeg executable path      | `ffmpeg`          |
| `input_dir`          | Input directory             | `./vodoss/`       |
| `output_dir`         | Output directory            | `./m3u8/`         |
| `base_url`           | Base URL for TS files       | empty             |
| `segment_duration`   | Segment duration (seconds)  | 10                |
| `screenshot_time`    | Screenshot time point (seconds) | 10             |
| `quality`            | Output quality              | `1080p`           |
| `use_gpu`            | Enable GPU acceleration     | 0                 |

### GPU Acceleration Support

| Acceleration Method | Supported Platform      | Hardware Requirement        |
| ------------------- | ---------------------- | --------------------------- |
| CUDA                | Windows/Linux          | NVIDIA GPU                  |
| AMF                 | Windows                | AMD GPU                     |
| DXVA2               | Windows                | Intel/NVIDIA/AMD            |
| D3D11VA             | Windows                | Modern graphics cards       |
| VAAPI               | Linux                  | Intel/NVIDIA                |

## Output Structure

After transcoding completes, the output directory structure is as follows:

```
m3u8/
└── 2024/
    └── 01/
        └── 15/
            └── abc123xyz4/
                ├── abc123xyz4.m3u8   # Playlist
                ├── abc123xyz4.jpg    # Cover thumbnail
                ├── 000001.ts         # TS segment
                ├── 000002.ts
                └── ...
```

## Security Features

- Path traversal protection
- Command injection protection
- Filename encoding handling (supports Chinese filenames)
- Input validation and parameter filtering

## License

MIT License

## Project URL

https://github.com/OsGits/FFmpegPHP
