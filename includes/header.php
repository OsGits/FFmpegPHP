<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>视频转码切割工具</title>
    <style>
        /* CSS Reset & Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            /* 主色调 - 现代蓝 */
            --primary-color: #3B82F6;
            --primary-dark: #2563EB;
            --primary-light: #60A5FA;

            /* 辅助色 */
            --success-color: #10B981;
            --success-dark: #059669;
            --warning-color: #F59E0B;
            --warning-dark: #D97706;
            --danger-color: #EF4444;
            --danger-dark: #DC2626;

            /* 中性色 - 避免纯黑 */
            --text-primary: #1F2937;
            --text-secondary: #6B7280;
            --text-muted: #9CA3AF;

            /* 背景色 */
            --bg-page: #F3F4F6;
            --bg-card: #FFFFFF;
            --bg-hover: #F9FAFB;
            --bg-active: #EFF6FF;

            /* 边框 */
            --border-color: #E5E7EB;
            --border-light: #F3F4F6;

            /* 阴影 - 扁平化轻阴影 */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);

            /* 圆角 */
            --radius-sm: 6px;
            --radius-md: 8px;
            --radius-lg: 12px;

            /* 过渡 */
            --transition-fast: 0.15s ease;
            --transition-normal: 0.25s ease;
        }

        html {
            font-size: 16px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* 容器 */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* 头部导航 */
        header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: #fff;
            padding: 0;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 12px;
            letter-spacing: -0.025em;
        }

        .nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .nav a {
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 500;
            transition: all var(--transition-fast);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav a:hover {
            background-color: rgba(255, 255, 255, 0.15);
            color: #fff;
        }

        .nav a.active {
            background-color: rgba(255, 255, 255, 0.25);
            color: #fff;
        }

        .nav .logout-btn {
            margin-left: 16px;
            background-color: rgba(239, 68, 68, 0.8);
            border: 1px solid rgba(239, 68, 68, 1);
        }

        .nav .logout-btn:hover {
            background-color: rgba(220, 38, 38, 1);
        }

        /* 卡片组件 */
        .card {
            background-color: var(--bg-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: box-shadow var(--transition-normal);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card h2 {
            color: var(--text-primary);
            margin-bottom: 16px;
            font-size: 1.125rem;
            font-weight: 600;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--primary-color);
            display: inline-block;
        }

        .card > div:not(.card) {
            margin-bottom: 12px;
        }

        .card > div:last-child {
            margin-bottom: 0;
        }

        /* 标题样式 */
        h3 {
            color: var(--text-primary);
            font-size: 1rem;
            font-weight: 600;
            margin: 16px 0 8px 0;
        }

        /* 表单样式 */
        form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        label {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-primary);
        }

        input[type="text"],
        input[type="number"],
        input[type="password"],
        select {
            padding: 10px 14px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: all var(--transition-fast);
            background-color: var(--bg-card);
            color: var(--text-primary);
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        input[type="password"]:focus,
        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        input[type="file"] {
            padding: 10px;
            border: 2px dashed var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            background-color: var(--bg-hover);
        }

        input[type="submit"],
        button[type="submit"] {
            background-color: var(--primary-color);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        input[type="submit"]:hover,
        button[type="submit"]:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* 按钮样式 */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all var(--transition-fast);
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: #fff;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-success {
            background-color: var(--success-color);
            color: #fff;
        }

        .btn-success:hover {
            background-color: var(--success-dark);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: #fff;
        }

        .btn-danger:hover {
            background-color: var(--danger-dark);
        }

        .btn-secondary {
            background-color: var(--text-secondary);
            color: #fff;
        }

        .btn-secondary:hover {
            background-color: var(--text-primary);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* 提示信息 */
        .error {
            background-color: #FEF2F2;
            color: var(--danger-dark);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            border-left: 4px solid var(--danger-color);
            font-size: 0.9rem;
        }

        .success {
            background-color: #ECFDF5;
            color: var(--success-dark);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            border-left: 4px solid var(--success-color);
            font-size: 0.9rem;
        }

        /* 警告提示 */
        .warning {
            background-color: #FFFBEB;
            color: var(--warning-dark);
            padding: 12px 16px;
            border-radius: var(--radius-md);
            margin-bottom: 16px;
            border-left: 4px solid var(--warning-color);
            font-size: 0.9rem;
        }

        /* 文件列表 */
        .file-list {
            list-style: none;
            margin-top: 12px;
        }

        .file-list li {
            padding: 14px;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color var(--transition-fast);
        }

        .file-list li:hover {
            background-color: var(--bg-hover);
        }

        .file-list li:last-child {
            border-bottom: none;
        }

        .file-size {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* 进度条 */
        .progress {
            width: 100%;
            height: 20px;
            background-color: var(--bg-page);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            border-radius: 10px;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* 双列布局 */
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* 状态指示器 */
        .status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }

        .status-success {
            background-color: var(--success-color);
        }

        .status-error {
            background-color: var(--danger-color);
        }

        .status-warning {
            background-color: var(--warning-color);
        }

        /* 链接样式 */
        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color var(--transition-fast);
        }

        a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* 小字体 */
        small {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* 代码块 */
        code {
            background-color: var(--bg-page);
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'SF Mono', 'Consolas', monospace;
            font-size: 0.85em;
            color: var(--text-secondary);
        }

        pre {
            background-color: var(--bg-page);
            padding: 12px;
            border-radius: var(--radius-md);
            overflow-x: auto;
            font-family: 'SF Mono', 'Consolas', monospace;
            font-size: 0.85rem;
        }

        /* 列表样式 */
        ul, ol {
            margin: 8px 0;
            padding-left: 24px;
        }

        li {
            margin-bottom: 6px;
            color: var(--text-secondary);
        }

        /* 复选框样式 */
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
            cursor: pointer;
        }

        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 8px 14px;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            text-decoration: none;
            transition: all var(--transition-fast);
            display: inline-flex;
            align-items: center;
        }

        .pagination a {
            background-color: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .pagination a:hover {
            background-color: var(--bg-hover);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .pagination .current {
            background-color: var(--primary-color);
            color: #fff;
            border: 1px solid var(--primary-color);
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            html {
                font-size: 14px;
            }

            .container {
                padding: 0 16px;
            }

            .header-content {
                padding: 16px 0;
            }

            .header-title {
                font-size: 1.25rem;
            }

            .nav {
                gap: 4px;
            }

            .nav a {
                padding: 6px 12px;
                font-size: 0.85rem;
            }

            .card {
                padding: 16px;
                border-radius: var(--radius-md);
            }

            .card h2 {
                font-size: 1rem;
            }

            .two-column {
                grid-template-columns: 1fr;
            }

            .file-list li {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            input[type="submit"],
            button[type="submit"] {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .header-title {
                font-size: 1.1rem;
            }

            .nav a {
                padding: 6px 10px;
                font-size: 0.8rem;
            }

            .card {
                padding: 12px;
            }

            .btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">                
                <nav class="nav">
                    <a href="index.php">首页</a>
                    <a href="transcode.php">转码</a>
                    <a href="history.php">记录</a>
                    <a href="settings.php">设置</a>
                    <a href="index.php?action=logout" class="logout-btn">退出</a>
                </nav>
            </div>
        </div>
    </header>
    <div class="container">
