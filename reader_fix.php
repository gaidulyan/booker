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

// Разбиваем контент на страницы
$contentPages = [];
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $book['content']);
$xpath = new DOMXPath($dom);
$paragraphs = $xpath->query('//p');

// Разбиваем на страницы по параграфам
$pageContent = '';
$paragraphCount = 0;
$wordsPerPage = 300; // Примерное количество слов на странице
$wordCount = 0;

foreach ($paragraphs as $paragraph) {
    $paragraphText = $dom->saveHTML($paragraph);
    $paragraphWords = str_word_count(strip_tags($paragraphText));
    
    // Если добавление этого параграфа превысит лимит слов на странице, создаем новую страницу
    if ($wordCount > 0 && ($wordCount + $paragraphWords) > $wordsPerPage) {
        $contentPages[] = '<div class="page-content">' . $pageContent . '</div>';
        $pageContent = $paragraphText;
        $wordCount = $paragraphWords;
    } else {
        $pageContent .= $paragraphText;
        $wordCount += $paragraphWords;
    }
}

// Добавляем последнюю страницу, если есть контент
if (!empty($pageContent)) {
    $contentPages[] = '<div class="page-content">' . $pageContent . '</div>';
}

// Если страниц нет, используем весь контент как одну страницу
if (empty($contentPages)) {
    $contentPages[] = '<div class="page-content">' . $book['content'] . '</div>';
}

// Определяем текущую страницу
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : $progress['page'];
if ($currentPage < 1 || $currentPage > count($contentPages)) {
    $currentPage = 1;
}

// Получаем содержимое текущей страницы и следующей (для двухстраничного режима)
$currentPageContent = $contentPages[$currentPage - 1] ?? '';
$nextPageContent = $contentPages[$currentPage] ?? '';

// Общее количество страниц
$totalPages = count($contentPages);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Читалка FB2</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/reader.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
<body class="theme-light">
    <div class="reader-container">
        <!-- Верхняя панель -->
        <header class="reader-header">
            <div class="left-controls">
                <button class="back-button" id="back-button">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <span class="source-title"><?php echo htmlspecialchars($book['title']); ?></span>
            </div>
            <div class="right-controls">
                <button class="control-button layout-toggle" id="layout-toggle" title="Две страницы">
                    <i class="fas fa-columns"></i>
                </button>
                <button class="control-button" id="font-button" title="Настройки">
                    <i class="fas fa-font"></i>
                </button>
                <button class="control-button" id="fullscreen-button" title="На весь экран">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
        </header>
        
        <!-- Контейнер для книги -->
        <div class="book-container">
            <div class="book">
                <div class="book-content single-page-mode" id="book-content">
                    <div class="page" id="current-page">
                        <?php echo $currentPageContent; ?>
                    </div>
                    <div class="page" id="next-page" style="display: none;">
                        <?php echo $nextPageContent; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Нижняя панель -->
        <footer class="reader-footer">
            <div class="pagination-controls">
                <button class="pagination-btn" id="prev-page" <?php if ($currentPage <= 1) echo 'disabled'; ?>>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="pagination-info">
                    <span id="current-page-num"><?php echo $currentPage; ?></span> из <span id="total-pages"><?php echo $totalPages; ?></span>
                </span>
                <button class="pagination-btn" id="next-page-btn" <?php if ($currentPage >= $totalPages) echo 'disabled'; ?>>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <div class="progress-container">
                <span class="progress-text"><?php echo round(($currentPage / $totalPages) * 100); ?>%</span>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($currentPage / $totalPages) * 100; ?>%;"></div>
                </div>
            </div>
            
            <button class="control-button" id="save-button">
                <i class="fas fa-save"></i> Сохранить позицию
            </button>
        </footer>
        
        <!-- Модальное окно настроек -->
        <div class="settings-modal" id="settings-modal">
            <h3>Настройки</h3>
            
            <div class="font-size-control">
                <span>Размер шрифта</span>
                <button class="font-size-btn" id="decrease-font">-</button>
                <span class="font-size-value" id="font-size-value">18</span>
                <button class="font-size-btn" id="increase-font">+</button>
            </div>
            
            <div>
                <span>Тема</span>
                <div class="theme-options">
                    <div class="theme-option theme-light active" data-theme="light"></div>
                    <div class="theme-option theme-sepia" data-theme="sepia"></div>
                    <div class="theme-option theme-dark" data-theme="dark"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Элементы управления
            const backButton = document.getElementById('back-button');
            const fontButton = document.getElementById('font-button');
            const fullscreenButton = document.getElementById('fullscreen-button');
            const layoutToggle = document.getElementById('layout-toggle');
            const saveButton = document.getElementById('save-button');
            const prevPageBtn = document.getElementById('prev-page');
            const nextPageBtn = document.getElementById('next-page-btn');
            const currentPageNum = document.getElementById('current-page-num');
            const totalPages = document.getElementById('total-pages');
            const progressFill = document.querySelector('.progress-fill');
            const progressText = document.querySelector('.progress-text');
            const settingsModal = document.getElementById('settings-modal');
            const decreaseFontBtn = document.getElementById('decrease-font');
            const increaseFontBtn = document.getElementById('increase-font');
            const fontSizeValue = document.getElementById('font-size-value');
            const themeOptions = document.querySelectorAll('.theme-option');
            const bookContent = document.getElementById('book-content');
            const currentPageElement = document.getElementById('current-page');
            const nextPageElement = document.getElementById('next-page');
            
            // Переменные состояния
            let currentPageIndex = <?php echo $currentPage; ?>;
            let totalPagesCount = <?php echo $totalPages; ?>;
            let fontSize = parseInt(localStorage.getItem('reader_font_size')) || 18;
            let isFullscreen = false;
            let isTwoPageMode = false;
            
            // Инициализация
            updateFontSize();
            loadSavedTheme();
            
            // Функция обновления размера шрифта
            function updateFontSize() {
                document.querySelectorAll('.page').forEach(page => {
                    page.style.fontSize = fontSize + 'px';
                });
                fontSizeValue.textContent = fontSize;
                localStorage.setItem('reader_font_size', fontSize);
            }
            
            // Функция загрузки сохраненной темы
            function loadSavedTheme() {
                const savedTheme = localStorage.getItem('reader_theme') || 'light';
                document.body.classList.add('theme-' + savedTheme);
                
                themeOptions.forEach(option => {
                    if (option.getAttribute('data-theme') === savedTheme) {
                        option.classList.add('active');
                    } else {
                        option.classList.remove('active');
                    }
                });
            }
            
            // Функция переключения двухстраничного режима
            function toggleTwoPageMode() {
                isTwoPageMode = !isTwoPageMode;
                
                if (isTwoPageMode) {
                    bookContent.classList.remove('single-page-mode');
                    bookContent.classList.add('two-page-mode');
                    document.body.classList.add('two-page-mode');
                    nextPageElement.style.display = 'block';
                    layoutToggle.innerHTML = '<i class="fas fa-book-open"></i>';
                } else {
                    bookContent.classList.add('single-page-mode');
                    bookContent.classList.remove('two-page-mode');
                    document.body.classList.remove('two-page-mode');
                    nextPageElement.style.display = 'none';
                    layoutToggle.innerHTML = '<i class="fas fa-columns"></i>';
                }
                
                localStorage.setItem('reader_two_page_mode', isTwoPageMode ? '1' : '0');
            }
            
            // Функция перехода на страницу
            function goToPage(pageNum) {
                if (pageNum < 1 || pageNum > totalPagesCount) return;
                
                // Сохраняем текущую позицию перед переходом
                saveProgress(false);
                
                // Переходим на новую страницу
                window.location.href = `reader_fix.php?id=<?php echo $bookId; ?>&page=${pageNum}`;
            }
            
            // Функция сохранения прогресса
            function saveProgress(showMessage = false) {
                const scrollPosition = window.scrollY;
                
                fetch('save_progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        user_id: <?php echo $_SESSION['user_id']; ?>,
                        book_id: <?php echo $bookId; ?>,
                        page: currentPageIndex,
                        scroll_position: scrollPosition,
                        last_page_text: currentPageElement.textContent.substring(0, 100)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (showMessage) {
                        alert('Позиция сохранена!');
                    }
                })
                .catch(error => {
                    console.error('Ошибка:', error);
                    if (showMessage) {
                        alert('Не удалось сохранить позицию.');
                    }
                });
            }
            
            // Обработчики событий
            backButton.addEventListener('click', function() {
                // Сохраняем прогресс перед уходом
                saveProgress(false);
                window.location.href = 'index.php';
            });
            
            fontButton.addEventListener('click', function() {
                if (settingsModal.style.display === 'block') {
                    settingsModal.style.display = 'none';
                } else {
                    settingsModal.style.display = 'block';
                }
            });
            
            saveButton.addEventListener('click', function() {
                saveProgress(true);
            });
            
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
            
            fullscreenButton.addEventListener('click', function() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().then(() => {
                        isFullscreen = true;
                        document.body.classList.add('fullscreen-mode');
                        fullscreenButton.innerHTML = '<i class="fas fa-compress"></i>';
                    }).catch(err => {
                        console.error(`Ошибка: ${err.message}`);
                    });
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen().then(() => {
                            isFullscreen = false;
                            document.body.classList.remove('fullscreen-mode');
                            fullscreenButton.innerHTML = '<i class="fas fa-expand"></i>';
                        });
                    }
                }
            });
            
            layoutToggle.addEventListener('click', function() {
                toggleTwoPageMode();
            });
            
            prevPageBtn.addEventListener('click', function() {
                if (currentPageIndex > 1) {
                    goToPage(currentPageIndex - 1);
                }
            });
            
            nextPageBtn.addEventListener('click', function() {
                if (currentPageIndex < totalPagesCount) {
                    goToPage(currentPageIndex + 1);
                }
            });
            
            // Закрытие модального окна при клике вне его
            document.addEventListener('click', function(event) {
                if (settingsModal.style.display === 'block' && 
                    !settingsModal.contains(event.target) && 
                    event.target !== fontButton) {
                    settingsModal.style.display = 'none';
                }
            });
            
            // Обработка клавиш
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && isFullscreen) {
                    if (document.exitFullscreen) {
                        document.exitFullscreen().then(() => {
                            isFullscreen = false;
                            document.body.classList.remove('fullscreen-mode');
                            fullscreenButton.innerHTML = '<i class="fas fa-expand"></i>';
                        });
                    }
                } else if (event.key === 'ArrowLeft') {
                    if (currentPageIndex > 1) {
                        goToPage(currentPageIndex - 1);
                    }
                } else if (event.key === 'ArrowRight') {
                    if (currentPageIndex < totalPagesCount) {
                        goToPage(currentPageIndex + 1);
                    }
                } else if (event.key === 'f' && event.ctrlKey) {
                    // Ctrl+F для полноэкранного режима
                    event.preventDefault();
                    fullscreenButton.click();
                } else if (event.key === 'd' && event.ctrlKey) {
                    // Ctrl+D для двухстраничного режима
                    event.preventDefault();
                    layoutToggle.click();
                }
            });
            
            // Автоматическое сохранение прогресса при загрузке страницы
            window.addEventListener('load', function() {
                // Восстанавливаем режим отображения
                if (localStorage.getItem('reader_two_page_mode') === '1') {
                    toggleTwoPageMode();
                }
                
                // Автоматически сохраняем прогресс
                setTimeout(function() {
                    saveProgress(false);
                }, 2000);
            });
            
            // Автоматическое сохранение прогресса перед закрытием страницы
            window.addEventListener('beforeunload', function() {
                saveProgress(false);
            });
        });
    </script>
</body>
</html> 