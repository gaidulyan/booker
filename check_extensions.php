<?php
echo "<h2>Проверка необходимых расширений PHP</h2>";

$requiredExtensions = [
    'mbstring' => 'Работа с многобайтовыми строками (кириллица)',
    'xml' => 'Обработка XML (FB2 файлы)',
    'pdo' => 'Работа с базой данных',
    'pdo_mysql' => 'Работа с MySQL',
    'fileinfo' => 'Определение типов файлов'
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Расширение</th><th>Статус</th><th>Описание</th></tr>";

foreach ($requiredExtensions as $ext => $desc) {
    $loaded = extension_loaded($ext);
    $status = $loaded ? 
        "<span style='color:green'>Установлено</span>" : 
        "<span style='color:red'>Отсутствует</span>";
    
    echo "<tr><td>$ext</td><td>$status</td><td>$desc</td></tr>";
}

echo "</table>";

echo "<h3>Информация о PHP:</h3>";
echo "Версия PHP: " . PHP_VERSION . "<br>";
echo "Сервер: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";
echo "Временная директория: " . sys_get_temp_dir() . "<br>";
?> 