<?php
session_start();
require_once 'includes/functions.php';

// Временно установим user_id = 1 для демонстрации
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['title']) && isset($_POST['author']) && isset($_POST['content'])) {
        try {
            $bookData = [
                'title' => $_POST['title'],
                'author' => $_POST['author'],
                'content' => $_POST['content'],
                'cover_image' => null
            ];
            
            $filePath = 'manual_import_' . time() . '.txt';
            
            $bookId = saveBook($bookData, $filePath);
            echo "<div style='color: green; margin: 20px 0;'>Книга успешно добавлена! ID: $bookId</div>";
            echo "<a href='reader.php?id=$bookId'>Читать книгу</a>";
        } catch (Exception $e) {
            echo "<div style='color: red; margin: 20px 0;'>Ошибка: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ручное добавление книги</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Ручное добавление книги</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Главная</a></li>
                    <li><a href="upload.php">Загрузить книгу</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <section>
                <h2>Добавить книгу вручную</h2>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="title">Название книги:</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="author">Автор:</label>
                        <input type="text" id="author" name="author" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="content">Содержимое (HTML):</label>
                        <textarea id="content" name="content" rows="10" required><div class="section"><p>Текст книги...</p></div></textarea>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn">Добавить книгу</button>
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