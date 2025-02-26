<?php
require_once 'includes/db.php';

try {
    $conn = $db->getConnection();
    
    echo "<h2>Пересоздание таблиц</h2>";
    
    // Удаляем существующие таблицы
    $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
    $conn->exec("DROP TABLE IF EXISTS user_progress");
    $conn->exec("DROP TABLE IF EXISTS books");
    $conn->exec("DROP TABLE IF EXISTS users");
    $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "Существующие таблицы удалены.<br>";
    
    // Создаем таблицы заново с правильной кодировкой
    $conn->exec("CREATE TABLE books (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        content LONGTEXT NOT NULL,
        cover_image LONGTEXT,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $conn->exec("CREATE TABLE user_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        book_id INT NOT NULL,
        position INT DEFAULT 0,
        last_read TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $conn->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    echo "Таблицы успешно пересозданы с кодировкой utf8mb4.<br>";
    
    // Создаем тестового пользователя
    $conn->exec("INSERT INTO users (username, password, email) VALUES ('test', '" . password_hash('test123', PASSWORD_DEFAULT) . "', 'test@example.com')");
    echo "Тестовый пользователь создан (логин: test, пароль: test123).<br>";
    
    echo "<br>Пересоздание таблиц завершено успешно!";
    
} catch(PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}
?> 