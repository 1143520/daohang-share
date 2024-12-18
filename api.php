<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require_once 'db_config.php';
require_once 'config.php';

// 添加错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 添加缓存控制头
header('Cache-Control: public, max-age=300'); // 5分钟缓存
header('ETag: ' . md5(json_encode(getData()))); // 添加ETag

// 检查是否有缓存
$etag = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? $_SERVER['HTTP_IF_NONE_MATCH'] : null;
if ($etag === md5(json_encode(getData()))) {
    http_response_code(304); // Not Modified
    exit;
}

// 如果是预检请求，直接返回成功
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 密码验证请求的快速处理路径
if (isset($_GET['action']) && $_GET['action'] === 'verifyPassword') {
    $input = json_decode(file_get_contents('php://input'), true);
    echo json_encode([
        'success' => ($input['password'] ?? '') === ADMIN_PASSWORD,
        'message' => ($input['password'] ?? '') === ADMIN_PASSWORD ? '验证成功' : '密码错误'
    ]);
    exit;
}

// 添加一个新的 API 端点用于检查锁定状态
if (isset($_GET['action']) && $_GET['action'] === 'checkLockStatus') {
    $stmt = $pdo->prepare("SELECT data_value FROM site_data WHERE data_key = 'lockStatus'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'isLocked' => $result ? json_decode($result['data_value'])->isLocked : true
    ]);
    exit;
}

// 获取数据的函数
function getData() {
    global $pdo;
    try {
        error_log("开始获取数据");
        
        // 禁用查询缓存
        $pdo->query("SET SESSION query_cache_type = OFF");
        
        // 获取所有数据
        $stmt = $pdo->query("SELECT data_key, data_value, updated_at FROM site_data");
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $decodedValue = json_decode($row['data_value'], true);
            if ($decodedValue === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log("JSON解码错误: " . json_last_error_msg());
                continue;
            }
            $result[$row['data_key']] = $decodedValue;
            error_log("已获取 {$row['data_key']} 数据，最后更新时间: {$row['updated_at']}");
        }
        
        error_log("获取到数据: " . json_encode($result));
        return $result;
    } catch (Exception $e) {
        error_log("获取数据错误: " . $e->getMessage());
        throw new Exception('获取数据失败: ' . $e->getMessage());
    }
}

// 保存所有数据的函数
function saveAllData($data) {
    global $pdo;
    try {
        error_log("开始保存所有数据: " . json_encode($data));
        
        $pdo->beginTransaction();

        // 保存服务列表
        if (isset($data['services'])) {
            $stmt = $pdo->prepare("INSERT INTO site_data (data_key, data_value) 
                                  VALUES ('services', :value) 
                                  ON DUPLICATE KEY UPDATE data_value = :value");
            $jsonServices = json_encode($data['services'], JSON_UNESCAPED_UNICODE);
            if ($jsonServices === false) {
                throw new Exception('服务列表JSON编码失败');
            }
            $stmt->execute([':value' => $jsonServices]);
            error_log("服务列表已保存");
        }

        // 保存设置
        if (isset($data['settings'])) {
            // 添加最后更新时间
            $data['settings']['lastUpdated'] = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("INSERT INTO site_data (data_key, data_value) 
                                  VALUES ('settings', :value) 
                                  ON DUPLICATE KEY UPDATE data_value = :value");
            $jsonSettings = json_encode($data['settings'], JSON_UNESCAPED_UNICODE);
            if ($jsonSettings === false) {
                throw new Exception('设置JSON编码失败');
            }
            $stmt->execute([':value' => $jsonSettings]);
            error_log("设置已保存");
        }

        $pdo->commit();
        error_log("所有数据保存成功");
        return ['success' => true];
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("保存数据错误: " . $e->getMessage());
        throw new Exception('保存数据失败: ' . $e->getMessage());
    }
}

// 添加数据验证函数
function validateData($data) {
    if (!is_array($data)) {
        return false;
    }
    
    // 验证必要的字段
    $requiredFields = ['services', 'settings'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            return false;
        }
    }
    
    return true;
}

// 修改保存数据函数
function saveData($key, $value) {
    global $pdo;
    try {
        error_log("开始保存数据 - 键: {$key}");
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 如果是设置数据，确保完整性
        if ($key === 'settings') {
            // 获取现有设置
            $stmt = $pdo->prepare("SELECT data_value FROM site_data WHERE data_key = 'settings'");
            $stmt->execute();
            $existingData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingData) {
                $existingSettings = json_decode($existingData['data_value'], true);
                if ($existingSettings === null) {
                    error_log("现有设置JSON解码失败");
                    $existingSettings = [];
                }
                // 合并现有设置和新设置
                $value = array_merge($existingSettings, $value);
            }
            
            // 添加更新时间戳
            $value['lastUpdated'] = date('Y-m-d H:i:s');
        }
        
        // 准备SQL语句
        $stmt = $pdo->prepare("INSERT INTO site_data (data_key, data_value) 
                              VALUES (:key, :value) 
                              ON DUPLICATE KEY UPDATE data_value = :value");
        
        // 转换为JSON
        $jsonValue = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($jsonValue === false) {
            throw new Exception('JSON编码失败: ' . json_last_error_msg());
        }
        
        // 执行SQL
        $stmt->execute([
            ':key' => $key,
            ':value' => $jsonValue
        ]);
        
        // 提交事务
        $pdo->commit();
        
        error_log("数据保存成功 - 键: {$key}");
        return ['success' => true];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("保存数据错误: " . $e->getMessage());
        return ['error' => '保存失败: ' . $e->getMessage()];
    }
}

// 修改备份处理函数
function handleBackups($action) {
    global $pdo;
    
    try {
        switch ($action) {
            case 'getBackups':
                error_log("开始获取备份列表");
                
                // 检查数据库连接
                if (!$pdo) {
                    throw new Exception('数据库连接失败');
                }
                
                // 检查表是否存在
                $stmt = $pdo->query("SHOW TABLES LIKE 'backups'");
                if ($stmt->rowCount() === 0) {
                    // 如果表不存在，创建表
                    $pdo->exec("CREATE TABLE IF NOT EXISTS `backups` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `data` LONGTEXT NOT NULL,
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX `idx_created_at` (`created_at`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                    
                    error_log("创建备份表成功");
                    return []; // 返回空数组
                }
                
                // 使用简单的查询
                $stmt = $pdo->query("SELECT id, created_at as date FROM backups ORDER BY created_at DESC");
                if (!$stmt) {
                    throw new Exception('查询备份失败');
                }
                
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("查询到 " . count($result) . " 条备份记录");
                
                // 添加响应头
                header('Content-Type: application/json');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                // 确保返回的是数组
                if (!is_array($result)) {
                    $result = [];
                }
                
                return $result;
                
            case 'createBackup':
                error_log("开始创建新备份");
                
                // 检查表是否存在，如果不存在则创建
                $pdo->exec("CREATE TABLE IF NOT EXISTS `backups` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `data` LONGTEXT NOT NULL,
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                
                $data = json_decode(file_get_contents('php://input'), true);
                if (!$data) {
                    throw new Exception('无效的备份数据');
                }
                
                $stmt = $pdo->prepare("INSERT INTO backups (data) VALUES (?)");
                $stmt->execute([json_encode($data, JSON_UNESCAPED_UNICODE)]);
                $id = $pdo->lastInsertId();
                
                error_log("备份创建成功，ID: " . $id);
                return [
                    'success' => true,
                    'id' => $id,
                    'message' => '备份创建成功'
                ];
                
            case 'getBackup':
                if (!isset($_GET['id'])) {
                    throw new Exception('缺少备份ID');
                }
                
                $stmt = $pdo->prepare("SELECT * FROM backups WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                $backup = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$backup) {
                    throw new Exception('备份不存在');
                }
                
                return $backup;
                
            case 'deleteBackup':
                if (!isset($_GET['id'])) {
                    throw new Exception('缺少备份ID');
                }
                
                $stmt = $pdo->prepare("DELETE FROM backups WHERE id = ?");
                $stmt->execute([$_GET['id']]);
                return ['success' => true];
                
            default:
                throw new Exception('未知的备份操作');
        }
    } catch (Exception $e) {
        error_log("备份操作错误: " . $e->getMessage());
        http_response_code(500);
        return ['error' => $e->getMessage()];
    }
}

// 修改 getDatabaseInfo 函数
function getDatabaseInfo() {
    global $pdo;
    try {
        // 获取IP信息
        $ipInfo = [
            'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'HTTP_X_REAL_IP' => $_SERVER['HTTP_X_REAL_IP'] ?? 'Not set',
            'HTTP_X_FORWARDED_FOR' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not set',
            'HTTP_CLIENT_IP' => $_SERVER['HTTP_CLIENT_IP'] ?? 'Not set',
            'SERVER_ADDR' => $_SERVER['SERVER_ADDR'] ?? 'Not set',
            'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? 'Not set',
            'REQUEST_SCHEME' => $_SERVER['REQUEST_SCHEME'] ?? 'Not set',
            'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'Not set',
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'Not set'
        ];

        // 1. 获取数据库结构信息
        $dbStructure = [];
        
        // 获取 site_data 表结构
        $stmt = $pdo->query("SHOW CREATE TABLE site_data");
        $siteDataStructure = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbStructure['site_data'] = $siteDataStructure['Create Table'];
        
        // 获取 backups 表结构
        $stmt = $pdo->query("SHOW CREATE TABLE backups");
        $backupsStructure = $stmt->fetch(PDO::FETCH_ASSOC);
        $dbStructure['backups'] = $backupsStructure['Create Table'];
        
        // 2. 获取表的索引信息
        $indexes = [];
        
        // site_data 表索引
        $stmt = $pdo->query("SHOW INDEX FROM site_data");
        $indexes['site_data'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // backups 表索引
        $stmt = $pdo->query("SHOW INDEX FROM backups");
        $indexes['backups'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. 获取现有的统计信息
        $stmt = $pdo->query("SELECT data_key, LENGTH(data_value) as size FROM site_data");
        $siteDataInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $siteDataSize = array_sum(array_column($siteDataInfo, 'size'));
        $siteDataCount = count($siteDataInfo);
        
        $stmt = $pdo->query("SELECT COUNT(*) as count, SUM(LENGTH(data)) as total_size FROM backups");
        $backupInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $backupCount = $backupInfo['count'] ?? 0;
        $backupSize = $backupInfo['total_size'] ?? 0;

        // 4. 获取表的状态信息
        $stmt = $pdo->query("SHOW TABLE STATUS WHERE Name IN ('site_data', 'backups')");
        $tableStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. 格式化返回数据
        return [
            'database_structure' => [
                'tables' => $dbStructure,
                'indexes' => $indexes,
                'table_status' => $tableStatus
            ],
            'statistics' => [
                'site_data' => [
                    'size' => number_format($siteDataSize / (1024 * 1024), 2) . ' MB',
                    'records' => $siteDataCount,
                    'keys' => array_column($siteDataInfo, 'data_key')
                ],
                'backups' => [
                    'size' => number_format($backupSize / (1024 * 1024), 2) . ' MB',
                    'count' => $backupCount
                ],
                'total_size' => number_format(($siteDataSize + $backupSize) / (1024 * 1024), 2) . ' MB'
            ],
            'performance' => [
                'site_data' => [
                    'avg_row_size' => $siteDataCount > 0 ? number_format($siteDataSize / $siteDataCount / 1024, 2) . ' KB' : '0 KB',
                    'data_free' => number_format($tableStatus[0]['Data_free'] / 1024, 2) . ' KB'
                ],
                'backups' => [
                    'avg_row_size' => $backupCount > 0 ? number_format($backupSize / $backupCount / 1024, 2) . ' KB' : '0 KB',
                    'data_free' => number_format($tableStatus[1]['Data_free'] / 1024, 2) . ' KB'
                ]
            ],
            // 添加IP信息部分
            'connection_info' => [
                'ip_info' => $ipInfo,
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown'
                ]
            ]
        ];
    } catch (Exception $e) {
        error_log("获取数据库信息错误: " . $e->getMessage());
        throw new Exception('获取数据库信息失败: ' . $e->getMessage());
    }
}

// 主要请求处理逻辑
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? null;

    // 添加请求日志
    error_log("收到请求 - 方法: {$method}, 动作: " . ($action ?? 'none'));

    if ($action) {
        $result = match($action) {
            'getDatabaseInfo' => getDatabaseInfo(),
            'getBackups', 'createBackup', 'getBackup', 'deleteBackup' => handleBackups($action),
            default => throw new Exception('未知的操作')
        };
        
        // 确保返回正确的 HTTP 状态码和响应头
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        
        if (isset($result['error'])) {
            http_response_code(400);
        } else {
            http_response_code(200);
        }
        
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    switch ($method) {
        case 'GET':
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            $data = getData();
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input || !isset($input['key']) || !isset($input['value'])) {
                throw new Exception('无效的数据格式');
            }
            
            error_log("收到POST数据: " . json_encode($input));
            
            if ($input['key'] === 'allData') {
                $result = saveAllData($input['value']);
            } else {
                $result = saveData($input['key'], $input['value']);
            }
            
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
            
        default:
            throw new Exception('不支持的请求方法');
    }
} catch (Exception $e) {
    error_log("API错误: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?> 