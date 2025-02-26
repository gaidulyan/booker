<?php
session_start();
require_once 'includes/functions.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Получаем данные из POST-запроса
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['book_id']) || !isset($data['page']) || !isset($data['scroll_position'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

// Проверяем, что пользователь сохраняет свой прогресс
if ($data['user_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Сохраняем прогресс
try {
    saveUserProgress(
        $_SESSION['user_id'],
        $data['book_id'],
        $data['page'],
        $data['scroll_position'],
        $data['last_page_text'] ?? ''
    );
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?> 