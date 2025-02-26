<?php
session_start();
require_once 'includes/functions.php';

// Получаем список книг
$books = [];
$recentBooks = [];

try {
    global $db;
    
    // Получаем все книги
    $sql = "SELECT * FROM books ORDER BY upload_date DESC";
    $result = $db->query($sql);
    $books = $result->fetchAll(PDO::FETCH_ASSOC);
    
    // Если пользователь авторизован, получаем его недавно читаемые книги
    if (isset($_SESSION['user_id'])) {
        $sql = "SELECT b.*, up.last_read, up.page, up.scroll_position 
                FROM books b 
                JOIN user_progress up ON b.id = up.book_id 
                WHERE up.user_id = :user_id 
                ORDER BY up.last_read DESC 
                LIMIT 5";
        
        $result = $db->query($sql, [':user_id' => $_SESSION['user_id']]);
        $recentBooks = $result->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
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
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="upload.php">Загрузить книгу</a></li>
                        <li><a href="simple_repair.php">Управление книгами</a></li>
                        <li><a href="logout.php">Выход (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Вход</a></li>
                        <li><a href="register.php">Регистрация</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </header>
        
        <main>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <section class="welcome">
                    <h2>Добро пожаловать в Читалку FB2</h2>
                    <p>Для использования всех возможностей читалки, пожалуйста, <a href="login.php">войдите</a> или <a href="register.php">зарегистрируйтесь</a>.</p>
                </section>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['user_id']) && !empty($recentBooks)): ?>
                <section class="recent-books">
                    <h2>Недавно читаемые книги</h2>
                    <div class="books-grid">
                        <?php foreach ($recentBooks as $book): ?>
                            <div class="book-card">
                                <div class="book-cover">
                                    <?php if ($book['cover_image']): ?>
                                        <img src="<?php echo $book['cover_image']; ?>" alt="Обложка">
                                    <?php else: ?>
                                        <div class="no-cover">Нет обложки</div>
                                    <?php endif; ?>
                                </div>
                                <div class="book-info">
                                    <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                                    <p class="author"><?php echo htmlspecialchars($book['author']); ?></p>
                                    <p class="last-read">Последнее чтение: <?php echo date('d.m.Y H:i', strtotime($book['last_read'])); ?></p>
                                    <p class="progress">Страница: <?php echo $book['page']; ?></p>
                                    <a href="reader_fix.php?id=<?php echo $book['id']; ?>" class="btn">Продолжить чтение</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            
            <section class="all-books">
                <h2>Все книги</h2>
                <?php if (empty($books)): ?>
                    <p>Пока нет загруженных книг. <?php if (isset($_SESSION['user_id'])): ?><a href="upload.php">Загрузите свою первую книгу</a>.<?php endif; ?></p>
                <?php else: ?>
                    <div class="books-grid">
                        <?php foreach ($books as $book): ?>
                            <div class="book-card">
                                <div class="book-cover">
                                    <?php if ($book['cover_image']): ?>
                                        <img src="<?php echo $book['cover_image']; ?>" alt="Обложка">
                                    <?php else: ?>
                                        <div class="no-cover">Нет обложки</div>
                                    <?php endif; ?>
                                </div>
                                <div class="book-info">
                                    <h3><?php echo htmlspecialchars($book['title']); ?></h3>
                                    <p class="author"><?php echo htmlspecialchars($book['author']); ?></p>
                                    <a href="reader_fix.php?id=<?php echo $book['id']; ?>" class="btn">Читать</a>
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