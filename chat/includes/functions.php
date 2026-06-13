<?php
/**
 * Основные функции мессенджера
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/logger.php';

/**
 * Инициализация сессии
 */
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_name(SESSION_NAME);
        session_start();
        
        // Генерация CSRF токена если отсутствует
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

/**
 * Получение CSRF токена
 */
function getCsrfToken() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Проверка CSRF токена
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Проверка авторизации
 */
function requireAuth() {
    if (!isAuthenticated()) {
        jsonResponse(['error' => 'Требуется авторизация'], 401);
        exit;
    }
}

/**
 * Проверка, авторизован ли пользователь
 */
function isAuthenticated() {
    return isset($_SESSION['user_login']);
}

/**
 * Получение текущего пользователя
 */
function getCurrentUser() {
    return $_SESSION['user_login'] ?? null;
}

/**
 * Получение роли текущего пользователя
 */
function getCurrentUserRole() {
    $login = getCurrentUser();
    if (!$login) return null;
    
    $users = getUsersData();
    return $users[$login]['role'] ?? null;
}

/**
 * Проверка роли
 */
function requireRole($roles) {
    $role = getCurrentUserRole();
    $roles = is_array($roles) ? $roles : [$roles];
    
    if (!in_array($role, $roles)) {
        jsonResponse(['error' => 'Недостаточно прав'], 403);
        exit;
    }
}

/**
 * Загрузка данных пользователей
 */
function getUsersData() {
    $file = DATA_DIR . '/users.json';
    
    if (!file_exists($file)) {
        return [];
    }
    
    $fp = fopen($file, 'r');
    if ($fp) {
        flock($fp, LOCK_SH);
        $content = fread($fp, filesize($file));
        flock($fp, LOCK_UN);
        fclose($fp);
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }
    
    return [];
}

/**
 * Сохранение данных пользователей
 */
function saveUsersData($users) {
    $file = DATA_DIR . '/users.json';
    $fp = fopen($file, 'w');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/**
 * Регистрация пользователя
 */
function registerUser($login, $password, $email = '') {
    $login = trim($login);
    $email = trim($email);
    
    if (strlen($login) < 3 || strlen($login) > 32) {
        return ['success' => false, 'error' => 'Логин должен быть от 3 до 32 символов'];
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $login)) {
        return ['success' => false, 'error' => 'Логин может содержать только буквы, цифры и подчёркивание'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'error' => 'Пароль должен быть не менее 6 символов'];
    }
    
    $users = getUsersData();
    
    if (isset($users[$login])) {
        return ['success' => false, 'error' => 'Пользователь уже существует'];
    }
    
    // Определение роли первого пользователя как creator
    $role = empty($users) ? ROLE_CREATOR : ROLE_USER;
    
    $users[$login] = [
        'login' => $login,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'email' => $email,
        'role' => $role,
        'banned' => false,
        'muted' => false,
        'created_at' => date('Y-m-d H:i:s'),
        'last_seen' => date('Y-m-d H:i:s')
    ];
    
    saveUsersData($users);
    logAction('Регистрация пользователя', "login: $login, role: $role");
    
    return ['success' => true];
}

/**
 * Вход пользователя
 */
function loginUser($login, $password) {
    $login = trim($login);
    $users = getUsersData();
    
    if (!isset($users[$login])) {
        return ['success' => false, 'error' => 'Неверный логин или пароль'];
    }
    
    $user = $users[$login];
    
    if ($user['banned']) {
        logAction('Попытка входа забаненного пользователя', "login: $login");
        return ['success' => false, 'error' => 'Аккаунт заблокирован'];
    }
    
    if (!password_verify($password, $user['password'])) {
        logAction('Неверный пароль при входе', "login: $login");
        return ['success' => false, 'error' => 'Неверный логин или пароль'];
    }
    
    $_SESSION['user_login'] = $login;
    $user['last_seen'] = date('Y-m-d H:i:s');
    $users[$login] = $user;
    saveUsersData($users);
    
    logAction('Вход пользователя', "login: $login");
    
    return ['success' => true];
}

/**
 * Выход пользователя
 */
function logoutUser() {
    $login = getCurrentUser();
    if ($login) {
        setOnlineStatus($login, false);
        logAction('Выход пользователя', "login: $login");
    }
    
    session_destroy();
    if (isset($_COOKIE[SESSION_NAME])) {
        setcookie(SESSION_NAME, '', time() - 3600, '/');
    }
}

/**
 * Получение онлайн статусов
 */
function getOnlineStatuses() {
    $file = CONVERSATIONS_DIR . '/online_status.json';
    
    if (!file_exists($file)) {
        return [];
    }
    
    $fp = fopen($file, 'r');
    if ($fp) {
        flock($fp, LOCK_SH);
        $content = fread($fp, filesize($file));
        flock($fp, LOCK_UN);
        fclose($fp);
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }
    
    return [];
}

/**
 * Установка онлайн статуса
 */
function setOnlineStatus($login, $online) {
    $file = CONVERSATIONS_DIR . '/online_status.json';
    
    $statuses = getOnlineStatuses();
    
    if ($online) {
        $statuses[$login] = time();
    } else {
        unset($statuses[$login]);
    }
    
    $fp = fopen($file, 'w');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, json_encode($statuses, JSON_UNESCAPED_UNICODE));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/**
 * Проверка, онлайн ли пользователь
 */
function isOnline($login, $timeout = 30) {
    $statuses = getOnlineStatuses();
    
    if (!isset($statuses[$login])) {
        return false;
    }
    
    return (time() - $statuses[$login]) < $timeout;
}

/**
 * Получение списка пользователей (кроме текущего)
 */
function getAllUsers($excludeLogin = null) {
    $excludeLogin = $excludeLogin ?? getCurrentUser();
    $users = getUsersData();
    $result = [];
    
    foreach ($users as $login => $user) {
        if ($login === $excludeLogin) continue;
        
        $result[] = [
            'login' => $login,
            'role' => $user['role'],
            'banned' => $user['banned'],
            'muted' => $user['muted'],
            'online' => isOnline($login),
            'avatar' => getAvatarUrl($login)
        ];
    }
    
    return $result;
}

/**
 * Получение файла чата для пары пользователей
 */
function getChatFile($user1, $user2) {
    $users = [$user1, $user2];
    sort($users);
    return CONVERSATIONS_DIR . '/' . $users[0] . '_' . $users[1] . '.json';
}

/**
 * Получение истории сообщений
 */
function getMessages($user1, $user2, $limit = 50) {
    $file = getChatFile($user1, $user2);
    
    if (!file_exists($file)) {
        return [];
    }
    
    $fp = fopen($file, 'r');
    if ($fp) {
        flock($fp, LOCK_SH);
        $content = fread($fp, filesize($file));
        flock($fp, LOCK_UN);
        fclose($fp);
        
        $messages = json_decode($content, true);
        if (is_array($messages)) {
            return array_slice($messages, -$limit);
        }
    }
    
    return [];
}

/**
 * Отправка сообщения
 */
function sendMessage($from, $to, $text, $fileData = null) {
    $users = getUsersData();
    
    if (!isset($users[$from])) {
        return ['success' => false, 'error' => 'Пользователь не найден'];
    }
    
    if ($users[$from]['muted'] && $users[$from]['role'] !== ROLE_CREATOR) {
        return ['success' => false, 'error' => 'Вы не можете отправлять сообщения (мут)'];
    }
    
    $file = getChatFile($from, $to);
    
    $messages = [];
    if (file_exists($file)) {
        $fp = fopen($file, 'r');
        if ($fp) {
            flock($fp, LOCK_SH);
            $content = fread($fp, filesize($file));
            flock($fp, LOCK_UN);
            fclose($fp);
            $messages = json_decode($content, true);
            if (!is_array($messages)) $messages = [];
        }
    }
    
    $message = [
        'id' => uniqid(),
        'from' => $from,
        'to' => $to,
        'text' => $text,
        'file' => $fileData,
        'timestamp' => time(),
        'edited' => false,
        'reactions' => []
    ];
    
    $messages[] = $message;
    
    // Ограничение размера файла сообщений (макс 1000 сообщений)
    if (count($messages) > 1000) {
        $messages = array_slice($messages, -1000);
    }
    
    $fp = fopen($file, 'w');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, json_encode($messages, JSON_UNESCAPED_UNICODE));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    
    logAction('Отправка сообщения', "from: $from, to: $to");
    
    return ['success' => true, 'message' => $message];
}

/**
 * Редактирование сообщения
 */
function editMessage($chatFile, $messageId, $newText, $userLogin) {
    if (!file_exists($chatFile)) {
        return ['success' => false, 'error' => 'Чат не найден'];
    }
    
    $fp = fopen($chatFile, 'r+');
    if ($fp) {
        flock($fp, LOCK_EX);
        $content = fread($fp, filesize($chatFile));
        $messages = json_decode($content, true);
        
        if (is_array($messages)) {
            foreach ($messages as &$msg) {
                if ($msg['id'] === $messageId && $msg['from'] === $userLogin) {
                    $msg['text'] = $newText;
                    $msg['edited'] = true;
                    $msg['edited_at'] = time();
                    
                    fseek($fp, 0);
                    ftruncate($fp, 0);
                    fwrite($fp, json_encode($messages, JSON_UNESCAPED_UNICODE));
                    
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    
                    return ['success' => true];
                }
            }
        }
        
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    
    return ['success' => false, 'error' => 'Сообщение не найдено или нет прав'];
}

/**
 * Удаление сообщения
 */
function deleteMessage($chatFile, $messageId, $userLogin) {
    $users = getUsersData();
    $userRole = $users[$userLogin]['role'] ?? null;
    
    if (!file_exists($chatFile)) {
        return ['success' => false, 'error' => 'Чат не найден'];
    }
    
    $fp = fopen($chatFile, 'r+');
    if ($fp) {
        flock($fp, LOCK_EX);
        $content = fread($fp, filesize($chatFile));
        $messages = json_decode($content, true);
        
        if (is_array($messages)) {
            foreach ($messages as $key => $msg) {
                if ($msg['id'] === $messageId) {
                    // Проверка прав: автор или админ/creator
                    if ($msg['from'] !== $userLogin && !in_array($userRole, [ROLE_ADMIN, ROLE_CREATOR])) {
                        flock($fp, LOCK_UN);
                        fclose($fp);
                        return ['success' => false, 'error' => 'Нет прав на удаление'];
                    }
                    
                    // Удаление файла если есть
                    if (!empty($msg['file']['path'])) {
                        $filePath = CHAT_DIR . '/' . $msg['file']['path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                    }
                    
                    unset($messages[$key]);
                    $messages = array_values($messages);
                    
                    fseek($fp, 0);
                    ftruncate($fp, 0);
                    fwrite($fp, json_encode($messages, JSON_UNESCAPED_UNICODE));
                    
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    
                    return ['success' => true];
                }
            }
        }
        
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    
    return ['success' => false, 'error' => 'Сообщение не найдено'];
}

/**
 * Добавление реакции
 */
function addReaction($chatFile, $messageId, $userLogin, $emoji) {
    if (!file_exists($chatFile)) {
        return ['success' => false, 'error' => 'Чат не найден'];
    }
    
    $allowedEmojis = ['👍', '❤️', '😂', '😮', '😢', '😡'];
    if (!in_array($emoji, $allowedEmojis)) {
        return ['success' => false, 'error' => 'Недопустимая реакция'];
    }
    
    $fp = fopen($chatFile, 'r+');
    if ($fp) {
        flock($fp, LOCK_EX);
        $content = fread($fp, filesize($chatFile));
        $messages = json_decode($content, true);
        
        if (is_array($messages)) {
            foreach ($messages as &$msg) {
                if ($msg['id'] === $messageId) {
                    if (!isset($msg['reactions'])) {
                        $msg['reactions'] = [];
                    }
                    
                    // Удаляем если уже есть такая реакция от этого пользователя
                    foreach ($msg['reactions'] as $i => $reaction) {
                        if ($reaction['user'] === $userLogin && $reaction['emoji'] === $emoji) {
                            unset($msg['reactions'][$i]);
                            $msg['reactions'] = array_values($msg['reactions']);
                            
                            fseek($fp, 0);
                            ftruncate($fp, 0);
                            fwrite($fp, json_encode($messages, JSON_UNESCAPED_UNICODE));
                            
                            flock($fp, LOCK_UN);
                            fclose($fp);
                            
                            return ['success' => true, 'action' => 'removed'];
                        }
                    }
                    
                    // Добавляем реакцию
                    $msg['reactions'][] = ['user' => $userLogin, 'emoji' => $emoji];
                    
                    fseek($fp, 0);
                    ftruncate($fp, 0);
                    fwrite($fp, json_encode($messages, JSON_UNESCAPED_UNICODE));
                    
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    
                    return ['success' => true, 'action' => 'added'];
                }
            }
        }
        
        flock($fp, LOCK_UN);
        fclose($fp);
    }
    
    return ['success' => false, 'error' => 'Сообщение не найдено'];
}

/**
 * Обновление индикатора набора текста
 */
function updateTypingStatus($user1, $user2, $isTyping) {
    $file = CONVERSATIONS_DIR . '/typing_status.json';
    
    $statuses = [];
    if (file_exists($file)) {
        $statuses = json_decode(file_get_contents($file), true) ?: [];
    }
    
    $key = min($user1, $user2) . '_' . max($user1, $user2);
    
    if ($isTyping) {
        $statuses[$key] = ['typing_user' => $user1, 'timestamp' => time()];
    } else {
        unset($statuses[$key]);
    }
    
    file_put_contents($file, json_encode($statuses, JSON_UNESCAPED_UNICODE));
}

/**
 * Получение статуса набора текста
 */
function getTypingStatus($user1, $user2) {
    $file = CONVERSATIONS_DIR . '/typing_status.json';
    
    if (!file_exists($file)) {
        return null;
    }
    
    $statuses = json_decode(file_get_contents($file), true);
    if (!is_array($statuses)) {
        return null;
    }
    
    $key = min($user1, $user2) . '_' . max($user1, $user2);
    $status = $statuses[$key] ?? null;
    
    if ($status && (time() - $status['timestamp']) > 5) {
        unset($statuses[$key]);
        file_put_contents($file, json_encode($statuses, JSON_UNESCAPED_UNICODE));
        return null;
    }
    
    return $status;
}

/**
 * Получение URL аватарки
 */
function getAvatarUrl($login) {
    $avatarFile = AVATARS_DIR . '/' . $login . '.jpg';
    $timestamp = file_exists($avatarFile) ? filemtime($avatarFile) : time();
    return 'uploads/avatars/' . $login . '.jpg?' . $timestamp;
}

/**
 * Сохранение аватарки
 */
function saveAvatar($login, $tmpPath) {
    // Проверка MIME типа
    $imageInfo = getimagesize($tmpPath);
    if (!$imageInfo) {
        return ['success' => false, 'error' => 'Некорректное изображение'];
    }
    
    // Разрешаем только изображения
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($imageInfo['mime'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Разрешены только JPEG, PNG и GIF'];
    }
    
    // Создание директории если нужно
    if (!is_dir(AVATARS_DIR)) {
        mkdir(AVATARS_DIR, 0755, true);
    }
    
    // Загрузка изображения
    $sourceImage = null;
    switch ($imageInfo['mime']) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($tmpPath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($tmpPath);
            break;
    }
    
    if (!$sourceImage) {
        return ['success' => false, 'error' => 'Ошибка обработки изображения'];
    }
    
    // Получение размеров
    $srcWidth = imagesx($sourceImage);
    $srcHeight = imagesy($sourceImage);
    
    // Вычисление квадрата по центру
    $minSize = min($srcWidth, $srcHeight);
    $srcX = intval(($srcWidth - $minSize) / 2);
    $srcY = intval(($srcHeight - $minSize) / 2);
    
    // Создание нового изображения
    $destImage = imagecreatetruecolor(AVATAR_SIZE, AVATAR_SIZE);
    
    // Обработка прозрачности для PNG и GIF
    if ($imageInfo['mime'] === 'image/png' || $imageInfo['mime'] === 'image/gif') {
        imagealphablending($destImage, false);
        imagesavealpha($destImage, true);
        $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
        imagefill($destImage, 0, 0, $transparent);
    }
    
    // Копирование с обрезкой до квадрата и масштабированием
    imagecopyresampled(
        $destImage, $sourceImage,
        0, 0, $srcX, $srcY,
        AVATAR_SIZE, AVATAR_SIZE,
        $minSize, $minSize
    );
    
    // Сохранение в JPEG
    $outputPath = AVATARS_DIR . '/' . $login . '.jpg';
    $result = imagejpeg($destImage, $outputPath, AVATAR_QUALITY);
    
    imagedestroy($sourceImage);
    imagedestroy($destImage);
    
    if (!$result) {
        return ['success' => false, 'error' => 'Ошибка сохранения аватарки'];
    }
    
    logAction('Загрузка аватарки', "login: $login");
    
    return ['success' => true];
}

/**
 * Проверка файла
 */
function validateFile($file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Ошибка загрузки файла'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Ошибка загрузки: код ' . $file['error']];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'Файл слишком большой (макс. ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB)'];
    }
    
    // Проверка расширения
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'Недопустимый тип файла'];
    }
    
    // Проверка MIME типа
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        return ['success' => false, 'error' => 'Недопустимый формат файла'];
    }
    
    return ['success' => true, 'mime' => $mimeType, 'extension' => $extension];
}

/**
 * Сохранение загруженного файла
 */
function saveUploadedFile($file, $validationResult) {
    $uploadDir = UPLOADS_DIR;
    $fileName = uniqid() . '_' . time() . '.' . $validationResult['extension'];
    $filePath = $uploadDir . '/' . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return [
            'success' => true,
            'path' => 'uploads/' . $fileName,
            'name' => $file['name'],
            'mime' => $validationResult['mime'],
            'size' => $file['size']
        ];
    }
    
    return ['success' => false, 'error' => 'Ошибка сохранения файла'];
}

/**
 * Форматирование времени
 */
function formatTime($timestamp) {
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'только что';
    } elseif ($diff < 3600) {
        return intval($diff / 60) . ' мин. назад';
    } elseif ($diff < 86400) {
        return intval($diff / 3600) . ' ч. назад';
    } elseif ($diff < 604800) {
        return intval($diff / 86400) . ' дн. назад';
    } else {
        return date('d.m.Y H:i', $timestamp);
    }
}

/**
 * JSON ответ
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Бан пользователя
 */
function banUser($targetLogin, $ban) {
    $users = getUsersData();
    $currentUserRole = getCurrentUserRole();
    
    if (!isset($users[$targetLogin])) {
        return ['success' => false, 'error' => 'Пользователь не найден'];
    }
    
    if ($users[$targetLogin]['role'] === ROLE_CREATOR) {
        return ['success' => false, 'error' => 'Нельзя изменить роль создателя'];
    }
    
    if (!in_array($currentUserRole, [ROLE_ADMIN, ROLE_CREATOR])) {
        return ['success' => false, 'error' => 'Недостаточно прав'];
    }
    
    $users[$targetLogin]['banned'] = $ban;
    saveUsersData($users);
    
    logAction($ban ? 'Бан пользователя' : 'Разбан пользователя', "target: $targetLogin");
    
    return ['success' => true];
}

/**
 * Мут пользователя
 */
function muteUser($targetLogin, $mute) {
    $users = getUsersData();
    $currentUserRole = getCurrentUserRole();
    
    if (!isset($users[$targetLogin])) {
        return ['success' => false, 'error' => 'Пользователь не найден'];
    }
    
    if ($users[$targetLogin]['role'] === ROLE_CREATOR) {
        return ['success' => false, 'error' => 'Нельзя изменить роль создателя'];
    }
    
    if (!in_array($currentUserRole, [ROLE_ADMIN, ROLE_CREATOR])) {
        return ['success' => false, 'error' => 'Недостаточно прав'];
    }
    
    $users[$targetLogin]['muted'] = $mute;
    saveUsersData($users);
    
    logAction($mute ? 'Мут пользователя' : 'Размут пользователя', "target: $targetLogin");
    
    return ['success' => true];
}

/**
 * Изменение роли пользователя
 */
function changeUserRole($targetLogin, $newRole) {
    $users = getUsersData();
    $currentUserRole = getCurrentUserRole();
    
    if (!isset($users[$targetLogin])) {
        return ['success' => false, 'error' => 'Пользователь не найден'];
    }
    
    if ($users[$targetLogin]['role'] === ROLE_CREATOR) {
        return ['success' => false, 'error' => 'Нельзя изменить роль создателя'];
    }
    
    if ($currentUserRole !== ROLE_CREATOR) {
        return ['success' => false, 'error' => 'Только создатель может менять роли'];
    }
    
    if (!in_array($newRole, [ROLE_ADMIN, ROLE_USER])) {
        return ['success' => false, 'error' => 'Недопустимая роль'];
    }
    
    $users[$targetLogin]['role'] = $newRole;
    saveUsersData($users);
    
    logAction('Изменение роли', "target: $targetLogin, new role: $newRole");
    
    return ['success' => true];
}

/**
 * Удаление пользователя
 */
function deleteUser($targetLogin) {
    $users = getUsersData();
    $currentUserRole = getCurrentUserRole();
    
    if (!isset($users[$targetLogin])) {
        return ['success' => false, 'error' => 'Пользователь не найден'];
    }
    
    if ($users[$targetLogin]['role'] === ROLE_CREATOR) {
        return ['success' => false, 'error' => 'Нельзя удалить создателя'];
    }
    
    if ($currentUserRole !== ROLE_CREATOR) {
        return ['success' => false, 'error' => 'Только создатель может удалять пользователей'];
    }
    
    unset($users[$targetLogin]);
    saveUsersData($users);
    
    // Удаление аватарки
    $avatarFile = AVATARS_DIR . '/' . $targetLogin . '.jpg';
    if (file_exists($avatarFile)) {
        unlink($avatarFile);
    }
    
    logAction('Удаление пользователя', "target: $targetLogin");
    
    return ['success' => true];
}

/**
 * Получение статистики
 */
function getSystemStats() {
    $users = getUsersData();
    $onlineStatuses = getOnlineStatuses();
    
    $stats = [
        'total_users' => count($users),
        'admins' => 0,
        'banned' => 0,
        'muted' => 0,
        'online' => 0,
        'chats' => 0,
        'messages' => 0,
        'media_files' => 0
    ];
    
    foreach ($users as $user) {
        if ($user['role'] === ROLE_ADMIN) $stats['admins']++;
        if ($user['banned']) $stats['banned']++;
        if ($user['muted']) $stats['muted']++;
    }
    
    foreach ($onlineStatuses as $login => $timestamp) {
        if ((time() - $timestamp) < 30) {
            $stats['online']++;
        }
    }
    
    // Подсчёт чатов и сообщений
    $chatFiles = glob(CONVERSATIONS_DIR . '/*.json');
    $stats['chats'] = count($chatFiles);
    
    foreach ($chatFiles as $file) {
        if ($file === CONVERSATIONS_DIR . '/online_status.json' || 
            $file === CONVERSATIONS_DIR . '/typing_status.json') {
            continue;
        }
        
        $content = file_get_contents($file);
        $messages = json_decode($content, true);
        if (is_array($messages)) {
            $stats['messages'] += count($messages);
            
            foreach ($messages as $msg) {
                if (!empty($msg['file'])) {
                    $stats['media_files']++;
                }
            }
        }
    }
    
    return $stats;
}

/**
 * Очистка кеша
 */
function clearCache() {
    $currentUserRole = getCurrentUserRole();
    
    if (!in_array($currentUserRole, [ROLE_ADMIN, ROLE_CREATOR])) {
        return ['success' => false, 'error' => 'Недостаточно прав'];
    }
    
    // Очистка чатов
    $chatFiles = glob(CONVERSATIONS_DIR . '/*.json');
    foreach ($chatFiles as $file) {
        if ($file !== CONVERSATIONS_DIR . '/online_status.json') {
            unlink($file);
        }
    }
    
    // Очистка загрузок (кроме index.html)
    $files = glob(UPLOADS_DIR . '/*');
    foreach ($files as $file) {
        if (basename($file) !== 'index.html') {
            if (is_dir($file)) {
                $subFiles = glob($file . '/*');
                foreach ($subFiles as $subFile) {
                    if (basename($subFile) !== 'index.html') {
                        unlink($subFile);
                    }
                }
            } else {
                unlink($file);
            }
        }
    }
    
    logAction('Очистка кеша');
    
    return ['success' => true];
}

/**
 * Диагностика системы
 */
function systemDiagnostics() {
    $diagnostics = [];
    
    // Проверка директорий
    $dirs = [
        'data' => DATA_DIR,
        'conversations' => CONVERSATIONS_DIR,
        'uploads' => UPLOADS_DIR,
        'avatars' => AVATARS_DIR,
        'logs' => LOGS_DIR
    ];
    
    foreach ($dirs as $name => $dir) {
        $diagnostics['dirs'][$name] = [
            'exists' => is_dir($dir),
            'writable' => is_writable($dir)
        ];
    }
    
    // Проверка JSON файлов
    $jsonFiles = [
        'users' => DATA_DIR . '/users.json'
    ];
    
    foreach ($jsonFiles as $name => $file) {
        $valid = true;
        if (file_exists($file)) {
            $content = file_get_contents($file);
            json_decode($content);
            $valid = json_last_error() === JSON_ERROR_NONE;
        }
        
        $diagnostics['json'][$name] = [
            'exists' => file_exists($file),
            'valid' => $valid
        ];
    }
    
    // Проверка GD
    $diagnostics['gd'] = function_exists('gd_info');
    
    // Версия PHP
    $diagnostics['php_version'] = PHP_VERSION;
    
    return $diagnostics;
}
