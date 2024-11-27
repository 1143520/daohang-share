<?php
// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 创建日志记录函数
function writeLog($message, $type = 'ERROR') {
    // 只在发生错误时记录日志
    if ($type === 'ERROR' || $type === 'EXCEPTION' || $type === 'JAVASCRIPT_ERROR') {
        // 确保logs目录存在
        $logDir = __DIR__ . '/logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }

        // 获取今天的日期和时间
        $today = date('Y-m-d');
        $time = date('H:i:s');
        
        // 创建日志文件名（使用日期）
        $logFile = $logDir . '/' . $today . '.log';

        // 准备日志信息
        $logInfo = [
            'timestamp' => $today . ' ' . $time,
            'type' => $type,
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'None',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown'
        ];

        // 获取现有日志内容
        $logs = [];
        if (file_exists($logFile)) {
            $logs = json_decode(file_get_contents($logFile), true) ?: [];
        }

        // 添加新日志
        array_push($logs, $logInfo);

        // 如果超过50条，删除最早的记录
        if (count($logs) > 50) {
            array_shift($logs);
        }

        // 写入日志文件（JSON格式，便于后续分析）
        file_put_contents($logFile, json_encode($logs, JSON_PRETTY_PRINT));
    }
}

// 设置错误处理函数
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $errorTypes = [
        E_ERROR => 'Fatal Error',
        E_WARNING => 'Warning',
        E_PARSE => 'Parse Error',
        E_NOTICE => 'Notice',
        E_CORE_ERROR => 'Core Error',
        E_CORE_WARNING => 'Core Warning',
        E_COMPILE_ERROR => 'Compile Error',
        E_COMPILE_WARNING => 'Compile Warning',
        E_USER_ERROR => 'User Error',
        E_USER_WARNING => 'User Warning',
        E_USER_NOTICE => 'User Notice',
        E_STRICT => 'Strict Standards',
        E_RECOVERABLE_ERROR => 'Recoverable Error',
        E_DEPRECATED => 'Deprecated',
        E_USER_DEPRECATED => 'User Deprecated'
    ];

    $errorType = $errorTypes[$errno] ?? 'Unknown Error';
    $message = [
        'error_type' => $errorType,
        'error_message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
    ];
    
    writeLog(json_encode($message), 'ERROR');
    return false; // 允许错误继续传播
});

// 设置异常处理函数
set_exception_handler(function($exception) {
    $message = [
        'exception_type' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'stack_trace' => $exception->getTraceAsString()
    ];
    writeLog(json_encode($message), 'EXCEPTION');
});

// 添加安全头
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'self\' https: \'unsafe-inline\' \'unsafe-eval\' data:;');

// 添加选择性的快捷键控制代码
$protection_code = <<<EOT
<script>
(function() {
    // 修改文本选择限制,允许在编辑模式和输入框中选择
    document.onselectstart = function(e) {
        var target = e.target || e.srcElement;
        // 允许在输入框、文本框和编辑模式下选择文本
        if (target.tagName === 'INPUT' || 
            target.tagName === 'TEXTAREA' ||
            target.closest('.card.editing') ||
            target.closest('.edit-form')) {
            return true;
        }
        return false;
    };
    
    // 修改 CSS 样式：允许在编辑模式下选择文本
    var style = document.createElement('style');
    style.type = 'text/css';
    style.innerHTML = `
        body:not(input):not(textarea):not(.editing) {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        input, textarea, .card.editing, .edit-form {
            -webkit-user-select: text !important;
            -moz-user-select: text !important;
            -ms-user-select: text !important;
            user-select: text !important;
        }
    `;
    document.head.appendChild(style);
})();
</script>
EOT;

// 读取并输出页面内容
$page_content = file_get_contents('template.html');
if ($page_content === false) {
    writeLog('Error loading template.html', 'ERROR');
    die('Error loading template');
}

// 在</head>标签前插入保护代码
$page_content = str_replace('</head>', $protection_code . '</head>', $page_content);

echo $page_content;
?>