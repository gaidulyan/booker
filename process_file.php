<?php
session_start();
require_once 'includes/functions.php';

// Временно установим user_id = 1 для демонстрации
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

if (!isset($_GET['file']) || empty($_GET['file'])) {
    die("Не указан файл для обработки");
}

$fileName = $_GET['file'];
$filePath = UPLOAD_DIR . $fileName;

if (!file_exists($filePath)) {
    die("Файл не найден: $filePath");
}

echo "<h2>Обработка файла: $fileName</h2>";

try {
    // Включаем вывод всех ошибок
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    // Устанавливаем кодировку
    mb_internal_encoding('UTF-8');
    
    echo "Начинаем парсинг FB2 файла...<br>";
    
    // Парсим FB2 файл
    $bookData = parseBookFB2($filePath);
    
    echo "Парсинг завершен успешно.<br>";
    echo "Название книги: " . htmlspecialchars($bookData['title']) . "<br>";
    echo "Автор: " . htmlspecialchars($bookData['author']) . "<br>";
    echo "Размер контента: " . strlen($bookData['content']) . " байт<br>";
    echo "Обложка: " . ($bookData['cover_image'] ? "Есть" : "Нет") . "<br><br>";
    
    echo "Сохраняем книгу в базу данных...<br>";
    
    // Сохраняем книгу в базу данных с отловом всех исключений
    try {
        $bookId = saveBook($bookData, $fileName);
        echo "Книга успешно сохранена в базу данных! ID: $bookId<br>";
        echo "<a href='reader.php?id=$bookId'>Читать книгу</a>";
    } catch (Exception $e) {
        echo "Ошибка при сохранении книги: " . $e->getMessage() . "<br>";
        
        // Дополнительная отладочная информация
        echo "<h3>Отладочная информация:</h3>";
        echo "PDO::errorInfo(): ";
        print_r($db->getConnection()->errorInfo());
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}
?> 