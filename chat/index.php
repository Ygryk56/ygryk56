<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мессенджер</title>
    <style>
        :root {
            --bg-primary: #f0f2f5;
            --bg-secondary: #ffffff;
            --text-primary: #050505;
            --text-secondary: #65676b;
            --accent: #008069;
            --accent-hover: #006a57;
            --border: #ddd;
            --shadow: rgba(0, 0, 0, 0.1);
            --message-out: #008069;
            --message-in: #ffffff;
            --online: #31a24c;
            --danger: #dc3545;
        }

        .dark {
            --bg-primary: #18191a;
            --bg-secondary: #242526;
            --text-primary: #e4e6eb;
            --text-secondary: #b0b3b8;
            --accent: #00a88a;
            --accent-hover: #008f75;
            --border: #3e4042;
            --shadow: rgba(0, 0, 0, 0.3);
            --message-out: #00a88a;
            --message-in: #242526;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            height: 100vh;
            overflow: hidden;
            transition: background 0.3s, color 0.3s;
        }

        /* Экран авторизации */
        #auth-screen {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: linear-gradient(135deg, var(--accent), #005f4e);
        }

        .auth-container {
            background: var(--bg-secondary);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 32px var(--shadow);
            width: 100%;
            max-width: 400px;
        }

        .auth-container h1 {
            text-align: center;
            margin-bottom: 30px;
            color: var(--accent);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 16px;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn:hover {
            background: var(--accent-hover);
        }

        .btn-secondary {
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
            margin-top: 10px;
        }

        .btn-secondary:hover {
            background: var(--accent);
            color: white;
        }

        .error-message {
            color: var(--danger);
            text-align: center;
            margin-bottom: 15px;
        }

        /* Экран чата */
        #chat-screen {
            display: none;
            height: 100vh;
        }

        .chat-layout {
            display: flex;
            height: 100%;
        }

        /* Панель контактов */
        #contacts-panel {
            width: 320px;
            background: var(--bg-secondary);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }

        .contacts-header {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-top: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .contacts-list {
            flex: 1;
            overflow-y: auto;
        }

        .contact-item {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid var(--border);
        }

        .contact-item:hover {
            background: var(--bg-primary);
        }

        .contact-item.active {
            background: rgba(0, 128, 105, 0.1);
        }

        .contact-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 12px;
        }

        .contact-avatar-placeholder {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--accent);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 12px;
        }

        .contact-info {
            flex: 1;
            min-width: 0;
        }

        .contact-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .contact-status {
            font-size: 13px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
        }

        .online-indicator {
            width: 8px;
            height: 8px;
            background: var(--online);
            border-radius: 50%;
            margin-right: 6px;
        }

        .unread-badge {
            background: var(--accent);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 12px;
        }

        /* Основная область чата */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-primary);
        }

        .chat-header {
            padding: 16px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            cursor: pointer;
        }

        .theme-toggle {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
        }

        .logout-btn {
            background: none;
            border: 1px solid var(--border);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            color: var(--text-primary);
        }

        /* Область сообщений */
        .messages-container {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .message {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.out {
            justify-content: flex-end;
        }

        .message.in {
            justify-content: flex-start;
        }

        .message-bubble {
            max-width: 60%;
            padding: 10px 14px;
            border-radius: 18px;
            position: relative;
        }

        .message.out .message-bubble {
            background: var(--message-out);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.in .message-bubble {
            background: var(--message-in);
            color: var(--text-primary);
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 2px var(--shadow);
        }

        .message-text {
            word-wrap: break-word;
        }

        .message-file {
            margin-top: 8px;
        }

        .message-file img {
            max-width: 100%;
            border-radius: 8px;
            cursor: pointer;
        }

        .message-file audio,
        .message-file video {
            max-width: 100%;
        }

        .message-file a {
            color: inherit;
            text-decoration: underline;
        }

        .message-time {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 4px;
            text-align: right;
        }

        .message-edited {
            font-size: 10px;
            opacity: 0.6;
        }

        .message-reactions {
            display: flex;
            gap: 4px;
            margin-top: 6px;
            flex-wrap: wrap;
        }

        .reaction {
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 12px;
            cursor: pointer;
        }

        .reaction-picker {
            position: absolute;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 4px 8px;
            display: none;
            gap: 4px;
            box-shadow: 0 2px 8px var(--shadow);
        }

        .reaction-picker.show {
            display: flex;
        }

        .typing-indicator {
            padding: 10px 20px;
            color: var(--text-secondary);
            font-style: italic;
            font-size: 14px;
        }

        /* Панель ввода */
        .input-panel {
            padding: 16px;
            background: var(--bg-secondary);
            border-top: 1px solid var(--border);
        }

        .input-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 15px;
            resize: none;
            max-height: 120px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        .send-btn, .attach-btn, .emoji-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 8px;
            color: var(--accent);
        }

        .send-btn {
            background: var(--accent);
            color: white;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .send-btn:hover {
            background: var(--accent-hover);
        }

        /* Эмодзи панель */
        .emoji-panel {
            position: absolute;
            bottom: 80px;
            right: 20px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px;
            display: none;
            grid-template-columns: repeat(8, 1fr);
            gap: 4px;
            box-shadow: 0 4px 12px var(--shadow);
            z-index: 100;
        }

        .emoji-panel.show {
            display: grid;
        }

        .emoji-item {
            font-size: 24px;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            text-align: center;
        }

        .emoji-item:hover {
            background: var(--bg-primary);
        }

        /* Админ кнопка */
        .admin-link {
            display: none;
            padding: 8px 16px;
            background: var(--accent);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
        }

        /* Адаптивность */
        @media (max-width: 768px) {
            #contacts-panel {
                position: fixed;
                left: 0;
                top: 0;
                height: 100%;
                z-index: 100;
                transform: translateX(-100%);
            }

            #contacts-panel.show {
                transform: translateX(0);
            }

            .menu-toggle {
                display: block;
                background: none;
                border: none;
                font-size: 24px;
                cursor: pointer;
                margin-right: 10px;
            }
        }

        @media (min-width: 769px) {
            .menu-toggle {
                display: none;
            }
        }

        /* Скрытый файл инпут */
        #file-input {
            display: none;
        }

        /* Уведомления */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: var(--bg-secondary);
            border-radius: 8px;
            box-shadow: 0 4px 12px var(--shadow);
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }

        .notification.error {
            border-left: 4px solid var(--danger);
        }

        .notification.success {
            border-left: 4px solid var(--online);
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
    <script>
        // Мгновенное применение темы
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body>
    <!-- Экран авторизации -->
    <div id="auth-screen">
        <div class="auth-container">
            <h1>💬 Мессенджер</h1>
            <div id="auth-error" class="error-message"></div>
            
            <form id="login-form">
                <div class="form-group">
                    <label for="login-username">Логин</label>
                    <input type="text" id="login-username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="login-password">Пароль</label>
                    <input type="password" id="login-password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn">Войти</button>
                <button type="button" class="btn btn-secondary" onclick="showRegister()">Регистрация</button>
            </form>
            
            <form id="register-form" style="display:none;">
                <div class="form-group">
                    <label for="register-username">Логин</label>
                    <input type="text" id="register-username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="register-password">Пароль</label>
                    <input type="password" id="register-password" required autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="register-email">Email (необязательно)</label>
                    <input type="email" id="register-email" autocomplete="email">
                </div>
                <button type="submit" class="btn">Зарегистрироваться</button>
                <button type="button" class="btn btn-secondary" onclick="showLogin()">Вход</button>
            </form>
        </div>
    </div>

    <!-- Экран чата -->
    <div id="chat-screen">
        <div class="chat-layout">
            <!-- Панель контактов -->
            <div id="contacts-panel">
                <div class="contacts-header">
                    <div style="display:flex;align-items:center;">
                        <button class="menu-toggle" onclick="toggleContacts()">☰</button>
                        <h2>Контакты</h2>
                    </div>
                    <button class="theme-toggle" onclick="toggleTheme()" title="Сменить тему">🌙</button>
                </div>
                <div style="padding:16px;">
                    <input type="text" class="search-input" placeholder="Поиск..." oninput="filterContacts(this.value)">
                </div>
                <div class="contacts-list" id="contacts-list"></div>
            </div>

            <!-- Основная область -->
            <div class="chat-main">
                <div class="chat-header">
                    <div style="display:flex;align-items:center;">
                        <button class="menu-toggle" onclick="toggleContacts()">☰</button>
                        <span id="current-chat-name">Выберите контакт</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:15px;">
                        <span class="header-user" id="user-display">
                            <img id="my-avatar" src="" style="display:none;width:40px;height:40px;border-radius:50%;object-fit:cover;cursor:pointer;" title="Сменить аватар">
                            <span id="my-username">User</span>
                            <label for="avatar-input" title="Сменить аватар" style="cursor:pointer;font-size:18px;">📷</label>
                            <input type="file" id="avatar-input" accept="image/*" style="display:none">
                        </span>
                        <a href="admin.php" class="admin-link" id="admin-link">Админка</a>
                        <button class="logout-btn" onclick="logout()">Выход</button>
                    </div>
                </div>

                <div class="messages-container" id="messages-container"></div>
                
                <div class="typing-indicator" id="typing-indicator" style="display:none;"></div>

                <div class="input-panel">
                    <div class="input-row">
                        <button class="attach-btn" onclick="document.getElementById('file-input').click()" title="Прикрепить файл">📎</button>
                        <input type="file" id="file-input" onchange="handleFileSelect(event)">
                        <button class="emoji-btn" onclick="toggleEmojiPanel()" title="Эмодзи">😊</button>
                        <textarea class="message-input" id="message-input" placeholder="Введите сообщение..." rows="1" onkeydown="handleInputKeydown(event)" oninput="handleTyping()"></textarea>
                        <button class="send-btn" onclick="sendMessage()" title="Отправить">➤</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Эмодзи панель -->
    <div class="emoji-panel" id="emoji-panel"></div>

    <!-- Контейнер уведомлений -->
    <div id="notifications-container"></div>

    <script src="js/script.js" defer></script>
</body>
</html>
