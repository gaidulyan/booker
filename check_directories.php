<?php
// Проверка и создание необходимых директорий
$directories = [
    'uploads',
    'assets/css',
    'assets/js'
];

foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!file_exists($path)) {
        mkdir($path, 0755, true);
        echo "Создана директория: $dir<br>";
    }
}

echo "Все необходимые директории созданы!"; 