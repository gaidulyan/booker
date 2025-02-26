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

// Определяем текущую страницу
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : $progress['page'];
if ($currentPage < 1) {
    $currentPage = 1;
}

// Подготавливаем DOM для работы с содержимым книги
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $book['content']);
$xpath = new DOMXPath($dom);

// Извлекаем оглавление
$chapters = [];
$headings = $xpath->query('//h1|//h2|//h3|//h4|//div[@class="title"]|//div[@class="subtitle"]');
$chapterIndex = 0;

foreach ($headings as $heading) {
    $chapterTitle = trim($heading->textContent);
    if (!empty($chapterTitle)) {
        $chapterIndex++;
        $chapters[] = [
            'id' => $chapterIndex,
            'title' => $chapterTitle,
            'element_id' => 'chapter_' . $chapterIndex
        ];
        
        // Добавляем ID к элементу заголовка для навигации
        $heading->setAttribute('id', 'chapter_' . $chapterIndex);
    }
}

// Получаем полное содержимое книги с добавленными ID для глав
$bookContent = $dom->saveHTML();

// Если оглавление пустое, создаем искусственное оглавление
if (empty($chapters)) {
    // Получаем все параграфы
    $paragraphs = $xpath->query('//p');
    $totalParagraphs = $paragraphs->length;
    
    // Создаем примерно 10 глав
    $chapterSize = max(1, ceil($totalParagraphs / 10));
    
    for ($i = 0; $i < $totalParagraphs; $i += $chapterSize) {
        $chapterIndex++;
        $chapterTitle = 'Часть ' . $chapterIndex;
        
        // Если есть параграф для этой главы
        if ($i < $totalParagraphs) {
            $paragraphs->item($i)->setAttribute('id', 'chapter_' . $chapterIndex);
            $chapters[] = [
                'id' => $chapterIndex,
                'title' => $chapterTitle,
                'element_id' => 'chapter_' . $chapterIndex
            ];
        }
    }
    
    // Получаем обновленное содержимое книги с добавленными ID
    $bookContent = $dom->saveHTML();
}

// Общее количество глав
$totalChapters = count($chapters);

// Если текущая страница больше общего количества глав, устанавливаем последнюю
if ($currentPage > $totalChapters && $totalChapters > 0) {
    $currentPage = $totalChapters;
}

// Определяем текущую главу
$currentChapter = null;
if (!empty($chapters) && isset($chapters[$currentPage - 1])) {
    $currentChapter = $chapters[$currentPage - 1];
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($book['title']); ?> - Читалка</title>
    <link rel="stylesheet" href="assets/css/reader.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* Дополнительные стили для улучшения отображения */
        .book-content {
            position: relative;
            overflow: hidden;
        }
        
        .page {
            overflow: hidden;
            position: relative;
            height: 100%;
            box-sizing: border-box;
        }
        
        .page-content {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: auto;
            visibility: hidden;
        }
        
        .page-content.active {
            visibility: visible;
            position: relative;
        }
        
        /* Стили для виртуальных страниц */
        .virtual-page {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        
        .virtual-page.active {
            opacity: 1;
            pointer-events: auto;
        }
    </style>
</head>
<body>
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
                <button class="control-button toc-button" id="toc-button" title="Оглавление">
                    <i class="fas fa-list"></i>
                </button>
                <button class="control-button" id="font-button" title="Настройки">
                    <i class="fas fa-font"></i>
                </button>
                <button class="control-button" id="layout-toggle" title="Режим отображения">
                    <i class="fas fa-columns"></i>
                </button>
                <button class="control-button" id="fullscreen-button" title="Полный экран">
                    <i class="fas fa-expand"></i>
                </button>
            </div>
        </header>
        
        <!-- Оглавление -->
        <div class="toc-modal" id="toc-modal">
            <div class="toc-header">
                <span class="toc-title">Оглавление</span>
                <button class="toc-close" id="toc-close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <ul class="toc-list">
                <?php foreach ($chapters as $index => $chapter): ?>
                <li class="toc-item <?php echo ($currentPage == $index + 1) ? 'active' : ''; ?>" 
                    data-page="<?php echo $index + 1; ?>" 
                    data-element-id="<?php echo $chapter['element_id']; ?>">
                    <?php echo htmlspecialchars($chapter['title']); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <!-- Контейнер для книги -->
        <div class="book-container">
            <div class="book">
                <div class="book-content" id="book-content">
                    <!-- Содержимое книги -->
                    <div class="page" id="current-page">
                        <?php echo $bookContent; ?>
                    </div>
                    <div class="page" id="next-page" style="display: none;"></div>
                </div>
                
                <!-- Области для перемотки страниц -->
                <div class="page-turn-area left" id="prev-page-area">
                    <div class="page-turn-indicator">
                        <i class="fas fa-chevron-left"></i>
                    </div>
                </div>
                <div class="page-turn-area right" id="next-page-area">
                    <div class="page-turn-indicator">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Нижняя панель -->
        <footer class="reader-footer">
            <div class="pagination">
                <button class="pagination-btn" id="prev-page-btn">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="pagination-info">
                    <span id="current-page-num"><?php echo $currentPage; ?></span> из <span id="total-pages"><?php echo $totalChapters; ?></span>
                </div>
                <button class="pagination-btn" id="next-page-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="progress-text" id="progress-text">0%</div>
            </div>
            <button class="save-button" id="save-btn" title="Сохранить позицию">
                <i class="fas fa-bookmark"></i>
            </button>
        </footer>
        
        <!-- Модальное окно настроек -->
        <div class="settings-modal" id="settings-modal">
            <div class="settings-header">
                <h3>Настройки</h3>
            </div>
            <div class="settings-content">
                <div class="settings-section">
                    <h4>Размер шрифта</h4>
                    <div class="font-size-control">
                        <button class="font-size-btn" id="decrease-font-btn">-</button>
                        <span class="font-size-value" id="font-size-value">18</span>
                        <button class="font-size-btn" id="increase-font-btn">+</button>
                    </div>
                </div>
                <div class="settings-section">
                    <h4>Тема</h4>
                    <div class="theme-options">
                        <div class="theme-option theme-light" data-theme="light"></div>
                        <div class="theme-option theme-sepia" data-theme="sepia"></div>
                        <div class="theme-option theme-dark" data-theme="dark"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Элементы интерфейса
            const backButton = document.getElementById('back-button');
            const fontButton = document.getElementById('font-button');
            const layoutToggle = document.getElementById('layout-toggle');
            const fullscreenButton = document.getElementById('fullscreen-button');
            const tocButton = document.getElementById('toc-button');
            const tocClose = document.getElementById('toc-close');
            const tocItems = document.querySelectorAll('.toc-item');
            const currentPageElement = document.getElementById('current-page');
            const nextPageElement = document.getElementById('next-page');
            const prevPageBtn = document.getElementById('prev-page-btn');
            const nextPageBtn = document.getElementById('next-page-btn');
            const prevPageArea = document.getElementById('prev-page-area');
            const nextPageArea = document.getElementById('next-page-area');
            const currentPageNum = document.getElementById('current-page-num');
            const totalPagesElement = document.getElementById('total-pages');
            const progressFill = document.getElementById('progress-fill');
            const progressText = document.getElementById('progress-text');
            const saveBtn = document.getElementById('save-btn');
            const settingsModal = document.getElementById('settings-modal');
            const decreaseFontBtn = document.getElementById('decrease-font-btn');
            const increaseFontBtn = document.getElementById('increase-font-btn');
            const fontSizeValue = document.getElementById('font-size-value');
            const themeOptions = document.querySelectorAll('.theme-option');
            const tocModal = document.getElementById('toc-modal');
            
            // Переменные
            const userId = <?php echo $_SESSION['user_id']; ?>;
            const bookId = <?php echo $bookId; ?>;
            let fontSize = parseInt(localStorage.getItem('reader_font_size')) || 18;
            let isFullscreen = false;
            let isTwoPageMode = localStorage.getItem('reader_two_page_mode') === '1';
            
            // Инициализация
            fontSizeValue.textContent = fontSize;
            updateFontSize();
            
            // Восстанавливаем тему
            const savedTheme = localStorage.getItem('reader_theme') || 'light';
            document.body.classList.add('theme-' + savedTheme);
            document.querySelector(`.theme-option[data-theme="${savedTheme}"]`).classList.add('active');
            
            // Автоматическое сохранение прогресса при прокрутке
            let scrollTimeout;
            window.addEventListener('scroll', function() {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(function() {
                    saveProgress(false);
                }, 1000);
                
                // Обновляем индикатор прогресса при прокрутке
                updateProgressIndicator();
            });
            
            // Автоматическое сохранение прогресса перед закрытием страницы
            window.addEventListener('beforeunload', function() {
                saveProgress(false);
            });
            
            // Обработка изменения размера окна
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    // Обновляем индикатор прогресса при изменении размера окна
                    updateProgressIndicator();
                }, 300);
            });
            
            // Функции
            function updateFontSize() {
                currentPageElement.style.fontSize = `${fontSize}px`;
                nextPageElement.style.fontSize = `${fontSize}px`;
                fontSizeValue.textContent = fontSize;
                localStorage.setItem('reader_font_size', fontSize);
            }
            
            function toggleTwoPageMode() {
                isTwoPageMode = !isTwoPageMode;
                
                if (isTwoPageMode) {
                    document.body.classList.add('two-page-mode');
                    nextPageElement.style.display = 'block';
                    layoutToggle.innerHTML = '<i class="fas fa-book-open"></i>';
                } else {
                    document.body.classList.remove('two-page-mode');
                    nextPageElement.style.display = 'none';
                    layoutToggle.innerHTML = '<i class="fas fa-columns"></i>';
                }
                
                localStorage.setItem('reader_two_page_mode', isTwoPageMode ? '1' : '0');
            }
            
            function toggleTocModal() {
                tocModal.classList.toggle('open');
            }
            
            function goToPage(pageNum) {
                window.location.href = `reader_fix.php?id=${bookId}&page=${pageNum}`;
            }
            
            function goToChapter(chapterId) {
                // Находим элемент главы
                const chapterElement = document.getElementById(chapterId);
                if (chapterElement) {
                    // Прокручиваем к элементу
                    chapterElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    
                    // Находим номер главы
                    const chapterNum = parseInt(chapterId.replace('chapter_', ''));
                    
                    // Обновляем активную главу в оглавлении
                    tocItems.forEach(item => {
                        item.classList.remove('active');
                        if (parseInt(item.getAttribute('data-page')) === chapterNum) {
                            item.classList.add('active');
                        }
                    });
                    
                    // Обновляем индикатор прогресса
                    updateProgressIndicator();
                }
            }
            
            function saveProgress(showMessage = true) {
                // Получаем текущую позицию прокрутки
                const scrollPosition = window.scrollY || document.documentElement.scrollTop;
                
                fetch('save_progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        book_id: bookId,
                        page: <?php echo $currentPage; ?>,
                        scroll_position: scrollPosition,
                        last_page_text: ''
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (showMessage && data.success) {
                        alert('Позиция сохранена!');
                    }
                })
                .catch(error => {
                    console.error('Ошибка сохранения прогресса:', error);
                });
            }
            
            function updateProgressIndicator() {
                // Получаем текущую позицию прокрутки
                const scrollPosition = window.scrollY || document.documentElement.scrollTop;
                
                // Получаем текущий процент прокрутки
                const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
                const scrollPercentage = scrollHeight > 0 ? (scrollPosition / scrollHeight) * 100 : 0;
                
                // Обновляем индикатор прогресса
                progressFill.style.width = `${scrollPercentage}%`;
                progressText.textContent = `${Math.round(scrollPercentage)}%`;
            }
            
            function updatePageInfo() {
                // Обновляем информацию о страницах
                currentPageNum.textContent = <?php echo $currentPage; ?>;
                totalPagesElement.textContent = <?php echo $totalChapters; ?>;
            }
            
            // Обработчики событий
            backButton.addEventListener('click', function() {
                saveProgress(false);
                window.location.href = 'index.php';
            });
            
            fontButton.addEventListener('click', function() {
                settingsModal.style.display = settingsModal.style.display === 'block' ? 'none' : 'block';
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
                if (<?php echo $currentPage; ?> > 1) {
                    goToPage(<?php echo $currentPage; ?> - 1);
                }
            });
            
            nextPageBtn.addEventListener('click', function() {
                if (<?php echo $currentPage; ?> < <?php echo $totalChapters; ?>) {
                    goToPage(<?php echo $currentPage; ?> + 1);
                }
            });
            
            // Обработчики для областей перемотки страниц
            prevPageArea.addEventListener('click', function() {
                if (<?php echo $currentPage; ?> > 1) {
                    goToPage(<?php echo $currentPage; ?> - 1);
                }
            });
            
            nextPageArea.addEventListener('click', function() {
                if (<?php echo $currentPage; ?> < <?php echo $totalChapters; ?>) {
                    goToPage(<?php echo $currentPage; ?> + 1);
                }
            });
            
            // Показываем индикаторы перемотки при наведении
            prevPageArea.addEventListener('mouseenter', function() {
                this.querySelector('.page-turn-indicator').style.opacity = '1';
            });
            
            prevPageArea.addEventListener('mouseleave', function() {
                this.querySelector('.page-turn-indicator').style.opacity = '0';
            });
            
            nextPageArea.addEventListener('mouseenter', function() {
                this.querySelector('.page-turn-indicator').style.opacity = '1';
            });
            
            nextPageArea.addEventListener('mouseleave', function() {
                this.querySelector('.page-turn-indicator').style.opacity = '0';
            });
            
            saveBtn.addEventListener('click', function() {
                saveProgress(true);
            });
            
            tocButton.addEventListener('click', function() {
                toggleTocModal();
            });
            
            tocClose.addEventListener('click', function() {
                toggleTocModal();
            });
            
            tocItems.forEach(item => {
                item.addEventListener('click', function() {
                    const elementId = this.getAttribute('data-element-id');
                    if (elementId) {
                        goToChapter(elementId);
                    }
                    tocModal.classList.remove('open');
                }); 
            });
            
            // Закрытие модального окна при клике вне его
            document.addEventListener('click', function(event) {
                if (settingsModal.style.display === 'block' && 
                    !settingsModal.contains(event.target) && 
                    event.target !== fontButton) {
                    settingsModal.style.display = 'none';
                }
                
                if (tocModal.classList.contains('open') && 
                    !tocModal.contains(event.target) && 
                    event.target !== tocButton) {
                    tocModal.classList.remove('open');
                }
            });
            
            // Обработка клавиш
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    if (isFullscreen) {
                        if (document.exitFullscreen) {
                            document.exitFullscreen().then(() => {
                                isFullscreen = false;
                                document.body.classList.remove('fullscreen-mode');
                                fullscreenButton.innerHTML = '<i class="fas fa-expand"></i>';
                            });
                        }
                    }
                    
                    if (tocModal.classList.contains('open')) {
                        tocModal.classList.remove('open');
                    }
                    
                    if (settingsModal.style.display === 'block') {
                        settingsModal.style.display = 'none';
                    }
                } else if (event.key === 'ArrowLeft') {
                    if (<?php echo $currentPage; ?> > 1) {
                        goToPage(<?php echo $currentPage; ?> - 1);
                    }
                } else if (event.key === 'ArrowRight') {
                    if (<?php echo $currentPage; ?> < <?php echo $totalChapters; ?>) {
                        goToPage(<?php echo $currentPage; ?> + 1);
                    }
                } else if (event.key === 'f' && event.ctrlKey) {
                    // Ctrl+F для полноэкранного режима
                    event.preventDefault();
                    fullscreenButton.click();
                } else if (event.key === 'd' && event.ctrlKey) {
                    // Ctrl+D для двухстраничного режима
                    event.preventDefault();
                    layoutToggle.click();
                } else if (event.key === 'o' && event.ctrlKey) {
                    // Ctrl+O для оглавления
                    event.preventDefault();
                    tocButton.click();
                }
            });
            
            // Автоматическое сохранение прогресса при загрузке страницы
            window.addEventListener('load', function() {
                // Восстанавливаем позицию прокрутки
                window.scrollTo(0, <?php echo $progress['scroll_position']; ?>);
                
                // Восстанавливаем режим отображения
                if (localStorage.getItem('reader_two_page_mode') === '1') {
                    toggleTwoPageMode();
                }
                
                // Обновляем информацию о страницах
                updatePageInfo();
                
                // Обновляем индикатор прогресса
                updateProgressIndicator();
                
                // Автоматически сохраняем прогресс
                setTimeout(function() {
                    saveProgress(false);
                }, 2000);
            });
        });
    </script>
</body>
</html> 