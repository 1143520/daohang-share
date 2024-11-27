<?php
// 设置执行时间限制
set_time_limit(300); // 5分钟
ini_set('memory_limit', '256M');

// 旧数据库配置
$old_host = '1Panel-mysql-WFuE';
$old_port = '3306';
$old_dbname = 'daohang';
$old_username = 'daohang';
$old_password = '13582Lxx';

try {
    // 连接旧数据库
    $old_dsn = "mysql:host=$old_host;port=$old_port;dbname=$old_dbname;charset=utf8mb4";
    $oldPdo = new PDO($old_dsn, $old_username, $old_password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // 连接新数据库
    require_once 'db_config.php';
    
    // 执行迁移
    migrateData($oldPdo, $pdo);
    
    echo "数据迁移成功!\n";
} catch (Exception $e) {
    echo "迁移失败: " . $e->getMessage() . "\n";
    exit(1);
}
?> 