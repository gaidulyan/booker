<?php
session_start();
require_once 'includes/functions.php';

// Временно установим user_id = 1 для демонстрации
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['book']) && $_FILES['book']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['book'];
        
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
                mkdir(UPLOAD_DIR, 0755, true);
            }
            
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
                    unlink($filePath);
                }
            } else {
                $error = 'Ошибка при загрузке файла';
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