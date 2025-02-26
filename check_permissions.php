<?php
// Проверка прав доступа к директории uploads
$uploadsDir = __DIR__ . '/uploads';

echo "Проверка директории uploads:<br>";
echo "Путь: " . $uploadsDir . "<br>";

if (!file_exists($uploadsDir)) {
    echo "Директория не существует! Создаем...<br>";
    if (mkdir($uploadsDir, 0755, true)) {
        echo "Директория успешно создана.<br>";
    } else {
        echo "Ошибка при создании директории!<br>";
    }
} else {
    echo "Директория существует.<br>";
}

echo "Права доступа: " . substr(sprintf('%o', fileperms($uploadsDir)), -4) . "<br>";
echo "Владелец: " . posix_getpwuid(fileowner($uploadsDir))['name'] . "<br>";
echo "Группа: " . posix_getgrgid(filegroup($uploadsDir))['name'] . "<br>";

// Проверка возможности записи
if (is_writable($uploadsDir)) {
    echo "Директория доступна для записи.<br>";
} else {
    echo "Директория НЕ доступна для записи!<br>";
    echo "Пытаемся изменить права доступа...<br>";
    
    if (chmod($uploadsDir, 0755)) {
        echo "Права доступа успешно изменены.<br>";
    } else {
        echo "Не удалось изменить права доступа!<br>";
    }
}

// Проверка временной директории PHP
$tmpDir = sys_get_temp_dir();
echo "<br>Временная директория PHP: " . $tmpDir . "<br>";
if (is_writable($tmpDir)) {
    echo "Временная директория доступна для записи.<br>";
} else {
    echo "Временная директория НЕ доступна для записи!<br>";
}

// Проверка настроек PHP для загрузки файлов
echo "<br>Настройки PHP для загрузки файлов:<br>";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "post_max_size: " . ini_get('post_max_size') . "<br>";
echo "max_file_uploads: " . ini_get('max_file_uploads') . "<br>";
?> 