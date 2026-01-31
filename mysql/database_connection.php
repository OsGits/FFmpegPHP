<?php
// 测试数据库连接和创建表结构

// 获取POST数据
$mysql_enabled = $_POST['mysql_enabled'] ?? 0;
$mysql_host = $_POST['mysql_host'] ?? 'localhost';
$mysql_port = $_POST['mysql_port'] ?? '3306';
$mysql_db = $_POST['mysql_db'] ?? 'vod_system';
$mysql_user = $_POST['mysql_user'] ?? 'root';
$mysql_password = $_POST['mysql_password'] ?? '';

// 初始化结果
$result = [
    'success' => false,
    'message' => '',
    'details' => []
];

// 验证必要参数
if (!$mysql_enabled) {
    $result['message'] = '请先启用数据库功能';
    echo json_encode($result);
    exit;
}

if (!$mysql_host || !$mysql_port || !$mysql_db || !$mysql_user) {
    $result['message'] = '请填写完整的数据库连接信息';
    echo json_encode($result);
    exit;
}

// 尝试连接数据库
try {
    $pdo = new PDO(
        "mysql:host=$mysql_host;port=$mysql_port;dbname=$mysql_db;charset=utf8mb4",
        $mysql_user,
        $mysql_password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    $result['success'] = true;
    $result['message'] = '数据库连接成功';
    
    // 检查并创建表结构
    $result['details'][] = '开始检查表结构...';
    
    // 检查vodm3u8表是否存在
    $stmt = $pdo->query("SHOW TABLES LIKE 'vodm3u8'");
    if ($stmt->rowCount() === 0) {
        // 创建vodm3u8表
        $createTableSQL = "
        CREATE TABLE vodm3u8 (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vodmc VARCHAR(255) NOT NULL,
            vodimg VARCHAR(255) NOT NULL,
            vodurl VARCHAR(255) NOT NULL,
            vodsj VARCHAR(50) NOT NULL,
            voddx VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $pdo->exec($createTableSQL);
        $result['details'][] = '成功创建vodm3u8表';
    } else {
        // 检查表结构是否完整
        $result['details'][] = 'vodm3u8表已存在，检查字段结构...';
        
        // 获取表结构
        $stmt = $pdo->query("DESCRIBE vodm3u8");
        $fields = $stmt->fetchAll();
        $existingFields = array_column($fields, 'Field');
        
        // 检查必要字段
        $requiredFields = ['id', 'vodmc', 'vodimg', 'vodurl', 'vodsj', 'voddx'];
        $missingFields = array_diff($requiredFields, $existingFields);
        
        if (!empty($missingFields)) {
            // 处理缺失字段（实际项目中可能需要更复杂的逻辑）
            $result['details'][] = '发现缺失字段：' . implode(', ', $missingFields);
            $result['details'][] = '表结构检查完成，部分字段缺失';
        } else {
            $result['details'][] = '表结构完整，所有必要字段都存在';
        }
    }
    
} catch (PDOException $e) {
    $result['success'] = false;
    $result['message'] = '数据库连接失败：' . $e->getMessage();
}

echo json_encode($result);
?>