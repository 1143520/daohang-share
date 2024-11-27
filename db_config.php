<?php
$host = 'sql.wsfdb.cn';
$port = '3306';
$dbname = 'manjjihw';
$username = 'manjjihw';
$password = '13582Lxx';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::ATTR_PERSISTENT => true,
        PDO::MYSQL_ATTR_COMPRESS => true,
        PDO::ATTR_TIMEOUT => 10
    ]);

    // 检查连接和权限
    $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES");
    $stmt->fetch();

    // 确保必要的表存在
    $pdo->exec("CREATE TABLE IF NOT EXISTS `site_data` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `data_key` VARCHAR(255) NOT NULL,
        `data_value` LONGTEXT NOT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_key` (`data_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `backups` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `data` LONGTEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch(PDOException $e) {
    error_log("数据库连接失败: " . $e->getMessage());
    die("数据库连接失败: " . $e->getMessage());
}

// 添加数据迁移函数
function migrateData($oldPdo, $newPdo) {
    try {
        // 开始事务
        $newPdo->beginTransaction();
        
        // 迁移 site_data 表数据
        $stmt = $oldPdo->query("SELECT * FROM site_data");
        $siteData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($siteData as $row) {
            $stmt = $newPdo->prepare("INSERT INTO site_data (data_key, data_value) VALUES (?, ?) 
                                    ON DUPLICATE KEY UPDATE data_value = ?");
            $stmt->execute([$row['data_key'], $row['data_value'], $row['data_value']]);
        }
        
        // 迁移 backups 表数据
        $stmt = $oldPdo->query("SELECT * FROM backups");
        $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($backups as $row) {
            $stmt = $newPdo->prepare("INSERT INTO backups (data, created_at) VALUES (?, ?)");
            $stmt->execute([$row['data'], $row['created_at']]);
        }
        
        // 提交事务
        $newPdo->commit();
        return true;
    } catch (Exception $e) {
        $newPdo->rollBack();
        error_log("数据迁移失败: " . $e->getMessage());
        throw $e;
    }
}
?> 