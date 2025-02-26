<?php
require_once 'includes/db.php';

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Изменение кодировки базы данных
    $conn->exec("ALTER DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Кодировка базы данных изменена на utf8mb4<br>";
    
    // Изменение кодировки таблиц
    $tables = ['books', 'user_progress', 'users'];
    foreach ($tables as $table) {
        $conn->exec("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Кодировка таблицы '$table' изменена на utf8mb4<br>";
    }
    
    echo "<br>Кодировка базы данных успешно обновлена!";
} catch(PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}
?> 