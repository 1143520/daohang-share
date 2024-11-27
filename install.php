<?php
require_once 'db_config.php';

try {
    // 创建 site_data 表
    $pdo->exec("DROP TABLE IF EXISTS site_data");
    $pdo->exec("CREATE TABLE site_data (
        id INT PRIMARY KEY AUTO_INCREMENT,
        data_key VARCHAR(50) NOT NULL UNIQUE,
        data_value LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 创建 backups 表
    $pdo->exec("DROP TABLE IF EXISTS backups");
    $pdo->exec("CREATE TABLE backups (
        id INT PRIMARY KEY AUTO_INCREMENT,
        data LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    echo "数据库表创建成功!<br>";
    
    // 检查表结构
    echo "<br>site_data 表结构:<br>";
    $columns = $pdo->query("SHOW COLUMNS FROM site_data")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']}<br>";
    }
    
    echo "<br>backups 表结构:<br>";
    $columns = $pdo->query("SHOW COLUMNS FROM backups")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']}<br>";
    }

} catch (PDOException $e) {
    die("数据库初始化失败: " . $e->getMessage());
}
?> 