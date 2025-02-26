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
            </div>
        </div>
        
        <!-- Нижняя панель -->
        <footer class="reader-footer">
            <div class="pagination">
                <button class="pagination-btn" id="prev-page-btn">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="pagination-info">
                    <span id="current-page-num"><?php echo $currentPage; ?></span> из <span id="total-pages">...</span>
                </div>
                <button class="pagination-btn" id="next-page-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 0%"></div>
                </div>
                <div class="progress-text">0%</div>
            </div>
            <button class="save-btn" id="save-btn">
                <i class="fas fa-bookmark"></i> Сохранить
            </button>
        </footer>
        
        <!-- Модальное окно настроек -->
        <div class="settings-modal" id="settings-modal" style="display: none;">
            <h3>Размер шрифта</h3>
            <div class="font-size-controls">
                <button class="font-size-btn" id="decrease-font">A-</button>
                <span id="font-size-value">18</span>
                <button class="font-size-btn" id="increase-font">A+</button>
            </div>
            <h3>Тема</h3>
            <div class="theme-options">
                <div class="theme-option" data-theme="light">
                    <div class="theme-preview light"></div>
                    <span>Светлая</span>
                </div>
                <div class="theme-option" data-theme="sepia">
                    <div class="theme-preview sepia"></div>
                    <span>Сепия</span>
                </div>
                <div class="theme-option" data-theme="dark">
                    <div class="theme-preview dark"></div>
                    <span>Темная</span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Элементы интерфейса
            const backButton = document.getElementById('back-button');
            const fontButton = document.getElementById('font-button');
            const fullscreenButton = document.getElementById('fullscreen-button');
            const layoutToggle = document.getElementById('layout-toggle');
            const prevPageBtn = document.getElementById('prev-page-btn');
            const nextPageBtn = document.getElementById('next-page-btn');
            const saveBtn = document.getElementById('save-btn');
            const settingsModal = document.getElementById('settings-modal');
            const decreaseFontBtn = document.getElementById('decrease-font');
            const increaseFontBtn = document.getElementById('increase-font');
            const fontSizeValue = document.getElementById('font-size-value');
            const themeOptions = document.querySelectorAll('.theme-option');
            const currentPageNum = document.getElementById('current-page-num');
            const totalPagesElement = document.getElementById('total-pages');
            const progressFill = document.querySelector('.progress-fill');
            const progressText = document.querySelector('.progress-text');
            const tocButton = document.getElementById('toc-button');
            const tocModal = document.getElementById('toc-modal');
            const tocClose = document.getElementById('toc-close');
            const tocItems = document.querySelectorAll('.toc-item');
            const bookContent = document.getElementById('book-content');
            const currentPageElement = document.getElementById('current-page');
            const nextPageElement = document.getElementById('next-page');
            
            // Переменные
            const bookId = <?php echo $bookId; ?>;
            const userId = <?php echo $_SESSION['user_id']; ?>;
            let currentPageIndex = <?php echo $currentPage; ?>;
            let totalPagesCount = 0;
            let fontSize = parseInt(localStorage.getItem('reader_font_size')) || 18;
            let isFullscreen = false;
            let isTwoPageMode = localStorage.getItem('reader_two_page_mode') === '1';
            let virtualPages = [];
            let currentVirtualPage = 1;
            
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
            
            // Инициализация
            updateFontSize();
            updateProgressIndicator();
            updatePageInfo();
            
            // Восстанавливаем тему
            const savedTheme = localStorage.getItem('reader_theme') || 'light';
            document.body.classList.add('theme-' + savedTheme);
            document.querySelector(`.theme-option[data-theme="${savedTheme}"]`).classList.add('active');
            
            // Если есть ID главы в URL, переходим к ней
            const urlParams = new URLSearchParams(window.location.search);
            const chapterId = urlParams.get('chapter');
            if (chapterId) {
                goToChapter('chapter_' + chapterId);
            }
            
            // Функции
            function initializePages() {
                // Создаем виртуальный контейнер для расчета страниц
                const virtualContainer = document.createElement('div');
                virtualContainer.style.position = 'absolute';
                virtualContainer.style.visibility = 'hidden';
                virtualContainer.style.width = currentPageElement.clientWidth + 'px';
                virtualContainer.style.height = currentPageElement.clientHeight + 'px';
                virtualContainer.style.overflow = 'hidden';
                virtualContainer.style.fontSize = fontSize + 'px';
                document.body.appendChild(virtualContainer);
                
                // Клонируем содержимое книги
                virtualContainer.innerHTML = currentPageElement.innerHTML;
                
                // Получаем все параграфы
                const paragraphs = Array.from(virtualContainer.querySelectorAll('p, h1, h2, h3, h4, h5, h6, div.title, div.subtitle'));
                
                // Создаем виртуальные страницы
                let currentVirtualPage = document.createElement('div');
                currentVirtualPage.className = 'virtual-page';
                virtualPages = [];
                
                // Высота страницы с учетом отступов
                const pageHeight = currentPageElement.clientHeight - 80; // Отступы сверху и снизу
                let currentHeight = 0;
                
                // Проходим по всем параграфам
                for (let i = 0; i < paragraphs.length; i++) {
                    const paragraph = paragraphs[i];
                    const paragraphHeight = paragraph.offsetHeight;
                    
                    // Если параграф не помещается на текущую страницу, создаем новую
                    if (currentHeight + paragraphHeight > pageHeight && currentHeight > 0) {
                        virtualPages.push(currentVirtualPage);
                        currentVirtualPage = document.createElement('div');
                        currentVirtualPage.className = 'virtual-page';
                        currentHeight = 0;
                    }
                    
                    // Клонируем параграф и добавляем на текущую страницу
                    const clonedParagraph = paragraph.cloneNode(true);
                    currentVirtualPage.appendChild(clonedParagraph);
                    currentHeight += paragraphHeight;
                }
                
                // Добавляем последнюю страницу
                if (currentVirtualPage.childNodes.length > 0) {
                    virtualPages.push(currentVirtualPage);
                }
                
                // Обновляем общее количество страниц
                totalPagesCount = virtualPages.length;
                totalPagesElement.textContent = totalPagesCount;
                
                // Очищаем виртуальный контейнер
                document.body.removeChild(virtualContainer);
                
                // Создаем реальные страницы
                currentPageElement.innerHTML = '';
                for (let i = 0; i < virtualPages.length; i++) {
                    const pageDiv = document.createElement('div');
                    pageDiv.className = 'page-content';
                    pageDiv.id = 'page-' + (i + 1);
                    pageDiv.innerHTML = virtualPages[i].innerHTML;
                    currentPageElement.appendChild(pageDiv);
                }
                
                // Показываем текущую страницу
                showVirtualPage(currentVirtualPage);
                
                // Обновляем индикатор прогресса
                updateProgressIndicator();
                updatePageInfo();
            }
            
            function showVirtualPage(pageIndex) {
                // Скрываем все страницы
                const pageContents = document.querySelectorAll('.page-content');
                pageContents.forEach(page => {
                    page.classList.remove('active');
                });
                
                // Показываем нужную страницу
                const targetPage = document.getElementById('page-' + pageIndex);
                if (targetPage) {
                    targetPage.classList.add('active');
                    currentVirtualPage = pageIndex;
                    currentPageNum.textContent = pageIndex;
                    
                    // Обновляем индикатор прогресса
                    updateProgressIndicator();
                }
                
                // Если включен двухстраничный режим, показываем следующую страницу
                if (isTwoPageMode) {
                    nextPageElement.innerHTML = '';
                    const nextPageContent = document.getElementById('page-' + (pageIndex + 1));
                    if (nextPageContent) {
                        nextPageElement.innerHTML = nextPageContent.innerHTML;
                    }
                }
            }
            
            function updateFontSize() {
                currentPageElement.style.fontSize = `${fontSize}px`;
                nextPageElement.style.fontSize = `${fontSize}px`;
                fontSizeValue.textContent = fontSize;
                localStorage.setItem('reader_font_size', fontSize);
                
                // Пересчитываем страницы при изменении размера шрифта
                initializePages();
            }
            
            function toggleTwoPageMode() {
                isTwoPageMode = !isTwoPageMode;
                
                if (isTwoPageMode) {
                    document.body.classList.add('two-page-mode');
                    nextPageElement.style.display = 'block';
                    layoutToggle.innerHTML = '<i class="fas fa-book-open"></i>';
                    
                    // Показываем следующую страницу
                    nextPageElement.innerHTML = '';
                    const nextPageContent = document.getElementById('page-' + (currentVirtualPage + 1));
                    if (nextPageContent) {
                        nextPageElement.innerHTML = nextPageContent.innerHTML;
                    }
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
                if (pageNum < 1 || pageNum > totalPagesCount) return;
                
                showVirtualPage(pageNum);
                saveProgress(false);
            }
            
            function goToChapter(chapterId) {
                // Находим элемент главы
                const chapterElement = document.getElementById(chapterId);
                if (chapterElement) {
                    // Находим страницу, на которой находится глава
                    const pageContents = document.querySelectorAll('.page-content');
                    for (let i = 0; i < pageContents.length; i++) {
                        if (pageContents[i].contains(chapterElement)) {
                            goToPage(i + 1);
                            break;
                        }
                    }
                }
            }
            
            function saveProgress(showMessage = true) {
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