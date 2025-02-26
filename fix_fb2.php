<?php
if (!isset($_GET['file']) || empty($_GET['file'])) {
    die("Не указан файл для проверки");
}

require_once 'includes/config.php';

$fileName = $_GET['file'];
$filePath = UPLOAD_DIR . $fileName;

if (!file_exists($filePath)) {
    die("Файл не найден: $filePath");
}

echo "<h2>Проверка и исправление FB2 файла: $fileName</h2>";

// Читаем файл
$content = file_get_contents($filePath);
if ($content === false) {
    die("Не удалось прочитать содержимое файла");
}

// Определяем кодировку
$encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'KOI8-R', 'CP1251'], true);
echo "Определенная кодировка: " . ($encoding ?: "Не удалось определить") . "<br>";

// Конвертируем в UTF-8 если нужно
if ($encoding && $encoding !== 'UTF-8') {
    $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    echo "Конвертировано в UTF-8<br>";
}

// Удаляем BOM
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

// Исправляем некорректные символы &
$content = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;)/', '&amp;', $content);
echo "Исправлены некорректные символы &<br>";

// Проверяем валидность XML
libxml_use_internal_errors(true);
$xml = simplexml_load_string($content);

if (!$xml) {
    $errors = libxml_get_errors();
    libxml_clear_errors();
    
    echo "<h3>Ошибки XML:</h3>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>Строка {$error->line}, столбец {$error->column}: {$error->message}</li>";
    }
    echo "</ul>";
    
    // Сохраняем исправленный файл
    $fixedFilePath = UPLOAD_DIR . "fixed_" . $fileName;
    if (file_put_contents($fixedFilePath, $content)) {
        echo "Исправленный файл сохранен как: fixed_$fileName<br>";
        echo "<a href='process_file.php?file=fixed_$fileName'>Попробовать обработать исправленный файл</a>";
    } else {
        echo "Не удалось сохранить исправленный файл";
    }
} else {
    echo "XML валиден!<br>";
    echo "<a href='process_file.php?file=$fileName'>Обработать файл</a>";
}
?> 