<?php
// 数据库操作类
class Database {
    private $pdo;
    private $config;
    
    // 构造函数
    public function __construct($config = null) {
        if ($config === null) {
            // 读取配置文件
            $configFile = dirname(__DIR__) . '/config.json';
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
            } else {
                throw new Exception('配置文件不存在');
            }
        }
        
        $this->config = $config;
        
        // 检查是否启用数据库
        if (!isset($config['mysql_enabled']) || $config['mysql_enabled'] != 1) {
            throw new Exception('数据库功能未启用');
        }
        
        // 连接数据库
        $this->connect();
    }
    
    // 连接数据库
    private function connect() {
        $host = $this->config['mysql_host'] ?? 'localhost';
        $port = $this->config['mysql_port'] ?? '3306';
        $dbname = $this->config['mysql_db'] ?? 'vod_system';
        $username = $this->config['mysql_user'] ?? 'root';
        $password = $this->config['mysql_password'] ?? '';
        
        try {
            $this->pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            throw new Exception('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    // 保存视频信息到数据库
    public function saveVideoInfo($videoInfo) {
        try {
            // 准备SQL语句
            $sql = "INSERT INTO vodm3u8 (vodmc, vodimg, vodurl, vodsj, voddx) 
                    VALUES (:vodmc, :vodimg, :vodurl, :vodsj, :voddx)";
            
            $stmt = $this->pdo->prepare($sql);
            
            // 绑定参数
            $stmt->bindParam(':vodmc', $videoInfo['vodmc'], PDO::PARAM_STR);
            $stmt->bindParam(':vodimg', $videoInfo['vodimg'], PDO::PARAM_STR);
            $stmt->bindParam(':vodurl', $videoInfo['vodurl'], PDO::PARAM_STR);
            $stmt->bindParam(':vodsj', $videoInfo['vodsj'], PDO::PARAM_STR);
            $stmt->bindParam(':voddx', $videoInfo['voddx'], PDO::PARAM_STR);
            
            // 执行SQL
            $stmt->execute();
            
            return [
                'success' => true,
                'id' => $this->pdo->lastInsertId(),
                'message' => '视频信息保存成功'
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '保存失败: ' . $e->getMessage()
            ];
        }
    }
    
    // 检查数据库连接
    public function checkConnection() {
        try {
            $this->pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    // 获取PDO实例（用于其他操作）
    public function getPdo() {
        return $this->pdo;
    }
}
?>