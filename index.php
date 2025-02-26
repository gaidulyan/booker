<?php
session_start();
require_once 'includes/functions.php';

// Временно установим user_id = 1 для демонстрации
// В реальном приложении здесь будет авторизация
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

$books = getAllBooks();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Читалка FB2</title>
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
            <section class="book-list">
                <h2>Ваша библиотека</h2>
                
                <?php if (empty($books)): ?>
                    <p>У вас пока нет загруженных книг. <a href="upload.php">Загрузите свою первую книгу</a>.</p>
                <?php else: ?>
                    <div class="books-grid">
                        <?php foreach ($books as $book): ?>
                            <div class="book-card">
                                <div class="book-cover">
                                    <?php if ($book['cover_image']): ?>
                                        <img src="<?php echo $book['cover_image']; ?>" alt="<?php echo htmlspecialchars($book['title']); ?>">
                                    <?php else: ?>
                                        <div class="no-cover"><?php echo substr($book['title'], 0, 1); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="book-info">
                                    <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                                    <p class="author"><?php echo htmlspecialchars($book['author']); ?></p>
                                    <p class="upload-date">Загружено: <?php echo date('d.m.Y', strtotime($book['upload_date'])); ?></p>
                                    <a href="reader.php?id=<?php echo $book['id']; ?>" class="read-btn">Читать</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Читалка FB2</p>
        </footer>
    </div>
</body>
</html> 