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
    </style>
</head>
<body class="theme-light">
    <div class="reader-container">
        <div class="reader-header">
            <div class="left-controls">
                <button class="back-button" onclick="window.location.href='index.php'">&larr;</button>
                <span class="source-title">Источник</span>
            </div>
            <div class="right-controls">
                <button class="control-button" id="toc-button">☰</button>
                <button class="control-button" id="star-button">☆</button>
                <button class="control-button" id="fullscreen-button">⛶</button>
                <button class="control-button" id="font-button">Aa</button>
                <button class="control-button" id="more-button">⋯</button>
            </div>
        </div>
        
        <div class="progress-bar">
            <div class="progress-indicator" id="progress-indicator"></div>
        </div>
        
        <div class="book-content" id="book-content">
            <?php echo $book['content']; ?>
        </div>
        
        <div class="reader-footer">
            <span id="page-info">Предисловие</span>
            <span id="page-percent">0%</span>
        </div>
        
        <!-- Модальное окно настроек -->
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
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const bookContent = document.getElementById('book-content');
            const progressIndicator = document.getElementById('progress-indicator');
            const pageInfo = document.getElementById('page-info');
            const pagePercent = document.getElementById('page-percent');
            const fontButton = document.getElementById('font-button');
            const settingsModal = document.getElementById('settings-modal');
            const decreaseFontBtn = document.getElementById('decrease-font');
            const increaseFontBtn = document.getElementById('increase-font');
            const fontSizeValue = document.getElementById('font-size-value');
            const themeOptions = document.querySelectorAll('.theme-option');
            const fullscreenButton = document.getElementById('fullscreen-button');
            
            let currentPage = <?php echo $progress['page']; ?>;
            let fontSize = 18;
            
            // Прокручиваем до сохраненной позиции
            window.scrollTo(0, <?php echo $progress['scroll_position']; ?>);
            
            // Обновление прогресса при прокрутке
            window.addEventListener('scroll', function() {
                const scrollHeight = document.documentElement.scrollHeight;
                const clientHeight = document.documentElement.clientHeight;
                const scrollTop = window.scrollY;
                
                const scrollPercentage = (scrollTop / (scrollHeight - clientHeight)) * 100;
                progressIndicator.style.width = scrollPercentage + '%';
                
                // Обновляем номер страницы
                currentPage = Math.max(1, Math.ceil(scrollPercentage / 10)); // Примерно 10 страниц
                pagePercent.textContent = Math.round(scrollPercentage) + '%';
                
                // Автоматическое сохранение прогресса при прокрутке
                saveProgress(scrollTop);
            });
            
            // Функция сохранения прогресса
            let saveTimeout;
            function saveProgress(scrollPosition) {
                clearTimeout(saveTimeout);
                saveTimeout = setTimeout(function() {
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
                            last_page_text: getVisibleText()
                        })
                    })
                    .catch(error => {
                        console.error('Ошибка сохранения:', error);
                    });
                }, 2000); // Сохраняем через 2 секунды после остановки прокрутки
            }
            
            // Получение видимого текста
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
            
            // Загрузка сохраненного размера шрифта
            if (localStorage.getItem('reader_font_size')) {
                fontSize = parseInt(localStorage.getItem('reader_font_size'));
                updateFontSize();
            }
            
            // Изменение темы
            themeOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const theme = this.getAttribute('data-theme');
                    document.body.className = 'theme-' + theme;
                    
                    // Обновляем активную тему
                    themeOptions.forEach(opt => opt.classList.remove('active'));
                    this.classList.add('active');
                    
                    localStorage.setItem('reader_theme', theme);
                });
            });
            
            // Загрузка сохраненной темы
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
            
            // Полноэкранный режим
            fullscreenButton.addEventListener('click', function() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(err => {
                        console.error(`Ошибка: ${err.message}`);
                    });
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                }
            });
        });
    </script>
</body>
</html> 