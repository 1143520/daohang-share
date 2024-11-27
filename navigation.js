// 默认服务配置
const defaultServices = [
    {
        title: '我的笔记',
        url: 'https://note.1143520.xyz',
        icon: 'home'
    },
    {
        title: '哪吒探针',
        url: 'https://nz.1143520.xyz',
        icon: 'desktop_windows'
    },
    {
        title: '简单图床',
        url: 'https://www.freeimg.cn/upload',
        icon: 'image'
    },
    {
        title: '在线记事',
        url: 'https://tools.196000.xyz/notes/mpxyj',
        icon: 'event_note'
    },
    {
        title: '我的服务器',
        url: 'https://1panel.1143520.xyz',
        icon: 'dns'
    },
    {
        title: 'NodeSeek关键字提醒',
        url: 'https://t.me/NodeSeek_Msg_Bot',
        icon: 'notifications'
    },
    {
        title: '剩余价值计算器',
        url: 'https://tools.196000.xyz/jsq',
        icon: 'calculate'
    },
    {
        title: '随机密码生成器',
        url: 'https://tools.196000.xyz/password/',
        icon: 'key'
    },
    {
        title: '客访问统计',
        url: 'https://tongji.baidu.com',
        icon: 'bar_chart'
    }
];

// 全局变量
let services = JSON.parse(localStorage.getItem('services')) || defaultServices;
let isEditLocked = localStorage.getItem('isLocked') !== 'false';
let settingsCopy = null;
let isThemeApplied = false;
let initializeRequestCount = 0;
let lastInitializeTime = 0;
const MAX_REQUESTS = 50;
const THROTTLE_INTERVAL = 5000;

// 工具函数
function getFaviconUrl(url) {
    try {
        const domain = new URL(url).hostname;
        return `https://www.google.com/s2/favicons?domain=${domain}&sz=32`;
    } catch {
        return null;
    }
}

// 防抖函数
function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Toast提示函数
function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;

    switch (type) {
        case 'warning':
            toast.style.backgroundColor = '#ff9800';
            break;
        case 'error':
            toast.style.backgroundColor = '#f44336';
            break;
        case 'success':
            toast.style.backgroundColor = '#4caf50';
            break;
        default:
            toast.style.backgroundColor = '#323232';
    }

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.classList.add('fade-out');
        setTimeout(() => toast.remove(), 300);
    }, 2000);
}

// 确认对话框函数
function showConfirmDialog(message, onConfirm, onCancel) {
    const overlay = document.getElementById('dialogOverlay');
    const dialog = document.getElementById('confirmDialog');
    const content = dialog.querySelector('.dialog-content');
    const confirmBtn = dialog.querySelector('.confirm-btn');
    const cancelBtn = dialog.querySelector('.cancel-btn');

    content.textContent = message;
    overlay.classList.add('show');
    dialog.classList.add('show');

    const handleConfirm = () => {
        overlay.classList.remove('show');
        dialog.classList.remove('show');
        confirmBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', handleCancel);
        onConfirm && onConfirm();
    };

    const handleCancel = () => {
        overlay.classList.remove('show');
        dialog.classList.remove('show');
        confirmBtn.removeEventListener('click', handleConfirm);
        cancelBtn.removeEventListener('click', handleCancel);
        onCancel && onCancel();
    };

    confirmBtn.addEventListener('click', handleConfirm);
    cancelBtn.addEventListener('click', handleCancel);
    overlay.addEventListener('click', handleCancel);
}

// 获取默认设置
function getDefaultSettings() {
    const isMobile = window.innerWidth <= 768;

    const commonSettings = {
        titleSize: '55',
        titleFontFamily: "'Microsoft YaHei'",
        titleFontWeight: '700',
        cardSize: '19',
        fontFamily: "'Microsoft YaHei'",
        fontWeight: '700',
        cardsPerRow: '3',
        gridGap: '24',
        maxWidth: '1100',
        themeMode: 'light',
        gridSize: '29',
        gridOpacity: '84',
        bgOpacity: '10',
        cardOpacity: '85',
        cardBlur: '0',
        blurStrength: '10',
        siteIconUrl: 'https://www.freeimg.cn/i/2024/11/13/67338175a26bd.webp'
    };

    if (isMobile) {
        return {
            ...commonSettings,
            titleSize: '32',
            cardSize: '16',
            cardsPerRow: '1',
            gridGap: '16',
            maxWidth: '600'
        };
    }
    return commonSettings;
}

// 渲染服务列表
function renderServices() {
    const grid = document.getElementById('services-grid');
    const settings = JSON.parse(localStorage.getItem('siteSettings')) || getDefaultSettings();

    grid.style.opacity = '0';

    const validServices = services.filter(service =>
        service && service.title && service.url &&
        typeof service.title === 'string' &&
        typeof service.url === 'string'
    );

    const servicesHtml = validServices.map((service, index) => {
        let iconHtml;
        if (service.iconUrl) {
            iconHtml = `<img src="${service.iconUrl}" class="site-icon" onerror="this.style.display='none'">`;
        } else if (service.icon) {
            iconHtml = `
                <i class="material-symbols-outlined" style="
                    font-size: 32px;
                    color: var(--text-color);
                    transition: all 0.3s ease;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 32px;
                    height: 32px;
                ">${service.icon.toLowerCase().replace(/\s+/g, '_')}</i>
            `;
        } else {
            const faviconUrl = getFaviconUrl(service.url);
            iconHtml = faviconUrl ?
                `<img src="${faviconUrl}" class="site-icon" onerror="this.style.display='none'">` :
                `<i class="material-symbols-outlined" style="
                    font-size: 32px;
                    color: var(--text-color);
                    transition: all 0.3s ease;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 32px;
                    height: 32px;
                ">link</i>`;
        }

        return `
            <a href="${service.url}" class="card" target="_blank" rel="noopener noreferrer" id="card-${index}">
                ${iconHtml}
                <span style="font-weight: ${settings.fontWeight}; font-size: ${settings.cardSize}px; font-family: ${settings.fontFamily};">${service.title}</span>
                <div class="edit-form">
                    <input type="text" class="title-input" value="${service.title}" placeholder="输入标题">
                    <input type="url" class="url-input" value="${service.url}" placeholder="输入链接">
                    <div class="edit-buttons">
                        <button class="mini-btn save-btn" onclick="event.preventDefault(); saveEdit(${index})">保存</button>
                        <button class="mini-btn cancel-btn" onclick="event.preventDefault(); cancelEdit(${index})">取消</button>
                    </div>
                </div>
                <button class="edit-btn material-icons" onclick="event.preventDefault(); startEdit(${index})">
                    edit
                </button>
                <button class="delete-btn material-icons" onclick="event.preventDefault(); deleteService(${index})">
                    close
                </button>
            </a>
        `;
    }).join('');

    const addButton = `
        <div class="add-button" onclick="openModal()">
            <i class="material-icons">add</i>
            <span>添加新链接</span>
        </div>
    `;

    grid.innerHTML = servicesHtml + addButton;

    requestAnimationFrame(() => {
        grid.style.opacity = '1';
        const cards = grid.querySelectorAll('.card');
        cards.forEach((card, index) => {
            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 50);
        });
    });
}

// 初始化函数
async function initializeFromServer() {
    if (initializeRequestCount >= MAX_REQUESTS) {
        console.warn('已达到最大请求次数限制');
        return;
    }

    const now = Date.now();
    if (now - lastInitializeTime < THROTTLE_INTERVAL) {
        return;
    }

    try {
        initializeRequestCount++;
        lastInitializeTime = now;

        const response = await fetch('/api.php', {
            headers: {
                'Cache-Control': 'max-age=300'
            }
        });

        if (!response.ok) {
            throw new Error('服务器响应异常');
        }

        const data = await response.json();
        const hasLocalChanges = localStorage.getItem('hasUnsavedChanges') === 'true';

        if (!hasLocalChanges && data.services) {
            const currentServices = localStorage.getItem('services');
            if (JSON.stringify(data.services) !== currentServices) {
                services = data.services;
                localStorage.setItem('services', JSON.stringify(services));
                renderServices();
            }
        }

        if (data.settings) {
            const currentSettings = localStorage.getItem('siteSettings');
            if (JSON.stringify(data.settings) !== currentSettings) {
                localStorage.setItem('siteSettings', JSON.stringify(data.settings));
                localStorage.setItem('currentTheme', data.settings.themeMode);
                loadSettings();
                updateStyles();
            }
        }

    } catch (error) {
        console.error('初始化失败:', error);
        if (!localStorage.getItem('siteSettings')) {
            const defaultSettings = getDefaultSettings();
            localStorage.setItem('siteSettings', JSON.stringify(defaultSettings));
        }
        loadSettings();
        renderServices();
        updateStyles();
    }
}

// 事件监听器设置
document.addEventListener('DOMContentLoaded', function () {
    services = JSON.parse(localStorage.getItem('services')) || defaultServices;
    loadSettings();

    isEditLocked = localStorage.getItem('isLocked') !== 'false';
    updateLockState();

    setTimeout(() => {
        renderServices();
        updateStyles();
        document.documentElement.classList.add('ready');
        document.body.classList.add('ready');
    }, 50);

    initializeFromServer().catch(console.error);

    // 添加其他事件监听器
    setupEventListeners();
});

// 导出所需的函数和变量
export {
    renderServices,
    initializeFromServer,
    showToast,
    showConfirmDialog,
    getDefaultSettings,
    // ... 其他需要导出的函数
}; 