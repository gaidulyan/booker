<?php
require_once 'includes/config.php';

echo "<h2>Проверка загруженных файлов</h2>";

if (!file_exists(UPLOAD_DIR)) {
    echo "Директория uploads не существует!";
    exit;
}

$files = scandir(UPLOAD_DIR);
$files = array_diff($files, ['.', '..']);

if (empty($files)) {
    echo "В директории uploads нет файлов.";
    exit;
}

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Файл</th><th>Размер</th><th>Дата изменения</th><th>Действия</th></tr>";

foreach ($files as $file) {
    $filePath = UPLOAD_DIR . $file;
    $fileSize = filesize($filePath);
    $fileDate = date("Y-m-d H:i:s", filemtime($filePath));
    
    echo "<tr>";
    echo "<td>$file</td>";
    echo "<td>" . number_format($fileSize / 1024, 2) . " KB</td>";
    echo "<td>$fileDate</td>";
    echo "<td><a href='process_file.php?file=$file'>Обработать</a></td>";
    echo "</tr>";
}

echo "</table>";
?> 