<?php
function cleanupLogs() {
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        return;
    }

    // 获取所有日志文件
    $files = glob($logDir . '/*.log');
    $counterFiles = glob($logDir . '/counter_*.txt');
    
    // 保留最近7天的日志
    $cutoff = strtotime('-7 days');
    
    foreach (array_merge($files, $counterFiles) as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
}

cleanupLogs(); 