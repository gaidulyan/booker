<?php
require_once 'includes/db.php';

try {
    $conn = $db->getConnection();
    
    echo "<h2>Проверка настроек кодировки MySQL</h2>";
    
    // Проверка системных переменных MySQL
    $variables = [
        'character_set_server',
        'character_set_database',
        'character_set_client',
        'character_set_connection',
        'character_set_results',
        'collation_server',
        'collation_database',
        'collation_connection'
    ];
    
    echo "<h3>Системные переменные MySQL:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Переменная</th><th>Значение</th></tr>";
    
    foreach ($variables as $var) {
        $stmt = $conn->query("SHOW VARIABLES LIKE '$var'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<tr><td>{$row['Variable_name']}</td><td>{$row['Value']}</td></tr>";
    }
    
    echo "</table>";
    
    // Проверка кодировки таблиц
    echo "<h3>Кодировка таблиц:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Таблица</th><th>Кодировка</th><th>Сравнение</th></tr>";
    
    $stmt = $conn->query("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . DB_NAME . "'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['TABLE_NAME']}</td><td>" . explode('_', $row['TABLE_COLLATION'])[0] . "</td><td>{$row['TABLE_COLLATION']}</td></tr>";
    }
    
    echo "</table>";
    
    // Проверка кодировки столбцов
    echo "<h3>Кодировка столбцов таблицы books:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Столбец</th><th>Тип</th><th>Кодировка</th><th>Сравнение</th></tr>";
    
    $stmt = $conn->query("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_SET_NAME, COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . DB_NAME . "' AND TABLE_NAME = 'books'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['COLUMN_NAME']}</td><td>{$row['DATA_TYPE']}</td><td>{$row['CHARACTER_SET_NAME']}</td><td>{$row['COLLATION_NAME']}</td></tr>";
    }
    
    echo "</table>";
    
    // Исправление кодировки для всех текстовых столбцов
    echo "<h3>Исправление кодировки столбцов:</h3>";
    
    $tables = ['books', 'user_progress', 'users'];
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW FULL COLUMNS FROM `$table`");
        while ($column = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (strpos($column['Type'], 'varchar') !== false || 
                strpos($column['Type'], 'text') !== false || 
                strpos($column['Type'], 'char') !== false) {
                
                $columnName = $column['Field'];
                $conn->exec("ALTER TABLE `$table` MODIFY `$columnName` {$column['Type']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                echo "Столбец $table.$columnName обновлен до utf8mb4_unicode_ci<br>";
            }
        }
    }
    
    echo "<br>Проверка и исправление кодировки завершены!";
    
} catch(PDOException $e) {
    echo "Ошибка: " . $e->getMessage();
}
?> 