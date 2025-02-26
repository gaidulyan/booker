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

// Разбиваем книгу на страницы
$contentPages = [];
$paragraphs = $xpath->query('//p');
$pageContent = '';
$paragraphCount = 0;
$wordsPerPage = 250; // Количество слов на странице
$wordCount = 0;

foreach ($paragraphs as $paragraph) {
    $paragraphText = $dom->saveHTML($paragraph);
    $paragraphWords = str_word_count(strip_tags($paragraphText));
    
    // Если добавление этого параграфа превысит лимит слов на странице, создаем новую страницу
    if ($wordCount > 0 && ($wordCount + $paragraphWords) > $wordsPerPage) {
        $contentPages[] = $pageContent;
        $pageContent = $paragraphText;
        $wordCount = $paragraphWords;
    } else {
        $pageContent .= $paragraphText;
        $wordCount += $paragraphWords;
    }
}

// Добавляем последнюю страницу
if (!empty($pageContent)) {
    $contentPages[] = $pageContent;
}

// Если страниц нет, используем весь контент как одну страницу
if (empty($contentPages)) {
    $contentPages[] = $book['content'];
}

// Общее количество страниц
$totalPages = count($contentPages);

// Если текущая страница больше общего количества, устанавливаем последнюю
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

// Определяем, к какой главе относится текущая страница
$currentChapter = null;
$chapterPageMap = [];
$lastChapterIndex = 0;

// Создаем карту соответствия страниц и глав
foreach ($contentPages as $pageIndex => $pageContent) {
    $pageDom = new DOMDocument();
    @$pageDom->loadHTML('<?xml encoding="UTF-8">' . $pageContent);
    $pageXpath = new DOMXPath($pageDom);
    
    // Проверяем, есть ли на этой странице заголовок главы
    $pageHeadings = $pageXpath->query('//h1|//h2|//h3|//h4|//div[@class="title"]|//div[@class="subtitle"]');
    
    if ($pageHeadings->length > 0) {
        foreach ($pageHeadings as $heading) {
            $headingText = trim($heading->textContent);
            
            // Ищем соответствующую главу в общем списке
            foreach ($chapters as $chapter) {
                if ($chapter['title'] === $headingText) {
                    $chapterPageMap[$pageIndex + 1] = $chapter['id'];
                    $lastChapterIndex = $chapter['id'];
                    break;
                }
            }
        }
    } else {
        // Если на странице нет заголовка, используем последний известный
        $chapterPageMap[$pageIndex + 1] = $lastChapterIndex;
    }
    
    // Если текущая страница, запоминаем главу
    if ($pageIndex + 1 === $currentPage && isset($chapterPageMap[$currentPage])) {
        foreach ($chapters as $chapter) {
            if ($chapter['id'] === $chapterPageMap[$currentPage]) {
                $currentChapter = $chapter;
                break;
            }
        }
    }
}

// Получаем содержимое текущей страницы
$currentPageContent = isset($contentPages[$currentPage - 1]) ? $contentPages[$currentPage - 1] : '';
$nextPageContent = isset($contentPages[$currentPage]) ? $contentPages[$currentPage] : '';
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
                <?php foreach ($chapters as $chapter): 
                    // Находим первую страницу, на которой встречается эта глава
                    $chapterFirstPage = array_search($chapter['id'], $chapterPageMap);
                    if ($chapterFirstPage === false) $chapterFirstPage = 1;
                ?>
                <li class="toc-item <?php echo ($currentChapter && $chapter['id'] == $currentChapter['id']) ? 'active' : ''; ?>" 
                    data-page="<?php echo $chapterFirstPage; ?>" 
                    data-element-id="<?php echo $chapter['element_id']; ?>">
                    <?php echo htmlspecialchars($chapter['title']); ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <!-- Контейнер для книги -->
        <div class="book-container">
            <div class="book">
                <div class="book-content">
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
            <div class="pagination">
                <button class="pagination-btn" id="prev-page">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span class="pagination-info">
                    Страница <span id="current-page-num"><?php echo $currentPage; ?></span> из <span id="total-pages"><?php echo $totalPages; ?></span>
                </span>
                <button class="pagination-btn" id="next-page-btn">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($totalPages > 0) ? ($currentPage / $totalPages * 100) : 0; ?>%"></div>
                </div>
                <span class="progress-text"><?php echo ($totalPages > 0) ? round($currentPage / $totalPages * 100) : 0; ?>%</span>
            </div>
            <button class="save-btn" id="save-btn">Сохранить позицию</button>
        </footer>
        
        <!-- Модальное окно настроек -->
        <div class="settings-modal" id="settings-modal">
            <h3>Настройки чтения</h3>
            <div class="font-size-control">
                <button class="font-size-btn" id="decrease-font">-</button>
                <span class="font-size-value" id="font-size-value">18</span>
                <button class="font-size-btn" id="increase-font">+</button>
            </div>
            <h3>Тема</h3>
            <div class="theme-options">
                <div class="theme-option theme-light active" data-theme="light"></div>
                <div class="theme-option theme-sepia" data-theme="sepia"></div>
                <div class="theme-option theme-dark" data-theme="dark"></div>
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
            const prevPageBtn = document.getElementById('prev-page');
            const nextPageBtn = document.getElementById('next-page-btn');
            const saveBtn = document.getElementById('save-btn');
            const settingsModal = document.getElementById('settings-modal');
            const decreaseFontBtn = document.getElementById('decrease-font');
            const increaseFontBtn = document.getElementById('increase-font');
            const fontSizeValue = document.getElementById('font-size-value');
            const themeOptions = document.querySelectorAll('.theme-option');
            const currentPageElement = document.getElementById('current-page');
            const nextPageElement = document.getElementById('next-page');
            const progressFill = document.querySelector('.progress-fill');
            const progressText = document.querySelector('.progress-text');
            const paginationInfo = document.querySelector('.pagination-info');
            const tocButton = document.getElementById('toc-button');
            const tocModal = document.getElementById('toc-modal');
            const tocClose = document.getElementById('toc-close');
            const tocItems = document.querySelectorAll('.toc-item');
            const currentPageNum = document.getElementById('current-page-num');
            const totalPages = document.getElementById('total-pages');
            
            // Переменные
            const bookId = <?php echo $bookId; ?>;
            const userId = <?php echo $_SESSION['user_id']; ?>;
            let currentPageIndex = <?php echo $currentPage; ?>;
            let totalPagesCount = <?php echo $totalPages; ?>;
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
            
            // Если включен двухстраничный режим, активируем его
            if (isTwoPageMode) {
                toggleTwoPageMode();
            }
            
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
                if (pageNum < 1 || pageNum > totalPagesCount) return;
                
                window.location.href = `reader_fix.php?id=${bookId}&page=${pageNum}`;
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
                        page: currentPageIndex,
                        scroll_position: window.scrollY,
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
                const percentage = Math.round((currentPageIndex / totalPagesCount) * 100);
                progressFill.style.width = `${percentage}%`;
                progressText.textContent = `${percentage}%`;
                currentPageNum.textContent = currentPageIndex;
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
                if (currentPageIndex > 1) {
                    goToPage(currentPageIndex - 1);
                }
            });
            
            nextPageBtn.addEventListener('click', function() {
                if (currentPageIndex < totalPagesCount) {
                    goToPage(currentPageIndex + 1);
                }
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
                    const page = parseInt(this.getAttribute('data-page'));
                    goToPage(page);
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