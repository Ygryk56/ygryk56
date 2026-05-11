<?php
/**
 * Логгер для записи ошибок и действий
 */

require_once __DIR__ . '/../config.php';

/**
 * Запись сообщения в лог
 * @param string $message Сообщение
 * @param string $level Уровень (ERROR, WARNING, INFO, ACTION)
 * @param string $context Дополнительный контекст
 */
function logMessage($message, $level = 'INFO', $context = '') {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user = isset($_SESSION['user_login']) ? $_SESSION['user_login'] : 'guest';
    
    $logEntry = sprintf(
        "[%s] [%s] [IP:%s] [User:%s] %s %s\n",
        $timestamp,
        $level,
        $ip,
        $user,
        $message,
        $context ? "($context)" : ""
    );
    
    $logFile = LOGS_DIR . '/error.log';
    
    // Проверка размера лога (макс 1MB)
    if (file_exists($logFile) && filesize($logFile) > 1024 * 1024) {
        $lines = file($logFile);
        $lines = array_slice($lines, -100); // Оставляем последние 100 строк
        file_put_contents($logFile, implode('', $lines));
    }
    
    // Запись с блокировкой
    $fp = fopen($logFile, 'a');
    if ($fp) {
        flock($fp, LOCK_EX);
        fwrite($fp, $logEntry);
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/**
 * Запись ошибки
 */
function logError($message, $context = '') {
    logMessage($message, 'ERROR', $context);
}

/**
 * Запись действия пользователя
 */
function logAction($message, $context = '') {
    logMessage($message, 'ACTION', $context);
}
