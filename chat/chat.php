<?php
/**
 * API обработчик всех действий мессенджера
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Инициализация сессии
initSession();

// Получение действия
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Обработка действий
switch ($action) {
    case 'check_auth':
        checkAuth();
        break;
        
    case 'login':
        handleLogin();
        break;
        
    case 'register':
        handleRegister();
        break;
        
    case 'logout':
        handleLogout();
        break;
        
    case 'get_users':
        getUsers();
        break;
        
    case 'typing':
        handleTyping();
        break;
        
    case 'ping':
        handlePing();
        break;
        
    case 'send':
        handleSend();
        break;
        
    case 'edit_msg':
        handleEditMsg();
        break;
        
    case 'reaction':
        handleReaction();
        break;
        
    case 'delete_msg':
        handleDeleteMsg();
        break;
        
    case 'get_history':
        getHistory();
        break;
        
    case 'get_avatar':
        getAvatar();
        break;
        
    case 'upload_avatar':
        handleUploadAvatar();
        break;
        
    default:
        jsonResponse(['error' => 'Неизвестное действие'], 400);
}

/**
 * Проверка авторизации
 */
function checkAuth() {
    if (isAuthenticated()) {
        $login = getCurrentUser();
        $role = getCurrentUserRole();
        jsonResponse([
            'authenticated' => true,
            'login' => $login,
            'role' => $role,
            'csrf_token' => getCsrfToken()
        ]);
    } else {
        jsonResponse(['authenticated' => false]);
    }
}

/**
 * Вход пользователя
 */
function handleLogin() {
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        jsonResponse(['error' => 'Введите логин и пароль'], 400);
    }
    
    $result = loginUser($login, $password);
    
    if ($result['success']) {
        setOnlineStatus($login, true);
        jsonResponse([
            'success' => true,
            'login' => $login,
            'csrf_token' => getCsrfToken()
        ]);
    } else {
        jsonResponse($result, 401);
    }
}

/**
 * Регистрация пользователя
 */
function handleRegister() {
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if (empty($login) || empty($password)) {
        jsonResponse(['error' => 'Введите логин и пароль'], 400);
    }
    
    $result = registerUser($login, $password, $email);
    
    if ($result['success']) {
        $_SESSION['user_login'] = $login;
        setOnlineStatus($login, true);
        jsonResponse([
            'success' => true,
            'login' => $login,
            'csrf_token' => getCsrfToken()
        ]);
    } else {
        jsonResponse($result, 400);
    }
}

/**
 * Выход пользователя
 */
function handleLogout() {
    logoutUser();
    jsonResponse(['success' => true]);
}

/**
 * Получение списка пользователей
 */
function getUsers() {
    requireAuth();
    $users = getAllUsers();
    jsonResponse(['users' => $users]);
}

/**
 * Индикатор набора текста
 */
function handleTyping() {
    requireAuth();
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Неверный CSRF токен'], 403);
    }
    
    $recipient = $_POST['recipient'] ?? '';
    $isTyping = isset($_POST['is_typing']) && $_POST['is_typing'] === 'true';
    
    if (empty($recipient)) {
        jsonResponse(['error' => 'Не указан собеседник'], 400);
    }
    
    updateTypingStatus(getCurrentUser(), $recipient, $isTyping);
    jsonResponse(['success' => true]);
}

/**
 * Поддержание онлайн статуса
 */
function handlePing() {
    $login = getCurrentUser();
    if ($login) {
        setOnlineStatus($login, true);
    }
    jsonResponse(['success' => true]);
}

/**
 * Отправка сообщения
 */
function handleSend() {
    requireAuth();
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Неверный CSRF токен'], 403);
    }
    
    $recipient = $_POST['recipient'] ?? '';
    $text = trim($_POST['text'] ?? '');
    
    if (empty($recipient)) {
        jsonResponse(['error' => 'Не указан собеседник'], 400);
    }
    
    // Обработка файла если есть
    $fileData = null;
    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $validation = validateFile($_FILES['file']);
        if (!$validation['success']) {
            jsonResponse($validation, 400);
        }
        
        $saveResult = saveUploadedFile($_FILES['file'], $validation);
        if (!$saveResult['success']) {
            jsonResponse($saveResult, 500);
        }
        
        $fileData = $saveResult;
    }
    
    // Если нет ни текста ни файла - ошибка
    if (empty($text) && !$fileData) {
        jsonResponse(['error' => 'Пустое сообщение'], 400);
    }
    
    $result = sendMessage(getCurrentUser(), $recipient, $text, $fileData);
    
    if ($result['success']) {
        jsonResponse($result);
    } else {
        jsonResponse($result, 400);
    }
}

/**
 * Редактирование сообщения
 */
function handleEditMsg() {
    requireAuth();
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Неверный CSRF токен'], 403);
    }
    
    $chatFile = $_POST['chat_file'] ?? '';
    $messageId = $_POST['message_id'] ?? '';
    $newText = $_POST['text'] ?? '';
    
    if (empty($chatFile) || empty($messageId)) {
        jsonResponse(['error' => 'Некорректные данные'], 400);
    }
    
    $fullPath = CHAT_DIR . '/' . $chatFile;
    $result = editMessage($fullPath, $messageId, $newText, getCurrentUser());
    
    jsonResponse($result);
}

/**
 * Добавление реакции
 */
function handleReaction() {
    requireAuth();
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Неверный CSRF токен'], 403);
    }
    
    $chatFile = $_POST['chat_file'] ?? '';
    $messageId = $_POST['message_id'] ?? '';
    $emoji = $_POST['emoji'] ?? '';
    
    if (empty($chatFile) || empty($messageId) || empty($emoji)) {
        jsonResponse(['error' => 'Некорректные данные'], 400);
    }
    
    $fullPath = CHAT_DIR . '/' . $chatFile;
    $result = addReaction($fullPath, $messageId, getCurrentUser(), $emoji);
    
    jsonResponse($result);
}

/**
 * Удаление сообщения
 */
function handleDeleteMsg() {
    requireAuth();
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Неверный CSRF токен'], 403);
    }
    
    $chatFile = $_POST['chat_file'] ?? '';
    $messageId = $_POST['message_id'] ?? '';
    
    if (empty($chatFile) || empty($messageId)) {
        jsonResponse(['error' => 'Некорректные данные'], 400);
    }
    
    $fullPath = CHAT_DIR . '/' . $chatFile;
    $result = deleteMessage($fullPath, $messageId, getCurrentUser());
    
    jsonResponse($result);
}

/**
 * Получение истории сообщений
 */
function getHistory() {
    requireAuth();
    
    $recipient = $_GET['recipient'] ?? '';
    $limit = intval($_GET['limit'] ?? 50);
    
    if (empty($recipient)) {
        jsonResponse(['error' => 'Не указан собеседник'], 400);
    }
    
    $messages = getMessages(getCurrentUser(), $recipient, $limit);
    $typingStatus = getTypingStatus(getCurrentUser(), $recipient);
    
    jsonResponse([
        'messages' => $messages,
        'typing_user' => $typingStatus ? $typingStatus['typing_user'] : null
    ]);
}

/**
 * Получение аватарки
 */
function getAvatar() {
    $login = $_GET['login'] ?? '';
    
    if (empty($login)) {
        jsonResponse(['error' => 'Не указан пользователь'], 400);
    }
    
    $url = getAvatarUrl($login);
    jsonResponse(['url' => $url]);
}

/**
 * Загрузка аватарки
 */
function handleUploadAvatar() {
    requireAuth();
    
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Неверный CSRF токен'], 403);
    }
    
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['error' => 'Ошибка загрузки файла'], 400);
    }
    
    $file = $_FILES['avatar'];
    
    // Проверка размера
    if ($file['size'] > MAX_AVATAR_SIZE) {
        jsonResponse(['error' => 'Файл слишком большой (макс. ' . (MAX_AVATAR_SIZE / 1024 / 1024) . ' MB)'], 400);
    }
    
    $login = getCurrentUser();
    $result = saveAvatar($login, $file['tmp_name']);
    
    if ($result['success']) {
        jsonResponse([
            'success' => true,
            'url' => getAvatarUrl($login)
        ]);
    } else {
        jsonResponse($result, 400);
    }
}
