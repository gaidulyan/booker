<?php
session_start();
require_once 'includes/functions.php';

// Временно установим user_id = 1 для демонстрации
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}

$userId = $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$bookId = (int)$_GET['id'];
$book = getBook($bookId);

if (!$book) {
    header('Location: index.php');
    exit;
}

// Получаем сохраненную позицию чтения
$position = getUserProgress($userId, $bookId);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Читалка FB2</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="reader-container">
        <header class="reader-header">
            <div class="reader-controls">
                <a href="index.php" class="back-btn">← Назад</a>
                <h1 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h1>
                <div class="reader-settings">
                    <button id="font-size-decrease">A-</button>
                    <button id="font-size-increase">A+</button>
                    <button id="theme-toggle">☀/☾</button>
                </div>
            </div>
        </header>
        
        <main class="reader-content" id="reader-content" data-book-id="<?php echo $bookId; ?>" data-user-id="<?php echo $userId; ?>">
            <?php echo $book['content']; ?>
        </main>
        
        <footer class="reader-footer">
            <div class="reader-progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="progress-info">
                    <span id="current-page">0</span> / <span id="total-pages">0</span>
                </div>
            </div>
            <div class="reader-navigation">
                <button id="prev-page">←</button>
                <button id="next-page">→</button>
            </div>
        </footer>
    </div>
    
    <script>
        // Сохраняем позицию чтения в переменной
        const savedPosition = <?php echo $position; ?>;
    </script>
    <script src="assets/js/reader.js"></script>
</body>
</html> 