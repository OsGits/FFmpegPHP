<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>视频转码切割工具</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        header {
            background-color: #333;
            color: #fff;
            padding: 15px 0;
            margin-bottom: 30px;
        }
        
        h1 {
            text-align: center;
            font-size: 24px;
        }
        
        .nav {
            text-align: center;
            margin-top: 10px;
        }
        
        .nav a {
            color: #fff;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .nav a:hover {
            text-decoration: underline;
        }
        
        .card {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 18px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        label {
            font-weight: bold;
            font-size: 14px;
        }
        
        input[type="text"],
        input[type="number"],
        select,
        input[type="file"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        input[type="submit"] {
            background-color: #4CAF50;
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        input[type="submit"]:hover {
            background-color: #45a049;
        }
        
        .error {
            background-color: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .success {
            background-color: #e8f5e8;
            color: #2e7d32;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .file-list {
            list-style: none;
            margin-top: 10px;
        }
        
        .file-list li {
            padding: 8px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .file-list li:last-child {
            border-bottom: none;
        }
        
        .file-size {
            font-size: 12px;
            color: #666;
        }
        
        .screenshot {
            max-width: 300px;
            margin-top: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .output-files {
            margin-top: 20px;
        }
        
        .output-files ul {
            list-style: none;
        }
        
        .output-files li {
            padding: 5px 0;
        }
        
        .output-files a {
            color: #1976d2;
            text-decoration: none;
        }
        
        .output-files a:hover {
            text-decoration: underline;
        }
        
        .progress {
            width: 100%;
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .progress-bar {
            height: 100%;
            background-color: #4CAF50;
            border-radius: 10px;
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>视频转码切割工具</h1>
            <div class="nav">
                <a href="index.php">首页</a>
                <a href="transcode.php">转码</a>
                <a href="history.php">记录</a>
                <a href="settings.php">设置</a>
            </div>
        </div>
    </header>
    <div class="container">