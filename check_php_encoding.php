<?php
echo "<h2>Проверка настроек PHP для работы с кодировками</h2>";

echo "<h3>Расширение mbstring:</h3>";
if (extension_loaded('mbstring')) {
    echo "Расширение mbstring загружено.<br>";
    echo "Внутренняя кодировка: " . mb_internal_encoding() . "<br>";
    echo "Кодировка HTTP вывода: " . mb_http_output() . "<br>";
    echo "Определение порядка кодировок: " . implode(', ', mb_detect_order()) . "<br>";
} else {
    echo "Расширение mbstring НЕ загружено!<br>";
}

echo "<h3>Локаль:</h3>";
echo "Текущая локаль: " . setlocale(LC_ALL, 0) . "<br>";

echo "<h3>Заголовки HTTP:</h3>";
echo "Content-Type: " . ini_get('default_mimetype') . "; charset=" . ini_get('default_charset') . "<br>";

echo "<h3>Тест кодировки:</h3>";
$testString = "Тестовая строка с кириллицей";
echo "Исходная строка: $testString<br>";
echo "Длина строки (strlen): " . strlen($testString) . " байт<br>";
echo "Длина строки (mb_strlen): " . mb_strlen($testString, 'UTF-8') . " символов<br>";
echo "Кодировка строки: " . mb_detect_encoding($testString, ['UTF-8', 'Windows-1251', 'KOI8-R'], true) . "<br>";

// Установка правильных настроек для PHP
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_detect_order(['UTF-8', 'Windows-1251', 'KOI8-R']);

echo "<br>Настройки PHP для работы с кодировками обновлены!";
?> 