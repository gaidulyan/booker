<?php
require_once 'includes/db.php';

try {
    $conn = $db->getConnection();
    
    echo "<h2>Исправление структуры базы данных</h2>";
    
    // Проверяем наличие колонки page в таблице user_progress
    $result = $conn->query("SHOW COLUMNS FROM user_progress LIKE 'page'");
    if ($result->rowCount() == 0) {
        $conn->exec("ALTER TABLE user_progress ADD COLUMN page INT DEFAULT 1");
        echo "Добавлена колонка page в таблицу user_progress.<br>";
    } else {
        echo "Колонка page уже существует в таблице user_progress.<br>";
    }
    
    // Проверяем наличие колонки scroll_position в таблице user_progress
    $result = $conn->query("SHOW COLUMNS FROM user_progress LIKE 'scroll_position'");
    if ($result->rowCount() == 0) {
        $conn->exec("ALTER TABLE user_progress ADD COLUMN scroll_position INT DEFAULT 0");
        echo "Добавлена колонка scroll_position в таблицу user_progress.<br>";
    } else {
        echo "Колонка scroll_position уже существует в таблице user_progress.<br>";
    }
    
    // Проверяем наличие колонки last_page_text в таблице user_progress
    $result = $conn->query("SHOW COLUMNS FROM user_progress LIKE 'last_page_text'");
    if ($result->rowCount() == 0) {
        $conn->exec("ALTER TABLE user_progress ADD COLUMN last_page_text VARCHAR(255) NULL");
        echo "Добавлена колонка last_page_text в таблицу user_progress.<br>";
    } else {
        echo "Колонка last_page_text уже существует в таблице user_progress.<br>";
    }
    
    // Проверяем наличие колонки last_read в таблице user_progress
    $result = $conn->query("SHOW COLUMNS FROM user_progress LIKE 'last_read'");
    if ($result->rowCount() == 0) {
        $conn->exec("ALTER TABLE user_progress ADD COLUMN last_read TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        echo "Добавлена колонка last_read в таблицу user_progress.<br>";
    } else {
        echo "Колонка last_read уже существует в таблице user_progress.<br>";
    }
    
    // Обновляем существующие записи, если есть колонка position
    $result = $conn->query("SHOW COLUMNS FROM user_progress LIKE 'position'");
    if ($result->rowCount() > 0) {
        $conn->exec("UPDATE user_progress SET page = FLOOR(position / 1000), scroll_position = position % 1000 WHERE page IS NULL");
        echo "Обновлены значения page и scroll_position на основе существующих данных position.<br>";
    }
    
    echo "<br>База данных успешно обновлена!";
    
} catch(PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}
?> 