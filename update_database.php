<?php
require_once 'includes/db.php';

try {
    $conn = $db->getConnection();
    
    echo "<h2>Обновление структуры базы данных</h2>";
    
    // Добавляем поле google_id в таблицу users
    $conn->exec("ALTER TABLE users ADD COLUMN google_id VARCHAR(100) NULL");
    $conn->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL");
    
    echo "Таблица users обновлена для поддержки Google OAuth.<br>";
    
    // Обновляем таблицу user_progress для хранения позиции чтения
    $conn->exec("ALTER TABLE user_progress ADD COLUMN page INT DEFAULT 1");
    $conn->exec("ALTER TABLE user_progress ADD COLUMN scroll_position INT DEFAULT 0");
    $conn->exec("ALTER TABLE user_progress ADD COLUMN last_page_text VARCHAR(255) NULL");
    
    echo "Таблица user_progress обновлена для хранения позиции чтения.<br>";
    
    echo "<br>База данных успешно обновлена!";
    
} catch(PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}
?> 