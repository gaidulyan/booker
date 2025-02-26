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
                <span class="page-info">
                    <span id="current-page-num"><?php echo $currentPage; ?></span> из <span id="total-pages"><?php echo $totalChapters; ?></span>
                </span>
                <button class="pagination-btn" id="next-page-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <span class="progress-text" id="progress-text">0%</span>
            </div>
            <div class="actions">
                <button class="action-btn" id="save-btn">
                    <i class="fas fa-save"></i> Сохранить
                </button>
            </div>
        </footer>
    </div>
    
    <!-- Модальное окно настроек -->
    <div class="settings-modal" id="settings-modal">
        <div class="settings-header">
            <span class="settings-title">Настройки</span>
            <button class="settings-close" id="settings-close">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="settings-content">
            <div class="settings-section">
                <h3>Размер шрифта</h3>
                <div class="font-size-controls">
                    <button class="font-size-btn" id="font-decrease">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="font-size-value" id="font-size-value">16</span>
                    <button class="font-size-btn" id="font-increase">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
            </div>
            <div class="settings-section">
                <h3>Тема</h3>
                <div class="theme-controls">
                    <button class="theme-btn active" id="light-theme-btn" data-theme="light">
                        <i class="fas fa-sun"></i> Светлая
                    </button>
                    <button class="theme-btn" id="dark-theme-btn" data-theme="dark">
                        <i class="fas fa-moon"></i> Темная
                    </button>
                    <button class="theme-btn" id="sepia-theme-btn" data-theme="sepia">
                        <i class="fas fa-book"></i> Сепия
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Получаем элементы DOM
            const backButton = document.getElementById('back-button');
            const tocButton = document.getElementById('toc-button');
            const tocCloseButton = document.getElementById('toc-close');
            const tocModal = document.getElementById('toc-modal');
            const tocItems = document.querySelectorAll('.toc-item');
            const fontButton = document.getElementById('font-button');
            const settingsModal = document.getElementById('settings-modal');
            const settingsCloseButton = document.getElementById('settings-close');
            const fontDecreaseButton = document.getElementById('font-decrease');
            const fontIncreaseButton = document.getElementById('font-increase');
            const fontSizeValue = document.getElementById('font-size-value');
            const lightThemeBtn = document.getElementById('light-theme-btn');
            const darkThemeBtn = document.getElementById('dark-theme-btn');
            const sepiaThemeBtn = document.getElementById('sepia-theme-btn');
            const layoutToggle = document.getElementById('layout-toggle');
            const fullscreenButton = document.getElementById('fullscreen-button');
            const currentPageElement = document.getElementById('current-page');
            const nextPageElement = document.getElementById('next-page');
            const prevPageBtn = document.getElementById('prev-page-btn');
            const nextPageBtn = document.getElementById('next-page-btn');
            const currentPageNum = document.getElementById('current-page-num');
            const totalPagesElement = document.getElementById('total-pages');
            const progressFill = document.getElementById('progress-fill');
            const progressText = document.getElementById('progress-text');
            const saveBtn = document.getElementById('save-btn');
            const prevPageArea = document.getElementById('prev-page-area');
            const nextPageArea = document.getElementById('next-page-area');
            
            // Инициализация переменных
            let virtualPages = [];
            let currentPageIndex = <?php echo $currentPage; ?>;
            let totalPagesCount = <?php echo $totalChapters; ?>;
            let fontSize = parseInt(localStorage.getItem('reader_font_size')) || 16;
            let currentTheme = localStorage.getItem('reader_theme') || 'light';
            let isTwoPageMode = localStorage.getItem('reader_two_page_mode') === '1';
            let isFullscreen = false;
            let touchStartX = 0;
            let touchEndX = 0;
            let scrollTimeout;
            let resizeTimeout;
            let wheelTimeout;
            
            // Обработчики событий
            backButton.addEventListener('click', function() {
                window.location.href = 'index.php';
            });
            
            tocButton.addEventListener('click', function() {
                tocModal.classList.toggle('open');
            });
            
            tocCloseButton.addEventListener('click', function() {
                tocModal.classList.remove('open');
            });
            
            fontButton.addEventListener('click', function() {
                settingsModal.style.display = settingsModal.style.display === 'block' ? 'none' : 'block';
            });
            
            settingsCloseButton.addEventListener('click', function() {
                settingsModal.style.display = 'none';
            });
            
            layoutToggle.addEventListener('click', function() {
                toggleTwoPageMode();
            });
            
            fullscreenButton.addEventListener('click', function() {
                toggleFullscreen();
            });
            
            fontDecreaseButton.addEventListener('click', function() {
                if (fontSize > 8) {
                    fontSize -= 2;
                    updateFontSize();
                }
            });
            
            fontIncreaseButton.addEventListener('click', function() {
                if (fontSize < 32) {
                    fontSize += 2;
                    updateFontSize();
                }
            });
            
            lightThemeBtn.addEventListener('click', function() {
                setTheme('light');
            });
            
            darkThemeBtn.addEventListener('click', function() {
                setTheme('dark');
            });
            
            sepiaThemeBtn.addEventListener('click', function() {
                setTheme('sepia');
            });
            
            // Обработчики для кнопок навигации
            prevPageBtn.addEventListener('click', function() {
                console.log('Нажата кнопка "Предыдущая страница"');
                if (currentPageIndex > 1) {
                    goToPage(currentPageIndex - 1);
                }
            });
            
            nextPageBtn.addEventListener('click', function() {
                console.log('Нажата кнопка "Следующая страница"');
                if (currentPageIndex < totalPagesCount) {
                    goToPage(currentPageIndex + 1);
                }
            });
            
            // Обработчики для областей перемотки страниц
            prevPageArea.addEventListener('click', function() {
                console.log('Нажата область "Предыдущая страница"');
                if (currentPageIndex > 1) {
                    goToPage(currentPageIndex - 1);
                }
            });
            
            nextPageArea.addEventListener('click', function() {
                console.log('Нажата область "Следующая страница"');
                if (currentPageIndex < totalPagesCount) {
                    goToPage(currentPageIndex + 1);
                }
            });
            
            // Обработчик для кнопки сохранения
            saveBtn.addEventListener('click', function() {
                saveProgress(true);
            });
            
            tocItems.forEach(function(item) {
                item.addEventListener('click', function() {
                    const elementId = this.getAttribute('data-element-id');
                    if (elementId) {
                        goToChapter(elementId);
                    }
                    tocModal.classList.remove('open');
                });
            });
            
            // Функция для обновления размера шрифта
            function updateFontSize() {
                document.body.style.fontSize = fontSize + 'px';
                fontSizeValue.textContent = fontSize;
                localStorage.setItem('reader_font_size', fontSize);
                
                // Пересчитываем виртуальные страницы при изменении размера шрифта
                setTimeout(function() {
                    initializeVirtualPages();
                }, 300);
            }
            
            // Функция для установки темы
            function setTheme(theme) {
                document.body.classList.remove('light-theme', 'dark-theme', 'sepia-theme');
                document.body.classList.add(theme + '-theme');
                
                lightThemeBtn.classList.remove('active');
                darkThemeBtn.classList.remove('active');
                sepiaThemeBtn.classList.remove('active');
                
                if (theme === 'light') {
                    lightThemeBtn.classList.add('active');
                } else if (theme === 'dark') {
                    darkThemeBtn.classList.add('active');
                } else if (theme === 'sepia') {
                    sepiaThemeBtn.classList.add('active');
                }
                
                currentTheme = theme;
                localStorage.setItem('reader_theme', theme);
            }
            
            // Функция для переключения двухстраничного режима
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
            
            // Функция для переключения полноэкранного режима
            function toggleFullscreen() {
                if (!isFullscreen) {
                    if (document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen();
                        isFullscreen = true;
                        document.body.classList.add('fullscreen-mode');
                        fullscreenButton.innerHTML = '<i class="fas fa-compress"></i>';
                    }
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                        isFullscreen = false;
                        document.body.classList.remove('fullscreen-mode');
                        fullscreenButton.innerHTML = '<i class="fas fa-expand"></i>';
                    }
                }
            }
            
            // Функция для сохранения прогресса
            function saveProgress(showMessage = true) {
                // Получаем текущую позицию прокрутки
                const scrollPosition = window.scrollY || document.documentElement.scrollTop;
                
                // Сохраняем прогресс на сервере
                fetch('save_progress.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: <?php echo $_SESSION['user_id']; ?>,
                        book_id: <?php echo $bookId; ?>,
                        page: currentPageIndex,
                        scroll_position: scrollPosition,
                        last_page_text: ''
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (showMessage && data.success) {
                        alert('Прогресс сохранен!');
                    }
                })
                .catch(error => {
                    console.error('Ошибка сохранения прогресса:', error);
                });
            }
            
            // Функция для обновления индикатора прогресса
            function updateProgressIndicator() {
                // Получаем текущую позицию прокрутки
                const scrollPosition = window.scrollY || document.documentElement.scrollTop;
                
                // Получаем общую высоту прокрутки
                const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
                
                // Вычисляем процент прокрутки
                const scrollPercentage = scrollHeight > 0 ? (scrollPosition / scrollHeight) * 100 : 0;
                
                // Обновляем индикатор прогресса
                progressFill.style.width = `${scrollPercentage}%`;
                progressText.textContent = `${Math.round(scrollPercentage)}%`;
            }
            
            // Функция для обновления информации о страницах
            function updatePageInfo() {
                currentPageNum.textContent = currentPageIndex;
                totalPagesElement.textContent = totalPagesCount;
            }
            
            // Функция для перехода к главе
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
                    
                    // Обновляем текущую страницу
                    currentPageIndex = chapterNum;
                    
                    // Обновляем URL без перезагрузки страницы
                    const url = new URL(window.location.href);
                    url.searchParams.set('page', chapterNum);
                    url.searchParams.set('chapter', chapterNum);
                    window.history.pushState({ page: chapterNum, chapter: chapterNum }, '', url.toString());
                    
                    // Обновляем информацию о страницах
                    updatePageInfo();
                    
                    // Обновляем индикатор прогресса
                    setTimeout(updateProgressIndicator, 500);
                    
                    // Сохраняем прогресс
                    setTimeout(function() {
                        saveProgress(false);
                    }, 1000);
                }
            }
            
            // Функция для перехода к странице
            function goToPage(pageIndex) {
                if (pageIndex < 1 || pageIndex > totalPagesCount) {
                    return;
                }
                
                console.log('Переход к странице:', pageIndex);
                
                // Обновляем текущую страницу
                currentPageIndex = pageIndex;
                
                // Если pageIndex меньше или равно количеству глав, переходим к главе
                if (pageIndex <= <?php echo $totalChapters; ?>) {
                    console.log('Переход к главе:', pageIndex);
                    
                    const chapterId = 'chapter_' + pageIndex;
                    const chapterElement = document.getElementById(chapterId);
                    
                    if (chapterElement) {
                        // Прокручиваем к элементу
                        chapterElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        
                        // Обновляем активную главу в оглавлении
                        tocItems.forEach(item => {
                            item.classList.remove('active');
                            if (parseInt(item.getAttribute('data-page')) === pageIndex) {
                                item.classList.add('active');
                            }
                        });
                    } else {
                        console.error('Элемент главы не найден:', chapterId);
                    }
                } else {
                    // Переходим к виртуальной странице
                    console.log('Переход к виртуальной странице');
                    
                    // Находим все параграфы
                    const paragraphs = Array.from(currentPageElement.querySelectorAll('p'));
                    
                    // Определяем общее количество параграфов
                    const totalParagraphs = paragraphs.length;
                    
                    // Определяем количество параграфов на страницу
                    // Делим общее количество параграфов на количество виртуальных страниц
                    const virtualPagesCount = totalPagesCount - <?php echo $totalChapters; ?>;
                    const paragraphsPerPage = Math.ceil(totalParagraphs / virtualPagesCount);
                    
                    // Вычисляем индекс виртуальной страницы (начиная с 0)
                    const virtualPageIndex = pageIndex - <?php echo $totalChapters; ?> - 1;
                    
                    // Вычисляем индекс первого параграфа на странице
                    const startIndex = virtualPageIndex * paragraphsPerPage;
                    
                    console.log('Всего параграфов:', totalParagraphs);
                    console.log('Виртуальных страниц:', virtualPagesCount);
                    console.log('Параграфов на страницу:', paragraphsPerPage);
                    console.log('Индекс виртуальной страницы:', virtualPageIndex);
                    console.log('Индекс первого параграфа:', startIndex);
                    
                    // Если индекс корректный, прокручиваем к этому параграфу
                    if (startIndex >= 0 && startIndex < totalParagraphs) {
                        console.log('Прокрутка к параграфу:', startIndex);
                        paragraphs[startIndex].scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else if (totalParagraphs > 0) {
                        // Если индекс некорректный, переходим к последнему параграфу
                        console.log('Прокрутка к последнему параграфу');
                        paragraphs[totalParagraphs - 1].scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
                
                // Обновляем URL без перезагрузки страницы
                const url = new URL(window.location.href);
                url.searchParams.set('page', pageIndex);
                window.history.pushState({ page: pageIndex }, '', url.toString());
                
                // Обновляем информацию о страницах
                updatePageInfo();
                
                // Обновляем индикатор прогресса
                setTimeout(updateProgressIndicator, 500);
                
                // Сохраняем прогресс
                setTimeout(function() {
                    saveProgress(false);
                }, 1000);
            }
            
            // Обработчик свайпов для мобильных устройств
            document.addEventListener('touchstart', function(event) {
                touchStartX = event.changedTouches[0].screenX;
            }, false);
            
            document.addEventListener('touchend', function(event) {
                touchEndX = event.changedTouches[0].screenX;
                
                const swipeThreshold = 100; // Минимальное расстояние для свайпа
                
                if (touchEndX < touchStartX - swipeThreshold) {
                    // Свайп влево - следующая страница
                    if (currentPageIndex < totalPagesCount) {
                        goToPage(currentPageIndex + 1);
                    }
                }
                
                if (touchEndX > touchStartX + swipeThreshold) {
                    // Свайп вправо - предыдущая страница
                    if (currentPageIndex > 1) {
                        goToPage(currentPageIndex - 1);
                    }
                }
            }, false);
            
            // Обработчик прокрутки для обновления индикатора прогресса
            window.addEventListener('scroll', function() {
                updateProgressIndicator();
            });
            
            // Обработчик клавиш
            document.addEventListener('keydown', function(event) {
                if (event.key === 'ArrowLeft') {
                    if (currentPageIndex > 1) {
                        goToPage(currentPageIndex - 1);
                    }
                } else if (event.key === 'ArrowRight') {
                    if (currentPageIndex < totalPagesCount) {
                        goToPage(currentPageIndex + 1);
                    }
                }
            });
            
            // Восстанавливаем настройки
            updateFontSize();
            setTheme(currentTheme);
            
            if (isTwoPageMode) {
                toggleTwoPageMode();
            }
            
            // Восстанавливаем позицию прокрутки
            window.scrollTo(0, <?php echo $progress['scroll_position']; ?>);
            
            // Инициализация виртуальных страниц
            initializeVirtualPages();
            
            // Обновляем индикатор прогресса
            updateProgressIndicator();
            
            // Автоматически сохраняем прогресс
            setTimeout(function() {
                saveProgress(false);
            }, 2000);
            
            // Если есть ID главы в URL, переходим к ней
            const urlParams = new URLSearchParams(window.location.search);
            const chapterId = urlParams.get('chapter');
            if (chapterId) {
                goToChapter('chapter_' + chapterId);
            }
            
            // Добавляем обработчик изменения размера окна
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    initializeVirtualPages();
                }, 300);
            });
            
            // Функция для разбиения книги на виртуальные страницы
            function initializeVirtualPages() {
                // Получаем все параграфы
                const paragraphs = Array.from(currentPageElement.querySelectorAll('p'));
                const totalParagraphs = paragraphs.length;
                
                // Фиксированное количество виртуальных страниц (например, 10)
                const desiredVirtualPages = 10;
                
                // Вычисляем количество виртуальных страниц
                // Минимум 1, максимум desiredVirtualPages
                const virtualPagesCount = Math.min(desiredVirtualPages, Math.max(1, Math.ceil(totalParagraphs / 20)));
                
                // Обновляем общее количество страниц
                totalPagesCount = <?php echo $totalChapters; ?> + virtualPagesCount;
                
                console.log('Всего параграфов:', totalParagraphs);
                console.log('Количество виртуальных страниц:', virtualPagesCount);
                console.log('Общее количество страниц:', totalPagesCount);
                
                // Обновляем информацию о страницах
                updatePageInfo();
            }
        });
    </script>
</body>
</html> 