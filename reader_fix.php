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

// Получаем прогресс чтения
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
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            background-color: #f9f9f9;
            color: #333;
        }
        
        .reader-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        .reader-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            background-color: #fff;
            border-bottom: 1px solid #eee;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .reader-header .left-controls {
            display: flex;
            align-items: center;
        }
        
        .reader-header .right-controls {
            display: flex;
            align-items: center;
        }
        
        .back-button {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #555;
            margin-right: 15px;
        }
        
        .source-title {
            font-size: 16px;
            color: #777;
        }
        
        .control-button {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #555;
            margin-left: 15px;
        }
        
        .book-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
            font-size: 18px;
            font-family: 'Times New Roman', Times, serif;
        }
        
        .book-content h1, .book-content h2, .book-content h3 {
            margin-top: 1.5em;
            margin-bottom: 0.5em;
        }
        
        .book-content p {
            margin-bottom: 1em;
            text-align: justify;
        }
        
        .progress-bar {
            height: 4px;
            background-color: #eee;
            position: relative;
        }
        
        .progress-indicator {
            position: absolute;
            height: 100%;
            background-color: #4285f4;
            width: 0%;
        }
        
        .reader-footer {
            padding: 10px 20px;
            background-color: #fff;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 14px;
            color: #777;
        }
        
        /* Стили для модального окна настроек */
        .settings-modal {
            display: none;
            position: fixed;
            top: 0;
            right: 0;
            width: 300px;
            height: 100%;
            background-color: #fff;
            box-shadow: -2px 0 5px rgba(0,0,0,0.1);
            z-index: 1000;
            padding: 20px;
            overflow-y: auto;
        }
        
        .settings-modal h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .settings-group {
            margin-bottom: 20px;
        }
        
        .settings-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .font-size-controls {
            display: flex;
            align-items: center;
        }
        
        .font-size-btn {
            background: #eee;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            font-weight: bold;
            cursor: pointer;
        }
        
        .font-size-value {
            margin: 0 10px;
        }
        
        .theme-options {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .theme-option {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .theme-option.light {
            background-color: #fff;
            border-color: #ddd;
        }
        
        .theme-option.sepia {
            background-color: #f8f1e3;
            border-color: #e8d8b9;
        }
        
        .theme-option.dark {
            background-color: #333;
            border-color: #555;
        }
        
        .theme-option.active {
            border-color: #4285f4;
        }
        
        /* Темы оформления */
        body.theme-light {
            background-color: #fff;
            color: #333;
        }
        
        body.theme-sepia {
            background-color: #f8f1e3;
            color: #5b4636;
        }
        
        body.theme-sepia .book-content {
            background-color: #f8f1e3;
        }
        
        body.theme-dark {
            background-color: #333;
            color: #eee;
        }
        
        body.theme-dark .book-content {
            background-color: #333;
        }
        
        body.theme-dark .reader-header,
        body.theme-dark .reader-footer {
            background-color: #222;
            border-color: #444;
        }
        
        body.theme-dark .back-button,
        body.theme-dark .control-button,
        body.theme-dark .source-title {
            color: #ccc;
        }
        
        /* Дополнительные встроенные стили */
        .book-content img {
            max-width: 100%;
            height: auto;
        }
        
        /* Стили для пагинации */
        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 10px;
        }
        
        .pagination-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #555;
            margin: 0 10px;
            transition: color 0.3s;
        }
        
        .pagination-btn:disabled {
            color: #ccc;
            cursor: not-allowed;
        }
        
        .pagination-info {
            font-size: 14px;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="reader-container">
        <header class="reader-header">
            <div class="left-controls">
                <button class="back-button" id="back-button">←</button>
                <span class="source-title"><?php echo htmlspecialchars($book['title']); ?></span>
            </div>
            <div class="right-controls">
                <div class="layout-toggle">
                    <button class="layout-toggle-btn" id="layout-toggle">
                        <span id="layout-icon">☰</span>
                    </button>
                    <span class="layout-toggle-label" id="layout-label">Одна страница</span>
                </div>
                <button class="control-button" id="font-button">Aa</button>
                <button class="control-button" id="fullscreen-button">⛶</button>
            </div>
        </header>
        
        <div class="progress-bar">
            <div class="progress-indicator" id="progress-indicator"></div>
        </div>
        
        <div class="book-content" id="book-content">
            <?php echo $book['content']; ?>
        </div>
        
        <footer class="reader-footer">
            <div class="page-info">
                Страница <span id="current-page"><?php echo $progress['page']; ?></span>
            </div>
            <div class="progress-info">
                Прогресс: <span id="progress-percentage">0%</span>
            </div>
            <button id="save-progress">Сохранить позицию</button>
        </footer>
    </div>
    
    <div class="settings-modal" id="settings-modal">
        <h3>Настройки</h3>
        
        <div class="settings-group">
            <label>Размер шрифта</label>
            <div class="font-size-controls">
                <button class="font-size-btn" id="decrease-font">-</button>
                <span class="font-size-value" id="font-size-value">18</span>
                <button class="font-size-btn" id="increase-font">+</button>
            </div>
        </div>
        
        <div class="settings-group">
            <label>Тема</label>
            <div class="theme-options">
                <div class="theme-option light active" data-theme="light"></div>
                <div class="theme-option sepia" data-theme="sepia"></div>
                <div class="theme-option dark" data-theme="dark"></div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bookContent = document.getElementById('book-content');
            const saveButton = document.getElementById('save-progress');
            const currentPageSpan = document.getElementById('current-page');
            const progressIndicator = document.getElementById('progress-indicator');
            const progressPercentage = document.getElementById('progress-percentage');
            const backButton = document.getElementById('back-button');
            const fontButton = document.getElementById('font-button');
            const fullscreenButton = document.getElementById('fullscreen-button');
            const settingsModal = document.getElementById('settings-modal');
            const decreaseFontBtn = document.getElementById('decrease-font');
            const increaseFontBtn = document.getElementById('increase-font');
            const fontSizeValue = document.getElementById('font-size-value');
            const themeOptions = document.querySelectorAll('.theme-option');
            const layoutToggle = document.getElementById('layout-toggle');
            const layoutIcon = document.getElementById('layout-icon');
            const layoutLabel = document.getElementById('layout-label');
            
            let currentPage = <?php echo $progress['page']; ?>;
            let fontSize = 18;
            let isFullscreen = false;
            let isTwoPageMode = false;
            let contentElements = [];
            let totalPages = 0;
            
            // Инициализация
            init();
            
            function init() {
                // Прокручиваем до сохраненной позиции
                window.scrollTo(0, <?php echo $progress['scroll_position']; ?>);
                
                // Обновляем прогресс
                updateProgress();
                
                // Загружаем сохраненные настройки
                loadSettings();
                
                // Подготавливаем контент для двухстраничного режима
                prepareContent();
            }
            
            function prepareContent() {
                // Получаем все элементы контента
                contentElements = Array.from(bookContent.children);
                
                // Определяем общее количество страниц
                totalPages = Math.ceil(contentElements.length / 20); // Примерно 20 элементов на страницу
            }
            
            function toggleTwoPageMode() {
                isTwoPageMode = !isTwoPageMode;
                
                if (isTwoPageMode) {
                    document.body.classList.add('two-page-mode');
                    layoutIcon.textContent = '⊞';
                    layoutLabel.textContent = 'Две страницы';
                    
                    // Очищаем контент
                    bookContent.innerHTML = '';
                    
                    // Создаем две страницы
                    const leftPage = document.createElement('div');
                    leftPage.className = 'page left-page';
                    
                    const rightPage = document.createElement('div');
                    rightPage.className = 'page right-page';
                    
                    // Добавляем страницы в контейнер
                    bookContent.appendChild(leftPage);
                    bookContent.appendChild(rightPage);
                    
                    // Распределяем контент по страницам
                    distributeContent(currentPage);
                    
                    // Добавляем элементы пагинации
                    addPaginationControls();
                } else {
                    document.body.classList.remove('two-page-mode');
                    layoutIcon.textContent = '☰';
                    layoutLabel.textContent = 'Одна страница';
                    
                    // Восстанавливаем оригинальный контент
                    bookContent.innerHTML = '';
                    contentElements.forEach(element => {
                        bookContent.appendChild(element.cloneNode(true));
                    });
                    
                    // Удаляем элементы пагинации
                    const paginationControls = document.querySelector('.pagination-controls');
                    if (paginationControls) {
                        paginationControls.remove();
                    }
                    
                    // Прокручиваем до текущей страницы
                    setTimeout(() => {
                        const scrollPosition = (currentPage / totalPages) * (document.documentElement.scrollHeight - window.innerHeight);
                        window.scrollTo(0, scrollPosition);
                    }, 100);
                }
                
                // Сохраняем настройку
                localStorage.setItem('reader_two_page_mode', isTwoPageMode ? 'true' : 'false');
            }
            
            function distributeContent(page) {
                const leftPage = document.querySelector('.left-page');
                const rightPage = document.querySelector('.right-page');
                
                if (!leftPage || !rightPage) return;
                
                // Очищаем страницы
                leftPage.innerHTML = '';
                rightPage.innerHTML = '';
                
                // Определяем индексы элементов для текущей пары страниц
                const startIndex = (page - 1) * 20;
                const middleIndex = startIndex + 10;
                const endIndex = startIndex + 20;
                
                // Заполняем левую страницу
                for (let i = startIndex; i < middleIndex && i < contentElements.length; i++) {
                    leftPage.appendChild(contentElements[i].cloneNode(true));
                }
                
                // Заполняем правую страницу
                for (let i = middleIndex; i < endIndex && i < contentElements.length; i++) {
                    rightPage.appendChild(contentElements[i].cloneNode(true));
                }
                
                // Обновляем информацию о странице
                currentPageSpan.textContent = page;
                updateProgress();
            }
            
            function addPaginationControls() {
                // Создаем контейнер для элементов пагинации
                const paginationControls = document.createElement('div');
                paginationControls.className = 'pagination-controls';
                
                // Кнопка "Предыдущая страница"
                const prevButton = document.createElement('button');
                prevButton.className = 'pagination-btn';
                prevButton.textContent = '←';
                prevButton.disabled = currentPage <= 1;
                prevButton.addEventListener('click', () => {
                    if (currentPage > 1) {
                        currentPage--;
                        distributeContent(currentPage);
                        prevButton.disabled = currentPage <= 1;
                        nextButton.disabled = currentPage >= totalPages;
                        paginationInfo.textContent = `${currentPage} из ${totalPages}`;
                    }
                });
                
                // Информация о странице
                const paginationInfo = document.createElement('span');
                paginationInfo.className = 'pagination-info';
                paginationInfo.textContent = `${currentPage} из ${totalPages}`;
                
                // Кнопка "Следующая страница"
                const nextButton = document.createElement('button');
                nextButton.className = 'pagination-btn';
                nextButton.textContent = '→';
                nextButton.disabled = currentPage >= totalPages;
                nextButton.addEventListener('click', () => {
                    if (currentPage < totalPages) {
                        currentPage++;
                        distributeContent(currentPage);
                        prevButton.disabled = currentPage <= 1;
                        nextButton.disabled = currentPage >= totalPages;
                        paginationInfo.textContent = `${currentPage} из ${totalPages}`;
                    }
                });
                
                // Добавляем элементы в контейнер
                paginationControls.appendChild(prevButton);
                paginationControls.appendChild(paginationInfo);
                paginationControls.appendChild(nextButton);
                
                // Добавляем контейнер после контента
                bookContent.parentNode.insertBefore(paginationControls, bookContent.nextSibling);
            }
            
            function updateProgress() {
                const scrollTop = window.scrollY;
                const scrollHeight = document.documentElement.scrollHeight;
                const clientHeight = document.documentElement.clientHeight;
                
                if (isTwoPageMode) {
                    // В двухстраничном режиме прогресс основан на номере текущей страницы
                    const progress = (currentPage / totalPages) * 100;
                    progressIndicator.style.width = `${progress}%`;
                    progressPercentage.textContent = `${Math.round(progress)}%`;
                } else {
                    // В обычном режиме прогресс основан на прокрутке
                    const scrollPercentage = (scrollTop / (scrollHeight - clientHeight)) * 100;
                    progressIndicator.style.width = `${scrollPercentage}%`;
                    progressPercentage.textContent = `${Math.round(scrollPercentage)}%`;
                    
                    // Обновляем номер страницы
                    currentPage = Math.max(1, Math.ceil(scrollPercentage / 100 * totalPages));
                    currentPageSpan.textContent = currentPage;
                }
            }
            
            function loadSettings() {
                // Загрузка размера шрифта
                if (localStorage.getItem('reader_font_size')) {
                    fontSize = parseInt(localStorage.getItem('reader_font_size'));
                    updateFontSize();
                }
                
                // Загрузка темы
                if (localStorage.getItem('reader_theme')) {
                    const savedTheme = localStorage.getItem('reader_theme');
                    document.body.className = 'theme-' + savedTheme;
                    
                    themeOptions.forEach(option => {
                        if (option.getAttribute('data-theme') === savedTheme) {
                            option.classList.add('active');
                        } else {
                            option.classList.remove('active');
                        }
                    });
                }
                
                // Загрузка режима отображения
                if (localStorage.getItem('reader_two_page_mode') === 'true') {
                    setTimeout(() => {
                        toggleTwoPageMode();
                    }, 100);
                }
            }
            
            // Обработчики событий
            backButton.addEventListener('click', function() {
                window.location.href = 'index.php';
            });
            
            saveButton.addEventListener('click', function() {
                saveProgress();
            });
            
            // Автоматическое сохранение прогресса при прокрутке
            let saveTimeout;
            window.addEventListener('scroll', function() {
                if (!isTwoPageMode) {
                    updateProgress();
                    
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        saveProgress(true);
                    }, 2000);
                }
            });
            
            function saveProgress(silent = false) {
                const scrollPosition = window.scrollY;
                const lastPageText = getVisibleText();
                
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
                        scroll_position: scrollPosition,
                        last_page_text: lastPageText
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!silent && data.success) {
                        alert('Позиция сохранена!');
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    if (!silent) {
                        alert('Не удалось сохранить позицию.');
                    }
                });
            }
            
            function getVisibleText() {
                const textElements = bookContent.querySelectorAll('p, h1, h2, h3, h4, h5, h6');
                const viewportHeight = window.innerHeight;
                
                for (const element of textElements) {
                    const rect = element.getBoundingClientRect();
                    if (rect.top >= 0 && rect.top <= viewportHeight) {
                        return element.textContent.substring(0, 100);
                    }
                }
                return '';
            }
            
            // Настройки шрифта
            fontButton.addEventListener('click', function() {
                settingsModal.style.display = settingsModal.style.display === 'block' ? 'none' : 'block';
            });
            
            // Закрытие модального окна при клике вне его
            document.addEventListener('click', function(event) {
                if (!settingsModal.contains(event.target) && event.target !== fontButton) {
                    settingsModal.style.display = 'none';
                }
            });
            
            // Изменение размера шрифта
            decreaseFontBtn.addEventListener('click', function() {
                if (fontSize > 12) {
                    fontSize -= 2;
                    updateFontSize();
                }
            });
            
            increaseFontBtn.addEventListener('click', function() {
                if (fontSize < 32) {
                    fontSize += 2;
                    updateFontSize();
                }
            });
            
            function updateFontSize() {
                bookContent.style.fontSize = fontSize + 'px';
                fontSizeValue.textContent = fontSize;
                localStorage.setItem('reader_font_size', fontSize);
            }
            
            // Изменение темы
            themeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const theme = this.getAttribute('data-theme');
                    
                    // Удаляем все классы тем
                    document.body.classList.remove('theme-light', 'theme-sepia', 'theme-dark');
                    
                    // Добавляем класс выбранной темы
                    document.body.classList.add('theme-' + theme);
                    
                    // Сохраняем режим отображения, если он активен
                    if (isTwoPageMode) {
                        document.body.classList.add('two-page-mode');
                    }
                    
                    // Обновляем активную тему
                    themeOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    
                    localStorage.setItem('reader_theme', theme);
                });
            });
            
            // Полноэкранный режим
            fullscreenButton.addEventListener('click', function() {
                toggleFullscreen();
            });
            
            function toggleFullscreen() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().then(() => {
                        isFullscreen = true;
                        document.body.classList.add('fullscreen-mode');
                    }).catch(err => {
                        console.error(`Ошибка: ${err.message}`);
                    });
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen().then(() => {
                            isFullscreen = false;
                            document.body.classList.remove('fullscreen-mode');
                        });
                    }
                }
            }
            
            // Переключение режима отображения
            layoutToggle.addEventListener('click', function() {
                toggleTwoPageMode();
            });
            
            // Обработка клавиш
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && isFullscreen) {
                    toggleFullscreen();
                } else if (event.key === 'ArrowLeft' && isTwoPageMode) {
                    // Предыдущая страница
                    const prevButton = document.querySelector('.pagination-controls .pagination-btn:first-child');
                    if (prevButton && !prevButton.disabled) {
                        prevButton.click();
                    }
                } else if (event.key === 'ArrowRight' && isTwoPageMode) {
                    // Следующая страница
                    const nextButton = document.querySelector('.pagination-controls .pagination-btn:last-child');
                    if (nextButton && !nextButton.disabled) {
                        nextButton.click();
                    }
                } else if (event.key === 'f' && event.ctrlKey) {
                    // Ctrl+F для полноэкранного режима
                    event.preventDefault();
                    toggleFullscreen();
                } else if (event.key === 'd' && event.ctrlKey) {
                    // Ctrl+D для двухстраничного режима
                    event.preventDefault();
                    toggleTwoPageMode();
                }
            });
        });
    </script>
</body>
</html> 