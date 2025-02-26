<?php
session_start();
require_once 'includes/functions.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    // Сохраняем URL для возврата после авторизации
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit;
}

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

// Временное решение для прогресса чтения
try {
    $progress = getUserDetailedProgress($_SESSION['user_id'], $bookId);
} catch (Exception $e) {
    // Если возникла ошибка с прогрессом, создаем временный прогресс
    $progress = [
        'page' => 1,
        'scroll_position' => 0,
        'last_page_text' => '',
        'last_read' => date('Y-m-d H:i:s')
    ];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Читалка FB2</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/reader.css">
    <style>
        #book-content {
            font-family: 'Times New Roman', Times, serif;
            font-size: 18px;
            line-height: 1.6;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .reader-controls {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            background: rgba(255, 255, 255, 0.9);
            padding: 10px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }
        .btn-control {
            padding: 8px 15px;
            margin: 0 5px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn-control:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?php echo htmlspecialchars($book['title']); ?></h1>
            <p class="author"><?php echo htmlspecialchars($book['author']); ?></p>
            <nav>
                <ul>
                    <li><a href="index.php">Вернуться к списку книг</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <div id="book-content">
                <?php echo $book['content']; ?>
            </div>
            
            <div class="reader-controls">
                <button id="save-progress" class="btn-control">Сохранить позицию</button>
                <span id="page-info">Страница: <span id="current-page">1</span></span>
            </div>
        </main>
    </div>
    
    <script>
        // Простой скрипт для сохранения позиции чтения
        document.addEventListener('DOMContentLoaded', function() {
            const bookContent = document.getElementById('book-content');
            const saveButton = document.getElementById('save-progress');
            const currentPageSpan = document.getElementById('current-page');
            
            let currentPage = <?php echo $progress['page']; ?>;
            currentPageSpan.textContent = currentPage;
            
            // Прокручиваем до сохраненной позиции
            window.scrollTo(0, <?php echo $progress['scroll_position']; ?>);
            
            // Обработчик сохранения позиции
            saveButton.addEventListener('click', function() {
                const scrollPosition = window.scrollY;
                
                // Отправляем данные на сервер
                fetch('save_progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: <?php echo $_SESSION['user_id']; ?>,
                        book_id: <?php echo $bookId; ?>,
                        page: currentPage,
                        scroll_position: scrollPosition
                    })
                })
                .then(response => response.json())
                .then(data => {
                    alert('Позиция сохранена!');
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    alert('Не удалось сохранить позицию.');
                });
            });
            
            // Обновление номера страницы при прокрутке
            window.addEventListener('scroll', function() {
                // Простая логика определения страницы по прокрутке
                // В реальном приложении можно использовать более сложную логику
                const scrollHeight = document.documentElement.scrollHeight;
                const clientHeight = document.documentElement.clientHeight;
                const scrollTop = window.scrollY;
                
                const scrollPercentage = (scrollTop / (scrollHeight - clientHeight)) * 100;
                currentPage = Math.max(1, Math.ceil(scrollPercentage / 10)); // Примерно 10 страниц
                
                currentPageSpan.textContent = currentPage;
            });
        });
    </script>
</body>
</html> 