<?php
/**
 * Админ-панель мессенджера
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

initSession();

// Проверка прав доступа
if (!isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$currentUserRole = getCurrentUserRole();
if (!in_array($currentUserRole, [ROLE_ADMIN, ROLE_CREATOR])) {
    die('Доступ запрещён');
}

// Обработка действий
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Неверный CSRF токен';
    } else {
        switch ($action) {
            case 'ban':
                $result = banUser($_POST['target_login'] ?? '', true);
                $message = $result['success'] ? 'Пользователь заблокирован' : $result['error'];
                $error = $result['success'] ? '' : $result['error'];
                break;
                
            case 'unban':
                $result = banUser($_POST['target_login'] ?? '', false);
                $message = $result['success'] ? 'Пользователь разблокирован' : $result['error'];
                $error = $result['success'] ? '' : $result['error'];
                break;
                
            case 'mute':
                $result = muteUser($_POST['target_login'] ?? '', true);
                $message = $result['success'] ? 'Пользователь заглушен' : $result['error'];
                $error = $result['success'] ? '' : $result['error'];
                break;
                
            case 'unmute':
                $result = muteUser($_POST['target_login'] ?? '', false);
                $message = $result['success'] ? 'Пользователь размучен' : $result['error'];
                $error = $result['success'] ? '' : $result['error'];
                break;
                
            case 'make_admin':
                $result = changeUserRole($_POST['target_login'] ?? '', ROLE_ADMIN);
                $message = $result['success'] ? 'Пользователь назначен админом' : $result['error'];
                $error = $result['success'] ? '' : $result['error'];
                break;
                
            case 'remove_admin':
                $result = changeUserRole($_POST['target_login'] ?? '', ROLE_USER);
                $message = $result['success'] ? 'Пользователь снят с должности админа' : $result['error'];
                $error = $result['success'] ? '' : $result['error'];
                break;
                
            case 'delete_user':
                $result = deleteUser($_POST['target_login'] ?? '');
                $message = $result['success'] ? 'Пользователь удалён' : $result['error'];
                $error = $result['success'] ? '' : $result['error'];
                break;
                
            case 'clear_cache':
                $result = clearCache();
                $message = $result['success'] ? 'Кеш очищен' : $result['error'];
                $error = $result['success'] ? '' : $result['error'];
                break;
                
            case 'clear_log':
                $logFile = LOGS_DIR . '/error.log';
                if (file_exists($logFile)) {
                    file_put_contents($logFile, '');
                    $message = 'Лог очищен';
                }
                break;
                
            case 'delete_chat':
                $chatFile = $_POST['chat_file'] ?? '';
                $fullPath = CONVERSATIONS_DIR . '/' . basename($chatFile);
                if (file_exists($fullPath) && strpos($chatFile, '/') === false) {
                    unlink($fullPath);
                    $message = 'Чат удалён';
                }
                break;
                
            case 'clear_chat':
                $chatFile = $_POST['chat_file'] ?? '';
                $fullPath = CONVERSATIONS_DIR . '/' . basename($chatFile);
                if (file_exists($fullPath) && strpos($chatFile, '/') === false) {
                    file_put_contents($fullPath, json_encode([], JSON_UNESCAPED_UNICODE));
                    $message = 'Чат очищен';
                }
                break;
        }
    }
}

// Получение данных
$users = getUsersData();
$stats = getSystemStats();
$diagnostics = systemDiagnostics();

// Получение списка чатов
$chatFiles = glob(CONVERSATIONS_DIR . '/*.json');
$chats = [];
foreach ($chatFiles as $file) {
    $filename = basename($file);
    if ($filename === 'online_status.json' || $filename === 'typing_status.json') {
        continue;
    }
    
    $content = file_get_contents($file);
    $messages = json_decode($content, true);
    $msgCount = is_array($messages) ? count($messages) : 0;
    
    $chats[] = [
        'file' => $filename,
        'users' => str_replace('.json', '', $filename),
        'messages' => $msgCount
    ];
}

// Чтение лога
$logContent = '';
$logFile = LOGS_DIR . '/error.log';
if (file_exists($logFile)) {
    $lines = file($logFile);
    $logContent = implode('', array_slice($lines, -100));
}

// Статистика ошибок в логе
$errorStats = [
    'ERROR' => 0,
    'WARNING' => 0,
    'ACTION' => 0,
    'INFO' => 0
];
if ($logContent) {
    foreach ($errorStats as $level => &$count) {
        $count = substr_count($logContent, "[$level]");
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <style>
        :root {
            --bg-primary: #f0f2f5;
            --bg-secondary: #ffffff;
            --text-primary: #050505;
            --text-secondary: #65676b;
            --accent: #008069;
            --accent-hover: #006a57;
            --border: #ddd;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ffc107;
        }

        .dark {
            --bg-primary: #18191a;
            --bg-secondary: #242526;
            --text-primary: #e4e6eb;
            --text-secondary: #b0b3b8;
            --border: #3e4042;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            margin-bottom: 20px;
            color: var(--accent);
        }

        h2 {
            margin: 30px 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accent);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: var(--accent);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-secondary);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: var(--accent);
            color: white;
        }

        tr:hover {
            background: var(--bg-primary);
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.8;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: black;
        }

        .btn-secondary {
            background: var(--border);
            color: var(--text-primary);
        }

        .form-actions {
            display: inline-flex;
            gap: 5px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .log-viewer {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
        }

        .diagnostics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .diag-item {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
        }

        .diag-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .diag-ok {
            background: #d4edda;
            color: #155724;
        }

        .diag-error {
            background: #f8d7da;
            color: #721c24;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 20px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--accent);
            text-decoration: none;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: bold;
        }

        .badge-creator {
            background: #6f42c1;
            color: white;
        }

        .badge-admin {
            background: var(--accent);
            color: white;
        }

        .badge-user {
            background: var(--border);
            color: var(--text-primary);
        }

        .badge-banned {
            background: var(--danger);
            color: white;
        }

        .badge-muted {
            background: var(--warning);
            color: black;
        }
    </style>
    <script>
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body>
    <button class="theme-toggle" onclick="toggleTheme()">🌙</button>
    
    <div class="container">
        <a href="index.php" class="back-link">← Вернуться в чат</a>
        
        <h1>🛡️ Админ-панель</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Статистика -->
        <h2>📊 Статистика</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_users'] ?></div>
                <div class="stat-label">Пользователей</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['admins'] ?></div>
                <div class="stat-label">Админов</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['online'] ?></div>
                <div class="stat-label">Онлайн</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['banned'] ?></div>
                <div class="stat-label">Забанены</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['muted'] ?></div>
                <div class="stat-label">Замучены</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['chats'] ?></div>
                <div class="stat-label">Чатов</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['messages'] ?></div>
                <div class="stat-label">Сообщений</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['media_files'] ?></div>
                <div class="stat-label">Медиафайлов</div>
            </div>
        </div>
        
        <!-- Пользователи -->
        <h2>👥 Пользователи</h2>
        <table>
            <thead>
                <tr>
                    <th>Логин</th>
                    <th>Роль</th>
                    <th>Создан</th>
                    <th>Последний вход</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $login => $user): ?>
                <tr>
                    <td><?= htmlspecialchars($login) ?></td>
                    <td>
                        <span class="badge badge-<?= $user['role'] ?>"><?= htmlspecialchars($user['role']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($user['created_at']) ?></td>
                    <td><?= htmlspecialchars($user['last_seen']) ?></td>
                    <td>
                        <?php if ($user['banned']): ?>
                            <span class="badge badge-banned">Забанен</span>
                        <?php elseif ($user['muted']): ?>
                            <span class="badge badge-muted">Мут</span>
                        <?php else: ?>
                            <span class="badge badge-user">OK</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" class="form-actions" onsubmit="return confirm('Подтвердить действие?')">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                            <input type="hidden" name="target_login" value="<?= htmlspecialchars($login) ?>">
                            
                            <?php if ($user['role'] !== ROLE_CREATOR): ?>
                                <?php if ($user['role'] === ROLE_USER): ?>
                                    <button type="submit" name="action" value="make_admin" class="btn btn-success">Админ</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="remove_admin" class="btn btn-warning">Снять</button>
                                <?php endif; ?>
                                
                                <?php if (!$user['banned']): ?>
                                    <button type="submit" name="action" value="ban" class="btn btn-danger">Бан</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="unban" class="btn btn-success">Разбан</button>
                                <?php endif; ?>
                                
                                <?php if (!$user['muted']): ?>
                                    <button type="submit" name="action" value="mute" class="btn btn-warning">Мут</button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="unmute" class="btn btn-success">Размут</button>
                                <?php endif; ?>
                                
                                <?php if ($currentUserRole === ROLE_CREATOR): ?>
                                    <button type="submit" name="action" value="delete_user" class="btn btn-danger" onclick="return confirm('Удалить пользователя навсегда?')">✕</button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Чаты -->
        <h2>💬 Чаты</h2>
        <table>
            <thead>
                <tr>
                    <th>Файл</th>
                    <th>Участники</th>
                    <th>Сообщений</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($chats as $chat): ?>
                <tr>
                    <td><?= htmlspecialchars($chat['file']) ?></td>
                    <td><?= htmlspecialchars($chat['users']) ?></td>
                    <td><?= $chat['messages'] ?></td>
                    <td>
                        <form method="POST" class="form-actions" onsubmit="return confirm('Подтвердить действие?')">
                            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
                            <input type="hidden" name="chat_file" value="<?= htmlspecialchars($chat['file']) ?>">
                            <button type="submit" name="action" value="clear_chat" class="btn btn-warning">Очистить</button>
                            <button type="submit" name="action" value="delete_chat" class="btn btn-danger">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($chats)): ?>
                <tr>
                    <td colspan="4">Чатов пока нет</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Управление -->
        <h2>⚙️ Управление</h2>
        <form method="POST" onsubmit="return confirm('Вы уверены? Это удалит все чаты и файлы!')">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <button type="submit" name="action" value="clear_cache" class="btn btn-danger">Очистить кеш (чаты и файлы)</button>
        </form>
        
        <!-- Лог ошибок -->
        <h2>📋 Лог ошибок</h2>
        <div style="margin-bottom: 15px;">
            <strong>Статистика:</strong>
            ERROR: <?= $errorStats['ERROR'] ?> | 
            WARNING: <?= $errorStats['WARNING'] ?> | 
            ACTION: <?= $errorStats['ACTION'] ?> | 
            INFO: <?= $errorStats['INFO'] ?>
        </div>
        <div class="log-viewer"><?= htmlspecialchars($logContent) ?></div>
        <form method="POST" style="margin-top: 10px;" onsubmit="return confirm('Очистить лог?')">
            <input type="hidden" name="csrf_token" value="<?= getCsrfToken() ?>">
            <button type="submit" name="action" value="clear_log" class="btn btn-warning">Очистить лог</button>
        </form>
        
        <!-- Диагностика -->
        <h2>🔧 Диагностика системы</h2>
        <div class="diagnostics-grid">
            <div class="diag-item">
                <strong>PHP версия:</strong><br>
                <?= htmlspecialchars($diagnostics['php_version']) ?>
            </div>
            <div class="diag-item">
                <strong>GD библиотека:</strong><br>
                <span class="diag-status <?= $diagnostics['gd'] ? 'diag-ok' : 'diag-error' ?>">
                    <?= $diagnostics['gd'] ? 'Доступна' : 'Недоступна' ?>
                </span>
            </div>
            <?php foreach ($diagnostics['dirs'] as $name => $info): ?>
            <div class="diag-item">
                <strong>Папка <?= htmlspecialchars($name) ?>:</strong><br>
                <span class="diag-status <?= $info['exists'] && $info['writable'] ? 'diag-ok' : 'diag-error' ?>">
                    <?= $info['exists'] ? 'Существует' : 'Нет' ?>, 
                    <?= $info['writable'] ? 'Запись OK' : 'Нет записи' ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php foreach ($diagnostics['json'] as $name => $info): ?>
            <div class="diag-item">
                <strong>JSON <?= htmlspecialchars($name) ?>:</strong><br>
                <span class="diag-status <?= $info['valid'] ? 'diag-ok' : 'diag-error' ?>">
                    <?= $info['exists'] ? 'Существует' : 'Нет' ?>, 
                    <?= $info['valid'] ? 'Валиден' : 'Невалиден' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const isDark = html.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            
            const btn = document.querySelector('.theme-toggle');
            btn.textContent = isDark ? '☀️' : '🌙';
        }
    </script>
</body>
</html>
