<?php
session_start();
require_once 'includes/functions.php';

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit('Метод не разрешен');
}

// Проверка наличия необходимых параметров
if (!isset($_POST['user_id']) || !isset($_POST['book_id']) || !isset($_POST['position'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Отсутствуют необходимые параметры');
}

$userId = (int)$_POST['user_id'];
$bookId = (int)$_POST['book_id'];
$position = (int)$_POST['position'];

// Проверка авторизации (в простом варианте)
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $userId) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Не авторизован');
}

// Сохранение прогресса
if (saveUserProgress($userId, $bookId, $position)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} else {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Ошибка при сохранении прогресса']);
} 