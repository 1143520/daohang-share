<?php
// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 定义最大混淆次数
define('MAX_OBFUSCATION_COUNT', 5);

// 检查混淆次数的函数
function checkObfuscationCount() {
    $countFile = 'obfuscation_count.txt';
    
    // 如果计数文件不存在，创建它
    if (!file_exists($countFile)) {
        file_put_contents($countFile, '0');
        return true;
    }
    
    // 读取当前混淆次数
    $count = (int)file_get_contents($countFile);
    
    // 检查是否超过最大次数
    if ($count >= MAX_OBFUSCATION_COUNT) {
        die("已达到最大混淆次数限制（{$count}次）。请联系管理员重置混淆次数。\n");
    }
    
    // 增加计数并保存
    $count++;
    file_put_contents($countFile, $count);
    
    return true;
}

// 在原始代码的混淆处理之前添加检查
$template = file_get_contents('template.html');
if (!$template) {
    die('无法读取模板文件');
}

// 检查混淆次数
checkObfuscationCount();

// HTML混淆函数
function obfuscateHTML($html) {
    // 1. 保护完整的头部，包括DOCTYPE和初始化代码
    if (preg_match('/^(<!DOCTYPE.*?<\/script>)/is', $html, $matches)) {
        $header = $matches[1];
        $html = substr($html, strlen($matches[1]));
    }

    // 2. 保护关键函数和变量名
    $protectedPatterns = [
        // DOM 操作相关
        'document',
        'getElementById',
        'getElementsByClassName',
        'getElementsByTagName',
        'getElementsByName',
        'querySelector',
        'querySelectorAll',
        'createElement',
        'createTextNode',
        'appendChild',
        'removeChild',
        'replaceChild',
        'insertBefore',
        'setAttribute',
        'getAttribute',
        'hasAttribute',
        'removeAttribute',
        'closest',
        'matches',
        'contains',
        
        // 事件相关
        'addEventListener',
        'removeEventListener',
        'dispatchEvent',
        'preventDefault',
        'stopPropagation',
        'stopImmediatePropagation',
        
        // 属性和样式
        'innerHTML',
        'outerHTML',
        'textContent',
        'innerText',
        'value',
        'checked',
        'selected',
        'disabled',
        'readOnly',
        'style',
        'className',
        'classList',
        'dataset',
        
        // 存储相关
        'localStorage',
        'sessionStorage',
        'getItem',
        'setItem',
        'removeItem',
        'clear',
        
        // 数组方法
        'forEach',
        'map',
        'filter',
        'reduce',
        'push',
        'pop',
        'shift',
        'unshift',
        'splice',
        'slice',
        'concat',
        'join',
        'indexOf',
        'lastIndexOf',
        'includes',
        
        // 字符串方法
        'split',
        'replace',
        'match',
        'search',
        'trim',
        'toLowerCase',
        'toUpperCase',
        
        // 异步相关
        'setTimeout',
        'setInterval',
        'clearTimeout',
        'clearInterval',
        'Promise',
        'async',
        'await',
        'fetch',
        'then',
        'catch',
        'finally',
        
        // JSON相关
        'JSON',
        'parse',
        'stringify',
        
        // 事件名称
        'DOMContentLoaded',
        'load',
        'click',
        'change',
        'submit',
        'input',
        'keydown',
        'keyup',
        'mousedown',
        'mouseup',
        'mousemove',
        'mouseover',
        'mouseout',
        'focus',
        'blur',
        
        // Material Icons相关
        'material-icons',
        'material-symbols-outlined',
        
        // 特定功能相关
        'toggleLock',
        'openSettings',
        'closeSettings',
        'renderServices',
        'updateStyles',
        'showToast',
        'createBackup',
        'restoreBackup',
        'handleImport',
        'quickSave'
    ];

    // 3. 简单压缩HTML，保护input和select标签
    $html = preg_replace('/>\s+</m', '><', $html);
    
    // 4. 压缩CSS，保护关键样式
    $html = preg_replace_callback('/<style[^>]*>(.*?)<\/style>/is', function($matches) {
        $css = $matches[1];
        // 保护关键样式
        $css = str_replace([
            'visibility: hidden',
            'display: none',
            'opacity: 0',
            'transform:',
            'transition:'
        ], [
            'VISIBILITY_HIDDEN_PLACEHOLDER',
            'DISPLAY_NONE_PLACEHOLDER',
            'OPACITY_ZERO_PLACEHOLDER',
            'TRANSFORM_PLACEHOLDER',
            'TRANSITION_PLACEHOLDER'
        ], $css);
        
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        $css = preg_replace('/\s+/', ' ', $css);
        
        // 恢复关键样式
        $css = str_replace([
            'VISIBILITY_HIDDEN_PLACEHOLDER',
            'DISPLAY_NONE_PLACEHOLDER',
            'OPACITY_ZERO_PLACEHOLDER',
            'TRANSFORM_PLACEHOLDER',
            'TRANSITION_PLACEHOLDER'
        ], [
            'visibility: hidden',
            'display: none',
            'opacity: 0',
            'transform:',
            'transition:'
        ], $css);
        
        return "<style>" . trim($css) . "</style>";
    }, $html);
    
    // 5. 压缩JavaScript，保护关键代码
    $html = preg_replace_callback('/<script[^>]*>(.*?)<\/script>/is', function($matches) use ($protectedPatterns) {
        $js = $matches[1];
        
        // 检查是否包含需要保护的代码
        foreach ($protectedPatterns as $pattern) {
            if (strpos($js, $pattern) !== false) {
                return "<script>" . $js . "</script>";
            }
        }
        
        // 压缩其他JavaScript代码
        $js = preg_replace('/\/\*.*?\*\//s', '', $js);
        $js = preg_replace('/\/\/[^\n]*/', '', $js);
        $js = preg_replace('/\s+/', ' ', $js);
        return "<script>" . trim($js) . "</script>";
    }, $html);

    // 6. 添加改进的反调试代码
    $antiDebug = <<<EOT
<script>
(function(){
    var _0x1a2b3c = false;
    function _0x4d5e6f() {
        if(_0x1a2b3c) return;
        if((window.outerHeight-window.innerHeight>200) || 
           (window.outerWidth-window.innerWidth>200) || 
           window.devtools?.isOpen || 
           window.devtools?.orientation) {
            _0x1a2b3c = true;
            try {
                document.body.innerHTML = '';
            } catch(e) {}
        }
    }
    setInterval(_0x4d5e6f, 1000);
    document.onkeydown = function(e) {
        return !(e.keyCode === 123 || (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74)));
    };
    document.oncontextmenu = function() {
        return false;
    };
})();
</script>
EOT;

    // 7. 添加API请求和密码验证的混淆代码
    $apiObfuscation = <<<EOT
<script>
(function(){
    // ... 其他代码保持不变 ...
    
    // 修改密码检查函数
    window._0x9a1b = async function(_0x5e3f) {
        if (_0x5e3f) _0x5e3f[_0x3e2a(7)]();
        
        const _0x1c3d = document[_0x3e2a(18)](_0x3e2a(8));
        const _0x8c7d = document[_0x3e2a(18)](_0x3e2a(9));
        
        if (!_0x1c3d || !_0x8c7d) {
            console[_0x3e2a(24)](_0x3e2a(26));
            return;
        }
        
        const _0x2c1b = await window._0x2c1b(_0x1c3d[_0x3e2a(17)]);
        
        if (_0x2c1b[_0x3e2a(5)]) {
            // 更新所有相关的解锁状态
            window.isEditLocked = false;
            window[_0x3e2a(22)][_0x3e2a(23)](_0x3e2a(15), _0x3e2a(16));
            
            // 确保更新所有UI元素
            const lockIcon = document[_0x3e2a(18)]('lockIcon');
            const lockText = document[_0x3e2a(18)]('lockText');
            if (lockIcon) lockIcon.textContent = 'lock_open';
            if (lockText) lockText.textContent = '已解锁';
            
            // 移除所有锁定相关的类
            document.body.classList.remove('locked');
            document.documentElement.classList.remove('locked');
            
            // 确保所有可编辑元素启用
            document.querySelectorAll('.editable, .addable, .deletable').forEach(el => {
                el.removeAttribute('disabled');
                el.classList.remove('disabled');
            });
            
            // 触发自定义事件通知其他组件
            const unlockEvent = new CustomEvent('systemUnlocked', {
                detail: { success: true }
            });
            document.dispatchEvent(unlockEvent);
            
            // 更新锁定状态
            if (typeof window.updateLockState === 'function') {
                window.updateLockState();
            }
            
            window._0x8c7d();
        } else {
            _0x8c7d[_0x3e2a(21)][_0x3e2a(10)] = _0x3e2a(11);
            const _0x4d5e = document[_0x3e2a(18)](_0x3e2a(12));
            if (_0x4d5e) {
                _0x4d5e[_0x3e2a(19)][_0x3e2a(20)](_0x3e2a(13));
                setTimeout(() => _0x4d5e[_0x3e2a(19)][_0x3e2a(14)](_0x3e2a(13)), 500);
            }
        }
    };
    
    // 添加页面加载完成后的状态检查
    document.addEventListener('DOMContentLoaded', function() {
        // 检查localStorage中的锁定状态
        const isLocked = window.localStorage.getItem('isLocked') === 'true';
        if (!isLocked) {
            window.isEditLocked = false;
            document.body.classList.remove('locked');
            document.documentElement.classList.remove('locked');
            
            // 确保所有可编辑元素启用
            document.querySelectorAll('.editable, .addable, .deletable').forEach(el => {
                el.removeAttribute('disabled');
                el.classList.remove('disabled');
            });
        }
        
        // ... 其他原有的DOMContentLoaded代码 ...
    });
})();
</script>
EOT;

    // 7. 重组HTML
    $result = $header ?? '';
    $result .= str_replace('</head>', $antiDebug . $apiObfuscation . '</head>', $html);

    return $result;
}

// 混淆模板
$obfuscated = obfuscateHTML($template);

// 备份原始文件
$backupFile = 'template.original.' . date('Y-m-d-H-i-s') . '.html';
if (!copy('template.html', $backupFile)) {
    die("备份原始文件失败\n");
}

// 保存混淆后的文件
if (!file_put_contents('template.html', $obfuscated)) {
    die("保存混淆文件失败\n");
}

// 读取当前混淆次数
$currentCount = (int)file_get_contents('obfuscation_count.txt');

echo "混淆成功完成！\n";
echo "当前混淆次数：$currentCount / " . MAX_OBFUSCATION_COUNT . "\n";
echo "原始文件已备份为: $backupFile\n";
?> 