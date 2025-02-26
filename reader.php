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

// Получаем прогресс чтения пользователя
$progress = getUserDetailedProgress($_SESSION['user_id'], $bookId);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Читалка FB2</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/reader.css">
    <script>
        // Сохраняем ID книги и пользователя для использования в JavaScript
        const bookId = <?php echo $bookId; ?>;
        const userId = <?php echo $_SESSION['user_id']; ?>;
        const initialPage = <?php echo $progress['page']; ?>;
        const initialScrollPosition = <?php echo $progress['scroll_position']; ?>;
    </script>
</head>
<body>
    <div class="container reader-container">
        <header class="reader-header">
            <div class="book-info">
                <h1><?php echo htmlspecialchars($book['title']); ?></h1>
                <p class="author"><?php echo htmlspecialchars($book['author']); ?></p>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Библиотека</a></li>
                    <li><a href="#" id="toggle-settings">Настройки</a></li>
                </ul>
            </nav>
        </header>
        
        <div class="reader-settings" id="settings-panel">
            <h3>Настройки чтения</h3>
            <div class="setting-group">
                <label for="font-size">Размер шрифта:</label>
                <input type="range" id="font-size" min="12" max="24" value="16">
                <span id="font-size-value">16px</span>
            </div>
            <div class="setting-group">
                <label for="line-height">Высота строки:</label>
                <input type="range" id="line-height" min="1.2" max="2" step="0.1" value="1.5">
                <span id="line-height-value">1.5</span>
            </div>
            <div class="setting-group">
                <label for="theme">Тема:</label>
                <select id="theme">
                    <option value="light">Светлая</option>
                    <option value="sepia">Сепия</option>
                    <option value="dark">Темная</option>
                </select>
            </div>
        </div>
        
        <main class="reader-content" id="reader">
            <div class="book-content" id="book-content">
                <?php echo $book['content']; ?>
            </div>
            
            <div class="reader-controls">
                <button id="prev-page" class="btn">Назад</button>
                <span id="page-info">Страница <span id="current-page">1</span></span>
                <button id="next-page" class="btn">Вперед</button>
            </div>
        </main>
        
        <footer class="reader-footer">
            <p>Последнее чтение: <?php echo date('d.m.Y H:i', strtotime($progress['last_read'])); ?></p>
        </footer>
    </div>
    
    <script src="assets/js/reader.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Инициализация читалки
            initReader();
            
            // Восстановление позиции чтения
            restoreReadingPosition(initialPage, initialScrollPosition);
            
            // Сохранение прогресса каждые 5 секунд
            setInterval(saveReadingProgress, 5000);
        });
        
        function initReader() {
            const settingsToggle = document.getElementById('toggle-settings');
            const settingsPanel = document.getElementById('settings-panel');
            const fontSizeSlider = document.getElementById('font-size');
            const fontSizeValue = document.getElementById('font-size-value');
            const lineHeightSlider = document.getElementById('line-height');
            const lineHeightValue = document.getElementById('line-height-value');
            const themeSelect = document.getElementById('theme');
            const bookContent = document.getElementById('book-content');
            const prevPageBtn = document.getElementById('prev-page');
            const nextPageBtn = document.getElementById('next-page');
            const currentPageSpan = document.getElementById('current-page');
            
            // Загружаем сохраненные настройки
            loadReaderSettings();
            
            // Обработчики событий для настроек
            settingsToggle.addEventListener('click', function(e) {
                e.preventDefault();
                settingsPanel.classList.toggle('active');
            });
            
            fontSizeSlider.addEventListener('input', function() {
                const size = this.value;
                fontSizeValue.textContent = size + 'px';
                bookContent.style.fontSize = size + 'px';
                saveReaderSettings();
            });
            
            lineHeightSlider.addEventListener('input', function() {
                const height = this.value;
                lineHeightValue.textContent = height;
                bookContent.style.lineHeight = height;
                saveReaderSettings();
            });
            
            themeSelect.addEventListener('change', function() {
                setTheme(this.value);
                saveReaderSettings();
            });
            
            // Навигация по страницам
            prevPageBtn.addEventListener('click', function() {
                navigatePage(-1);
            });
            
            nextPageBtn.addEventListener('click', function() {
                navigatePage(1);
            });
            
            // Обработка клавиш
            document.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowLeft') {
                    navigatePage(-1);
                } else if (e.key === 'ArrowRight') {
                    navigatePage(1);
                }
            });
        }
        
        function loadReaderSettings() {
            const settings = JSON.parse(localStorage.getItem('readerSettings')) || {
                fontSize: 16,
                lineHeight: 1.5,
                theme: 'light'
            };
            
            document.getElementById('font-size').value = settings.fontSize;
            document.getElementById('font-size-value').textContent = settings.fontSize + 'px';
            document.getElementById('book-content').style.fontSize = settings.fontSize + 'px';
            
            document.getElementById('line-height').value = settings.lineHeight;
            document.getElementById('line-height-value').textContent = settings.lineHeight;
            document.getElementById('book-content').style.lineHeight = settings.lineHeight;
            
            document.getElementById('theme').value = settings.theme;
            setTheme(settings.theme);
        }
        
        function saveReaderSettings() {
            const settings = {
                fontSize: document.getElementById('font-size').value,
                lineHeight: document.getElementById('line-height').value,
                theme: document.getElementById('theme').value
            };
            
            localStorage.setItem('readerSettings', JSON.stringify(settings));
        }
        
        function setTheme(theme) {
            const body = document.body;
            body.classList.remove('theme-light', 'theme-sepia', 'theme-dark');
            body.classList.add('theme-' + theme);
        }
        
        function navigatePage(direction) {
            const bookContent = document.getElementById('book-content');
            const currentPageSpan = document.getElementById('current-page');
            
            // Получаем текущую страницу
            let currentPage = parseInt(currentPageSpan.textContent);
            
            // Вычисляем новую страницу
            currentPage += direction;
            
            // Проверяем границы
            if (currentPage < 1) {
                currentPage = 1;
                return;
            }
            
            // Обновляем номер страницы
            currentPageSpan.textContent = currentPage;
            
            // Сохраняем прогресс
            saveReadingProgress();
            
            // Прокручиваем к началу страницы
            window.scrollTo(0, 0);
        }
        
        function restoreReadingPosition(page, scrollPosition) {
            const currentPageSpan = document.getElementById('current-page');
            currentPageSpan.textContent = page;
            
            // Прокручиваем до сохраненной позиции
            window.scrollTo(0, scrollPosition);
        }
        
        function saveReadingProgress() {
            const currentPage = parseInt(document.getElementById('current-page').textContent);
            const scrollPosition = window.scrollY;
            
            // Получаем текст текущей страницы для дополнительной идентификации
            const visibleText = getVisibleText();
            
            // Отправляем данные на сервер
            fetch('save_progress.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    user_id: userId,
                    book_id: bookId,
                    page: currentPage,
                    scroll_position: scrollPosition,
                    last_page_text: visibleText
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Прогресс сохранен:', data);
            })
            .catch(error => {
                console.error('Ошибка при сохранении прогресса:', error);
            });
        }
        
        function getVisibleText() {
            // Получаем видимый текст на странице для идентификации позиции
            const bookContent = document.getElementById('book-content');
            const viewportHeight = window.innerHeight;
            const scrollTop = window.scrollY;
            
            // Находим все текстовые элементы
            const textElements = bookContent.querySelectorAll('p, h1, h2, h3, h4, h5, h6');
            
            // Ищем первый видимый элемент
            for (const element of textElements) {
                const rect = element.getBoundingClientRect();
                
                // Если элемент видим
                if (rect.top >= 0 && rect.top <= viewportHeight) {
                    // Возвращаем первые 100 символов текста
                    return element.textContent.substring(0, 100);
                }
            }
            
            return '';
        }
    </script>
</body>
</html> 