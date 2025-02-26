<?php
require_once 'includes/db.php';

echo "Проверка подключения к базе данных:<br>";
echo "Хост: " . DB_HOST . "<br>";
echo "Имя базы данных: " . DB_NAME . "<br>";
echo "Пользователь: " . DB_USER . "<br>";

try {
    $conn = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<br>Подключение к базе данных успешно установлено!<br>";
    
    // Проверка таблиц
    $tables = ['books', 'user_progress', 'users'];
    echo "<br>Проверка таблиц:<br>";
    
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "Таблица '$table' существует.<br>";
        } else {
            echo "Таблица '$table' НЕ существует!<br>";
        }
    }
} catch(PDOException $e) {
    echo "<br>Ошибка подключения к базе данных: " . $e->getMessage();
}
?> 