<?php
/**
 * Конфигурация мессенджера
 */

// Определяем корневую директорию
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}

// Пути к директориям
define('CHAT_DIR', ROOT_DIR . '/chat');
define('DATA_DIR', CHAT_DIR . '/data');
define('CONVERSATIONS_DIR', CHAT_DIR . '/conversations');
define('UPLOADS_DIR', CHAT_DIR . '/uploads');
define('AVATARS_DIR', UPLOADS_DIR . '/avatars');
define('LOGS_DIR', CHAT_DIR . '/logs');
define('MODULES_DIR', CHAT_DIR . '/modules');

// Параметры сессии
define('SESSION_NAME', 'messenger_session');
define('SESSION_LIFETIME', 3600 * 24); // 24 часа

// Параметры аватарок
define('AVATAR_SIZE', 200);
define('AVATAR_QUALITY', 85);
define('MAX_AVATAR_SIZE', 5 * 1024 * 1024); // 5 MB

// Параметры файлов
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'mp3', 'wav', 'ogg', 'mp4', 'webm', 'pdf', 'doc', 'docx', 'txt', 'zip']);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg', 'image/png', 'image/gif',
    'audio/mpeg', 'audio/wav', 'audio/ogg',
    'video/mp4', 'video/webm',
    'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain', 'application/zip'
]);

// Роли пользователей
define('ROLE_CREATOR', 'creator');
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// Настройка ошибок
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', LOGS_DIR . '/error.log');

// Создание директорий если не существуют
$dirs = [DATA_DIR, CONVERSATIONS_DIR, UPLOADS_DIR, AVATARS_DIR, LOGS_DIR, MODULES_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Создание index.html для защиты директорий
foreach ([UPLOADS_DIR, AVATARS_DIR] as $dir) {
    $indexFile = $dir . '/index.html';
    if (!file_exists($indexFile)) {
        file_put_contents($indexFile, '<!DOCTYPE html><html><head><title>Access Denied</title></head><body><h1>Access Denied</h1></body></html>');
    }
}
