/**
 * Клиентская логика мессенджера
 */

// Глобальные переменные
let myLogin = null;
let myRole = null;
let csrfToken = null;
let currentRecipient = null;
let allUsers = [];
let unreadCounts = {};
let allMessagesCache = {};
let typingTimeout = null;
let refreshInterval = null;
let pingInterval = null;

// Эмодзи для панели
const EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '😡', '🎉', '🔥', '👋', '🙏', '💯', '✨', '🌟', '💪', '🤔', '👀'];

// Звуки (синтезированные)
const audioContext = new (window.AudioContext || window.webkitAudioContext)();

function playSendSound() {
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    oscillator.frequency.value = 800;
    oscillator.type = 'sine';
    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.1);
}

function playReceiveSound() {
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    oscillator.frequency.value = 600;
    oscillator.type = 'sine';
    gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.15);
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.15);
}

function vibrate() {
    if (navigator.vibrate) {
        navigator.vibrate(50);
    }
}

// Уведомления
function showNotification(message, type = 'success') {
    const container = document.getElementById('notifications-container');
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    container.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// API запрос
async function api(data) {
    const formData = new FormData();
    for (const key in data) {
        if (data[key] !== null && data[key] !== undefined) {
            formData.append(key, data[key]);
        }
    }
    
    // Добавляем CSRF токен для POST запросов
    if (csrfToken && !['check_auth', 'ping', 'get_history', 'get_avatar'].includes(data.action)) {
        formData.append('csrf_token', csrfToken);
    }
    
    try {
        const response = await fetch('chat.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!response.ok && result.error) {
            throw new Error(result.error);
        }
        
        return result;
    } catch (error) {
        console.error('API error:', error);
        throw error;
    }
}

// GET запрос для истории
async function apiGet(params) {
    const queryString = new URLSearchParams(params).toString();
    
    try {
        const response = await fetch(`chat.php?${queryString}`, {
            method: 'GET'
        });
        
        const result = await response.json();
        
        if (!response.ok && result.error) {
            throw new Error(result.error);
        }
        
        return result;
    } catch (error) {
        console.error('API GET error:', error);
        throw error;
    }
}

// Проверка авторизации при загрузке
async function checkAuth() {
    try {
        const result = await api({ action: 'check_auth' });
        
        if (result.authenticated) {
            myLogin = result.login;
            myRole = result.role;
            csrfToken = result.csrf_token;
            showChatScreen();
        } else {
            showAuthScreen();
        }
    } catch (error) {
        showAuthScreen();
    }
}

// Переключение экранов
function showAuthScreen() {
    document.getElementById('auth-screen').style.display = 'flex';
    document.getElementById('chat-screen').style.display = 'none';
}

function showChatScreen() {
    document.getElementById('auth-screen').style.display = 'none';
    document.getElementById('chat-screen').style.display = 'block';
    
    // Загрузка данных
    loadMyAvatar();
    loadRecipients();
    initEmojiPanel();
    
    // Показ админ кнопки для админов
    if (myRole === 'admin' || myRole === 'creator') {
        document.getElementById('admin-link').style.display = 'block';
    }
    
    // Запуск интервалов
    startIntervals();
}

// Старт интервалов обновления
function startIntervals() {
    // Обновление каждые 3 секунды
    refreshInterval = setInterval(() => {
        if (currentRecipient) {
            loadHistory(currentRecipient);
        }
        updateOnlineStatuses();
    }, 3000);
    
    // Ping каждые 15 секунд
    pingInterval = setInterval(async () => {
        try {
            await api({ action: 'ping' });
        } catch (e) {}
    }, 15000);
}

// Остановка интервалов
function stopIntervals() {
    if (refreshInterval) clearInterval(refreshInterval);
    if (pingInterval) clearInterval(pingInterval);
}

// Вход
document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const login = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    
    try {
        const result = await api({
            action: 'login',
            login: login,
            password: password
        });
        
        myLogin = result.login;
        csrfToken = result.csrf_token;
        myRole = (await api({ action: 'check_auth' })).role;
        
        showChatScreen();
        showNotification('Добро пожаловать!');
    } catch (error) {
        document.getElementById('auth-error').textContent = error.message;
        vibrate();
    }
});

// Регистрация
document.getElementById('register-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const login = document.getElementById('register-username').value.trim();
    const password = document.getElementById('register-password').value;
    const email = document.getElementById('register-email').value.trim();
    
    try {
        const result = await api({
            action: 'register',
            login: login,
            password: password,
            email: email
        });
        
        myLogin = result.login;
        csrfToken = result.csrf_token;
        myRole = (await api({ action: 'check_auth' })).role;
        
        showChatScreen();
        showNotification('Регистрация успешна!');
    } catch (error) {
        document.getElementById('auth-error').textContent = error.message;
        vibrate();
    }
});

// Переключение форм входа/регистрации
function showRegister() {
    document.getElementById('login-form').style.display = 'none';
    document.getElementById('register-form').style.display = 'block';
    document.getElementById('auth-error').textContent = '';
}

function showLogin() {
    document.getElementById('register-form').style.display = 'none';
    document.getElementById('login-form').style.display = 'block';
    document.getElementById('auth-error').textContent = '';
}

// Выход
async function logout() {
    stopIntervals();
    try {
        await api({ action: 'logout' });
    } catch (e) {}
    location.reload();
}

// Загрузка аватарки текущего пользователя
function loadMyAvatar() {
    const avatarImg = document.getElementById('my-avatar');
    const usernameSpan = document.getElementById('my-username');
    
    avatarImg.src = `uploads/avatars/${myLogin}.jpg?t=${Date.now()}`;
    avatarImg.style.display = 'block';
    avatarImg.onerror = () => {
        avatarImg.style.display = 'none';
    };
    usernameSpan.textContent = myLogin;
}

// Загрузка списка контактов
async function loadRecipients() {
    try {
        const result = await api({ action: 'get_users' });
        allUsers = result.users || [];
        renderContactList();
    } catch (error) {
        console.error('Error loading users:', error);
    }
}

// Отрисовка списка контактов
function renderContactList(filter = '') {
    const list = document.getElementById('contacts-list');
    list.innerHTML = '';
    
    const filteredUsers = allUsers.filter(user => 
        user.login.toLowerCase().includes(filter.toLowerCase())
    );
    
    filteredUsers.forEach(user => {
        const item = document.createElement('div');
        item.className = 'contact-item';
        if (user.login === currentRecipient) {
            item.classList.add('active');
        }
        
        const avatarHtml = getAvatarHtml(user.login, user.avatar, 48);
        const onlineHtml = user.online ? '<span class="online-indicator"></span>' : '';
        const unreadHtml = unreadCounts[user.login] > 0 
            ? `<span class="unread-badge">${unreadCounts[user.login]}</span>` 
            : '';
        
        item.innerHTML = `
            ${avatarHtml}
            <div class="contact-info">
                <div class="contact-name">${escapeHtml(user.login)}</div>
                <div class="contact-status">
                    ${onlineHtml}
                    <span>${user.online ? 'Онлайн' : 'Офлайн'}</span>
                </div>
            </div>
            ${unreadHtml}
        `;
        
        item.onclick = () => selectContact(user.login);
        list.appendChild(item);
    });
}

// HTML для аватарки
function getAvatarHtml(login, avatarUrl, size = 40) {
    if (avatarUrl) {
        return `<img src="${avatarUrl}" class="contact-avatar" style="width:${size}px;height:${size}px;" alt="${login}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                <div class="contact-avatar-placeholder" style="display:none;width:${size}px;height:${size}px;font-size:${size/2.5}px;">${login[0].toUpperCase()}</div>`;
    }
    return `<div class="contact-avatar-placeholder" style="width:${size}px;height:${size}px;font-size:${size/2.5}px;">${login[0].toUpperCase()}</div>`;
}

// Выбор контакта
async function selectContact(login) {
    currentRecipient = login;
    unreadCounts[login] = 0;
    
    document.getElementById('current-chat-name').textContent = login;
    document.getElementById('messages-container').innerHTML = '';
    
    // Обновляем активный класс в списке
    document.querySelectorAll('.contact-item').forEach(item => {
        item.classList.remove('active');
    });
    
    // Закрываем панель на мобильных
    if (window.innerWidth <= 768) {
        document.getElementById('contacts-panel').classList.remove('show');
    }
    
    await loadHistory(login);
    renderContactList(document.querySelector('.search-input').value);
}

// Загрузка истории сообщений
async function loadHistory(recipient) {
    try {
        const result = await apiGet({
            action: 'get_history',
            recipient: recipient,
            limit: 50
        });
        
        allMessagesCache[recipient] = result.messages || [];
        renderMessages(result.messages);
        
        // Индикатор набора текста
        const typingIndicator = document.getElementById('typing-indicator');
        if (result.typing_user && result.typing_user !== myLogin) {
            typingIndicator.textContent = `${result.typing_user} печатает...`;
            typingIndicator.style.display = 'block';
        } else {
            typingIndicator.style.display = 'none';
        }
    } catch (error) {
        console.error('Error loading history:', error);
    }
}

// Отрисовка сообщений
function renderMessages(messages) {
    const container = document.getElementById('messages-container');
    const wasScrolled = container.scrollHeight - container.scrollTop - container.clientHeight < 100;
    
    container.innerHTML = '';
    
    messages.forEach(msg => {
        const isOut = msg.from === myLogin;
        const messageEl = document.createElement('div');
        messageEl.className = `message ${isOut ? 'out' : 'in'}`;
        messageEl.dataset.id = msg.id;
        
        let fileHtml = '';
        if (msg.file) {
            fileHtml = renderFile(msg.file);
        }
        
        const timeStr = formatMessageTime(msg.timestamp);
        const editedStr = msg.edited ? '<span class="message-edited">(ред.)</span>' : '';
        
        // Реакции
        let reactionsHtml = '';
        if (msg.reactions && msg.reactions.length > 0) {
            const reactionCounts = {};
            msg.reactions.forEach(r => {
                reactionCounts[r.emoji] = (reactionCounts[r.emoji] || 0) + 1;
            });
            reactionsHtml = `<div class="message-reactions">
                ${Object.entries(reactionCounts).map(([emoji, count]) => 
                    `<span class="reaction" onclick="addReaction('${msg.id}', '${emoji}')">${emoji}${count > 1 ? count : ''}</span>`
                ).join('')}
            </div>`;
        }
        
        // Кнопка реакции
        const reactionPickerHtml = `
            <div class="reaction-picker" id="reaction-${msg.id}">
                ${EMOJIS.slice(0, 6).map(e => `<span class="emoji-item" onclick="addReaction('${msg.id}', '${e}')">${e}</span>`).join('')}
            </div>
        `;
        
        messageEl.innerHTML = `
            <div class="message-bubble" oncontextmenu="showReactionPicker(event, '${msg.id}')">
                <div class="message-text">${escapeHtml(msg.text)}</div>
                ${fileHtml}
                <div class="message-time">${timeStr} ${editedStr}</div>
                ${reactionsHtml}
                ${reactionPickerHtml}
            </div>
        `;
        
        // Двойной клик для редактирования (свои сообщения)
        if (isOut) {
            messageEl.ondblclick = () => editMessage(msg);
        }
        
        container.appendChild(messageEl);
    });
    
    if (wasScrolled || messages.length > 0) {
        container.scrollTop = container.scrollHeight;
    }
}

// Отрисовка файла
function renderFile(file) {
    if (!file) return '';
    
    const mime = file.mime || '';
    const path = file.path || '';
    const name = escapeHtml(file.name || 'Файл');
    
    if (mime.startsWith('image/')) {
        return `<div class="message-file"><img src="${path}" alt="${name}" onclick="openImage('${path}')"></div>`;
    } else if (mime.startsWith('audio/')) {
        return `<div class="message-file"><audio controls src="${path}"></audio></div>`;
    } else if (mime.startsWith('video/')) {
        return `<div class="message-file"><video controls src="${path}"></video></div>`;
    } else {
        return `<div class="message-file"><a href="${path}" download>${name}</a> (${formatFileSize(file.size)})</div>`;
    }
}

// Форматирование времени сообщения
function formatMessageTime(timestamp) {
    const date = new Date(timestamp * 1000);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) return 'только что';
    if (diff < 3600) return `${Math.floor(diff / 60)} мин`;
    if (diff < 86400) return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    if (diff < 604800) return date.toLocaleDateString('ru-RU', { weekday: 'short' });
    return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
}

// Форматирование размера файла
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// Отправка сообщения
async function sendMessage() {
    const input = document.getElementById('message-input');
    const text = input.value.trim();
    
    if (!text && !selectedFile) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('recipient', currentRecipient);
        formData.append('text', text);
        formData.append('csrf_token', csrfToken);
        
        if (selectedFile) {
            formData.append('file', selectedFile);
            selectedFile = null;
        }
        
        const response = await fetch('chat.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            playSendSound();
            input.value = '';
            handleTyping(); // Сброс индикатора набора
            
            // Добавляем сообщение в кэш и перерисовываем
            if (!allMessagesCache[currentRecipient]) {
                allMessagesCache[currentRecipient] = [];
            }
            allMessagesCache[currentRecipient].push(result.message);
            renderMessages(allMessagesCache[currentRecipient]);
        } else {
            showNotification(result.error || 'Ошибка отправки', 'error');
            vibrate();
        }
    } catch (error) {
        showNotification('Ошибка отправки: ' + error.message, 'error');
        vibrate();
    }
}

// Обработка клавиши Enter
function handleInputKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

// Индикатор набора текста
let lastTypingState = false;

function handleTyping() {
    if (!currentRecipient) return;
    
    const input = document.getElementById('message-input');
    const isTyping = input.value.length > 0;
    
    if (isTyping !== lastTypingState) {
        lastTypingState = isTyping;
        
        clearTimeout(typingTimeout);
        
        api({
            action: 'typing',
            recipient: currentRecipient,
            is_typing: isTyping
        }).catch(() => {});
        
        if (isTyping) {
            typingTimeout = setTimeout(() => {
                lastTypingState = false;
                api({
                    action: 'typing',
                    recipient: currentRecipient,
                    is_typing: false
                }).catch(() => {});
            }, 2000);
        }
    }
}

// Выделение файла
let selectedFile = null;

function handleFileSelect(e) {
    const file = e.target.files[0];
    if (file) {
        selectedFile = file;
        showNotification(`Файл выбран: ${file.name}`);
    }
    e.target.value = '';
}

// Эмодзи панель
function initEmojiPanel() {
    const panel = document.getElementById('emoji-panel');
    panel.innerHTML = EMOJIS.map(e => 
        `<span class="emoji-item" onclick="insertEmoji('${e}')">${e}</span>`
    ).join('');
}

function toggleEmojiPanel() {
    const panel = document.getElementById('emoji-panel');
    panel.classList.toggle('show');
}

function insertEmoji(emoji) {
    const input = document.getElementById('message-input');
    input.value += emoji;
    input.focus();
    document.getElementById('emoji-panel').classList.remove('show');
}

// Реакции
async function addReaction(messageId, emoji) {
    if (!currentRecipient) return;
    
    try {
        const chatFile = `conversations/${[myLogin, currentRecipient].sort().join('_')}.json`;
        
        const result = await api({
            action: 'reaction',
            chat_file: chatFile,
            message_id: messageId,
            emoji: emoji,
            csrf_token: csrfToken
        });
        
        if (result.success) {
            loadHistory(currentRecipient);
        }
    } catch (error) {
        console.error('Error adding reaction:', error);
    }
}

function showReactionPicker(e, messageId) {
    e.preventDefault();
    
    // Закрываем другие пикеры
    document.querySelectorAll('.reaction-picker').forEach(p => p.classList.remove('show'));
    
    const picker = document.getElementById(`reaction-${messageId}`);
    if (picker) {
        picker.classList.toggle('show');
    }
}

// Закрытие пикеров реакций при клике вне
document.addEventListener('click', (e) => {
    if (!e.target.closest('.reaction-picker') && !e.target.closest('.message-bubble')) {
        document.querySelectorAll('.reaction-picker').forEach(p => p.classList.remove('show'));
    }
});

// Редактирование сообщения
async function editMessage(msg) {
    const newText = prompt('Редактировать сообщение:', msg.text);
    if (newText === null || newText === msg.text) return;
    
    try {
        const chatFile = `conversations/${[myLogin, currentRecipient].sort().join('_')}.json`;
        
        const result = await api({
            action: 'edit_msg',
            chat_file: chatFile,
            message_id: msg.id,
            text: newText,
            csrf_token: csrfToken
        });
        
        if (result.success) {
            loadHistory(currentRecipient);
        } else {
            showNotification(result.error, 'error');
        }
    } catch (error) {
        showNotification('Ошибка редактирования', 'error');
    }
}

// Удаление сообщения (по правому клику с Ctrl)
document.addEventListener('contextmenu', async (e) => {
    const messageEl = e.target.closest('.message');
    if (messageEl && e.ctrlKey) {
        e.preventDefault();
        
        const messageId = messageEl.dataset.id;
        if (!confirm('Удалить сообщение?')) return;
        
        try {
            const chatFile = `conversations/${[myLogin, currentRecipient].sort().join('_')}.json`;
            
            const result = await api({
                action: 'delete_msg',
                chat_file: chatFile,
                message_id: messageId,
                csrf_token: csrfToken
            });
            
            if (result.success) {
                loadHistory(currentRecipient);
            } else {
                showNotification(result.error, 'error');
            }
        } catch (error) {
            showNotification('Ошибка удаления', 'error');
        }
    }
});

// Фильтрация контактов
function filterContacts(query) {
    renderContactList(query);
}

// Обновление онлайн статусов
function updateOnlineStatuses() {
    // Перезагружаем контакты для обновления статусов
    if (currentRecipient) {
        loadRecipients();
    }
}

// Toggle темы
function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    
    const btn = document.querySelector('.theme-toggle');
    btn.textContent = isDark ? '☀️' : '🌙';
}

// Toggle контактов на мобильных
function toggleContacts() {
    document.getElementById('contacts-panel').classList.toggle('show');
}

// Открытие изображения
function openImage(src) {
    const win = window.open();
    win.document.write(`<img src="${src}" style="max-width:100%;">`);
}

// Загрузка аватарки
document.getElementById('avatar-input').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'upload_avatar');
        formData.append('avatar', file);
        formData.append('csrf_token', csrfToken);
        
        const response = await fetch('chat.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Обновляем аватарку
            const avatarImg = document.getElementById('my-avatar');
            avatarImg.src = result.url;
            avatarImg.style.display = 'block';
            
            // Перезагружаем список контактов и историю
            await loadRecipients();
            if (currentRecipient) {
                await loadHistory(currentRecipient);
            }
            
            showNotification('Аватарка обновлена!');
        } else {
            showNotification(result.error, 'error');
        }
    } catch (error) {
        showNotification('Ошибка загрузки: ' + error.message, 'error');
    }
    
    e.target.value = '';
});

// Экранирование HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', checkAuth);

// Очистка при выгрузке
window.addEventListener('beforeunload', () => {
    stopIntervals();
});
