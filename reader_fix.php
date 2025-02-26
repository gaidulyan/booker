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
            const prevPageBtn = document.getElementById('prev-page-btn');
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
            const currentPageNum = document.getElementById('current-page-num');
            const totalPagesElement = document.getElementById('total-pages');
            const tocButton = document.getElementById('toc-button');
            const tocModal = document.getElementById('toc-modal');
            const tocClose = document.getElementById('toc-close');
            const tocItems = document.querySelectorAll('.toc-item');
            const bookContent = document.getElementById('book-content');
            
            // Переменные
            const bookId = <?php echo $bookId; ?>;
            const userId = <?php echo $_SESSION['user_id']; ?>;
            let currentPageIndex = <?php echo $currentPage; ?>;
            let totalPagesCount = 0;
            let fontSize = parseInt(localStorage.getItem('reader_font_size')) || 18;
            let isFullscreen = false;
            let isTwoPageMode = localStorage.getItem('reader_two_page_mode') === '1';
            let bookPages = [];
            let currentPosition = 0;
            
            // Инициализация
            fontSizeValue.textContent = fontSize;
            updateFontSize();
            
            // Восстанавливаем тему
            const savedTheme = localStorage.getItem('reader_theme') || 'light';
            document.body.classList.add('theme-' + savedTheme);
            document.querySelector(`.theme-option[data-theme="${savedTheme}"]`).classList.add('active');
            
            // Инициализация страниц
            initializePages();
            
            // Функции
            function initializePages() {
                // Получаем все содержимое книги
                const bookContentHTML = currentPageElement.innerHTML;
                
                // Сохраняем оригинальное содержимое
                currentPageElement.setAttribute('data-original-content', bookContentHTML);
                
                // Разбиваем книгу на страницы в зависимости от размера экрана
                calculatePages();
                
                // Отображаем текущую страницу
                showPage(currentPageIndex);
                
                // Обновляем информацию о страницах
                updatePageInfo();
                
                // Если включен двухстраничный режим, применяем его
                if (isTwoPageMode) {
                    toggleTwoPageMode();
                }
            }
            
            function calculatePages() {
                // Очищаем массив страниц
                bookPages = [];
                
                // Получаем оригинальное содержимое
                const originalContent = currentPageElement.getAttribute('data-original-content');
                
                // Создаем временный элемент для расчета страниц
                const tempElement = document.createElement('div');
                tempElement.style.position = 'absolute';
                tempElement.style.visibility = 'hidden';
                tempElement.style.width = currentPageElement.clientWidth + 'px';
                tempElement.style.height = currentPageElement.clientHeight + 'px';
                tempElement.style.fontSize = fontSize + 'px';
                tempElement.style.overflow = 'hidden';
                document.body.appendChild(tempElement);
                
                // Создаем DOM из оригинального содержимого
                const parser = new DOMParser();
                const doc = parser.parseFromString(originalContent, 'text/html');
                const paragraphs = doc.querySelectorAll('p, h1, h2, h3, h4, h5, h6, div.title, div.subtitle');
                
                // Создаем первую страницу
                let currentPageContent = '';
                let currentPageHeight = 0;
                const maxPageHeight = currentPageElement.clientHeight - 80; // Оставляем отступ
                
                // Проходим по всем параграфам
                for (let i = 0; i < paragraphs.length; i++) {
                    const paragraph = paragraphs[i];
                    
                    // Добавляем параграф во временный элемент для измерения высоты
                    tempElement.innerHTML = paragraph.outerHTML;
                    const paragraphHeight = tempElement.scrollHeight;
                    
                    // Если параграф не помещается на текущую страницу, создаем новую
                    if (currentPageHeight > 0 && (currentPageHeight + paragraphHeight) > maxPageHeight) {
                        bookPages.push(currentPageContent);
                        currentPageContent = paragraph.outerHTML;
                        currentPageHeight = paragraphHeight;
                    } else {
                        currentPageContent += paragraph.outerHTML;
                        currentPageHeight += paragraphHeight;
                    }
                }
                
                // Добавляем последнюю страницу
                if (currentPageContent) {
                    bookPages.push(currentPageContent);
                }
                
                // Удаляем временный элемент
                document.body.removeChild(tempElement);
                
                // Обновляем общее количество страниц
                totalPagesCount = bookPages.length;
                totalPagesElement.textContent = totalPagesCount;
                
                // Если текущая страница больше общего количества, устанавливаем последнюю
                if (currentPageIndex > totalPagesCount) {
                    currentPageIndex = totalPagesCount;
                }
                
                // Обновляем индикатор прогресса
                updateProgressIndicator();
            }
            
            function showPage(pageIndex) {
                if (pageIndex < 1 || pageIndex > totalPagesCount) return;
                
                // Отображаем текущую страницу
                currentPageElement.innerHTML = bookPages[pageIndex - 1] || '';
                
                // Отображаем следующую страницу (для двухстраничного режима)
                if (pageIndex < totalPagesCount) {
                    nextPageElement.innerHTML = bookPages[pageIndex] || '';
                } else {
                    nextPageElement.innerHTML = '';
                }
                
                // Обновляем номер текущей страницы
                currentPageNum.textContent = pageIndex;
                currentPageIndex = pageIndex;
                
                // Обновляем индикатор прогресса
                updateProgressIndicator();
                
                // Обновляем активный элемент в оглавлении
                updateActiveTocItem();
                
                // Прокручиваем страницу в начало
                window.scrollTo(0, 0);
            }
            
            function goToPage(pageIndex) {
                if (pageIndex < 1 || pageIndex > totalPagesCount) return;
                
                // Сохраняем прогресс перед переходом
                saveProgress(false);
                
                // Отображаем новую страницу
                showPage(pageIndex);
            }
            
            function updateFontSize() {
                currentPageElement.style.fontSize = `${fontSize}px`;
                nextPageElement.style.fontSize = `${fontSize}px`;
                fontSizeValue.textContent = fontSize;
                localStorage.setItem('reader_font_size', fontSize);
                
                // Пересчитываем страницы при изменении размера шрифта
                calculatePages();
                showPage(currentPageIndex);
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
            
            function updateActiveTocItem() {
                tocItems.forEach(item => {
                    item.classList.remove('active');
                    
                    // Находим главу, соответствующую текущей странице
                    const itemPage = parseInt(item.getAttribute('data-page'));
                    if (itemPage === currentPageIndex) {
                        item.classList.add('active');
                    }
                });
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
                        scroll_position: window.pageYOffset || document.documentElement.scrollTop,
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
            }
            
            function updatePageInfo() {
                currentPageNum.textContent = currentPageIndex;
                totalPagesElement.textContent = totalPagesCount;
            }
            
            // Обработчики событий
            window.addEventListener('resize', function() {
                // Пересчитываем страницы при изменении размера окна
                calculatePages();
                showPage(currentPageIndex);
            });
            
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