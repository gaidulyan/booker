<?php
session_start();

// Проверяем наличие расширения mbstring
if (function_exists('mb_detect_encoding')) {
    require_once 'includes/functions.php';
} else {
    require_once 'includes/functions_no_mbstring.php';
}

// Временно установим user_id = 1 для демонстрации
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['book'])) {
        $file = $_FILES['book'];
        
        // Проверка на ошибки загрузки
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                    $error = 'Размер файла превышает значение upload_max_filesize в php.ini';
                    break;
                case UPLOAD_ERR_FORM_SIZE:
                    $error = 'Размер файла превышает значение MAX_FILE_SIZE в HTML-форме';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $error = 'Файл был загружен только частично';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $error = 'Файл не был загружен';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $error = 'Отсутствует временная директория';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $error = 'Не удалось записать файл на диск';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $error = 'Загрузка файла была остановлена расширением PHP';
                    break;
                default:
                    $error = 'Неизвестная ошибка при загрузке файла';
            }
        } else {
            // Проверка размера файла
            if ($file['size'] > MAX_FILE_SIZE) {
                $error = 'Файл слишком большой. Максимальный размер: ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
            }
            
            // Проверка типа файла
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($fileExt !== 'fb2') {
                $error = 'Поддерживаются только файлы формата FB2';
            }
            
            if (empty($error)) {
                // Создаем директорию для загрузок, если она не существует
                if (!file_exists(UPLOAD_DIR)) {
                    if (!mkdir(UPLOAD_DIR, 0755, true)) {
                        $error = 'Не удалось создать директорию для загрузки файлов';
                    }
                }
                
                if (empty($error)) {
                    // Генерируем уникальное имя файла
                    $fileName = uniqid() . '.fb2';
                    $filePath = UPLOAD_DIR . $fileName;
                    
                    if (move_uploaded_file($file['tmp_name'], $filePath)) {
                        try {
                            // Парсим FB2 файл
                            $bookData = parseBookFB2($filePath);
                            
                            // Сохраняем книгу в базу данных
                            $bookId = saveBook($bookData, $fileName);
                            
                            $message = 'Книга успешно загружена! <a href="reader.php?id=' . $bookId . '">Читать сейчас</a>';
                        } catch (Exception $e) {
                            $error = 'Ошибка при обработке файла: ' . $e->getMessage();
                            // Удаляем загруженный файл в случае ошибки
                            @unlink($filePath);
                        }
                    } else {
                        $error = 'Ошибка при перемещении загруженного файла. Проверьте права доступа к директории uploads.';
                    }
                }
            }
        }
    } else {
        $error = 'Пожалуйста, выберите файл для загрузки';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузить книгу - Читалка FB2</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Читалка FB2</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Главная</a></li>
                    <li><a href="upload.php">Загрузить книгу</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <section class="upload-form">
                <h2>Загрузить новую книгу</h2>
                
                <?php if (!empty($message)): ?>
                    <div class="message success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form action="upload.php" method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="book">Выберите FB2 файл:</label>
                        <input type="file" id="book" name="book" accept=".fb2" required>
                        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_FILE_SIZE; ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Загрузить книгу</button>
                    </div>
                </form>
            </section>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Читалка FB2</p>
        </footer>
    </div>
</body>
</html> 