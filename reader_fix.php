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
        (function() {
            const ReaderApp = {
                state: {
                    virtualPages: [],
                    currentPageIndex: <?php echo $currentPage; ?>,
                    totalPagesCount: <?php echo $totalChapters; ?>,
                    userId: <?php echo $_SESSION['user_id']; ?>,
                    bookId: <?php echo $bookId; ?>,
                    progress: <?php echo json_encode($progress); ?>,
                    isFullscreen: false,
                    isTwoPageMode: localStorage.getItem('reader_two_page_mode') === '1',
                    fontSize: parseInt(localStorage.getItem('reader_font_size')) || 18,
                    savedTheme: localStorage.getItem('reader_theme') || 'light'
                },
                elements: {
                    backButton: document.getElementById('back-button'),
                    fontButton: document.getElementById('font-button'),
                    layoutToggle: document.getElementById('layout-toggle'),
                    fullscreenButton: document.getElementById('fullscreen-button'),
                    tocButton: document.getElementById('toc-button'),
                    tocClose: document.getElementById('toc-close'),
                    tocItems: document.querySelectorAll('.toc-item'),
                    currentPageElement: document.getElementById('current-page'),
                    nextPageElement: document.getElementById('next-page'),
                    prevPageBtn: document.getElementById('prev-page-btn'),
                    nextPageBtn: document.getElementById('next-page-btn'),
                    prevPageArea: document.getElementById('prev-page-area'),
                    nextPageArea: document.getElementById('next-page-area'),
                    currentPageNum: document.getElementById('current-page-num'),
                    totalPagesElement: document.getElementById('total-pages'),
                    progressFill: document.getElementById('progress-fill'),
                    progressText: document.getElementById('progress-text'),
                    saveBtn: document.getElementById('save-btn'),
                    settingsModal: document.getElementById('settings-modal'),
                    decreaseFontBtn: document.getElementById('decrease-font-btn'),
                    increaseFontBtn: document.getElementById('increase-font-btn'),
                    fontSizeValue: document.getElementById('font-size-value'),
                    themeOptions: document.querySelectorAll('.theme-option'),
                    tocModal: document.getElementById('toc-modal')
                },
                setupEventListeners: function() {
                    // Восстанавливаем тему
                    this.state.savedTheme = this.state.savedTheme || 'light';
                    this.elements.themeOptions.forEach(option => {
                        option.classList.remove('active');
                        if (option.getAttribute('data-theme') === this.state.savedTheme) {
                            option.classList.add('active');
                        }
                    });
                    document.body.classList.add('theme-' + this.state.savedTheme);
                    
                    // Автоматическое сохранение прогресса при прокрутке
                    let scrollTimeout;
                    window.addEventListener('scroll', function() {
                        clearTimeout(scrollTimeout);
                        scrollTimeout = setTimeout(function() {
                            this.saveProgress(false);
                            this.updateProgressIndicator();
                        }.bind(this), 1000);
                    });
                    
                    // Автоматическое сохранение прогресса перед закрытием страницы
                    window.addEventListener('beforeunload', function() {
                        this.saveProgress(false);
                    }.bind(this));
                    
                    // Обработка изменения размера окна
                    let resizeTimeout;
                    window.addEventListener('resize', function() {
                        clearTimeout(resizeTimeout);
                        resizeTimeout = setTimeout(function() {
                            // Пересчитываем виртуальные страницы
                            this.initializeVirtualPages();
                            
                            // Обновляем индикатор прогресса
                            this.updateProgressIndicator();
                        }.bind(this), 300);
                    });
                    
                    // Функции
                    this.updateFontSize = function() {
                        this.elements.currentPageElement.style.fontSize = `${this.state.fontSize}px`;
                        this.elements.nextPageElement.style.fontSize = `${this.state.fontSize}px`;
                        this.elements.fontSizeValue.textContent = this.state.fontSize;
                        localStorage.setItem('reader_font_size', this.state.fontSize);
                    };
                    
                    this.toggleTwoPageMode = function() {
                        this.state.isTwoPageMode = !this.state.isTwoPageMode;
                        
                        if (this.state.isTwoPageMode) {
                            document.body.classList.add('two-page-mode');
                            this.elements.nextPageElement.style.display = 'block';
                            this.elements.layoutToggle.innerHTML = '<i class="fas fa-book-open"></i>';
                        } else {
                            document.body.classList.remove('two-page-mode');
                            this.elements.nextPageElement.style.display = 'none';
                            this.elements.layoutToggle.innerHTML = '<i class="fas fa-columns"></i>';
                        }
                        
                        localStorage.setItem('reader_two_page_mode', this.state.isTwoPageMode ? '1' : '0');
                    };
                    
                    this.toggleTocModal = function() {
                        this.elements.tocModal.classList.toggle('open');
                    };
                    
                    this.goToPage = function(pageNum) {
                        window.location.href = `reader_fix.php?id=${this.state.bookId}&page=${pageNum}`;
                    };
                    
                    this.goToChapter = function(chapterId) {
                        // Находим элемент главы
                        const chapterElement = document.getElementById(chapterId);
                        if (chapterElement) {
                            // Прокручиваем к элементу
                            chapterElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            
                            // Находим номер главы
                            const chapterNum = parseInt(chapterId.replace('chapter_', ''));
                            
                            // Обновляем активную главу в оглавлении
                            this.elements.tocItems.forEach(item => {
                                item.classList.remove('active');
                                if (parseInt(item.getAttribute('data-page')) === chapterNum) {
                                    item.classList.add('active');
                                }
                            });
                            
                            // Обновляем индикатор прогресса
                            this.updateProgressIndicator();
                        }
                    };
                    
                    this.saveProgress = function(showMessage = true) {
                        // Получаем текущую позицию прокрутки
                        const scrollPosition = window.scrollY || document.documentElement.scrollTop;
                        
                        // Получаем текущий процент прокрутки
                        const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
                        const scrollPercentage = scrollHeight > 0 ? (scrollPosition / scrollHeight) * 100 : 0;
                        
                        // Сохраняем прогресс на сервере
                        fetch('save_progress.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                user_id: this.state.userId,
                                book_id: this.state.bookId,
                                page: this.state.currentPageIndex,
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
                    };
                    
                    this.updateProgressIndicator = function() {
                        // Получаем текущую позицию прокрутки
                        const scrollPosition = window.scrollY || document.documentElement.scrollTop;
                        
                        // Получаем общую высоту прокрутки
                        const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
                        
                        // Вычисляем процент прокрутки
                        const scrollPercentage = scrollHeight > 0 ? (scrollPosition / scrollHeight) * 100 : 0;
                        
                        // Обновляем индикатор прогресса
                        this.elements.progressFill.style.width = `${scrollPercentage}%`;
                        this.elements.progressText.textContent = `${Math.round(scrollPercentage)}%`;
                    };
                    
                    this.updatePageInfo = function() {
                        this.elements.currentPageNum.textContent = this.state.currentPageIndex;
                        this.elements.totalPagesElement.textContent = this.state.totalPagesCount;
                    };
                    
                    this.handleSwipe = function() {
                        const swipeThreshold = 100; // Минимальное расстояние для свайпа
                        
                        if (this.state.touchEndX < this.state.touchStartX - swipeThreshold) {
                            // Свайп влево - следующая страница
                            if (this.state.currentPageIndex < this.state.totalPagesCount) {
                                this.goToPage(this.state.currentPageIndex + 1);
                            }
                        }
                        
                        if (this.state.touchEndX > this.state.touchStartX + swipeThreshold) {
                            // Свайп вправо - предыдущая страница
                            if (this.state.currentPageIndex > 1) {
                                this.goToPage(this.state.currentPageIndex - 1);
                            }
                        }
                    };
                    
                    // Обработчики событий
                    this.elements.backButton.addEventListener('click', function() {
                        this.saveProgress(false);
                        window.location.href = 'index.php';
                    }.bind(this));
                    
                    this.elements.fontButton.addEventListener('click', function() {
                        this.elements.settingsModal.style.display = this.elements.settingsModal.style.display === 'block' ? 'none' : 'block';
                    }.bind(this));
                    
                    this.elements.decreaseFontBtn.addEventListener('click', function() {
                        if (this.state.fontSize > 12) {
                            this.state.fontSize -= 2;
                            this.updateFontSize();
                        }
                    }.bind(this));
                    
                    this.elements.increaseFontBtn.addEventListener('click', function() {
                        if (this.state.fontSize < 32) {
                            this.state.fontSize += 2;
                            this.updateFontSize();
                        }
                    }.bind(this));
                    
                    this.elements.themeOptions.forEach(option => {
                        option.addEventListener('click', function() {
                            const theme = this.getAttribute('data-theme');
                            
                            // Удаляем все классы тем
                            document.body.classList.remove('theme-light', 'theme-sepia', 'theme-dark');
                            
                            // Добавляем класс выбранной темы
                            document.body.classList.add('theme-' + theme);
                            
                            // Сохраняем режим отображения, если он активен
                            if (this.state.isTwoPageMode) {
                                document.body.classList.add('two-page-mode');
                            }
                            
                            // Обновляем активную тему
                            this.classList.add('active');
                            
                            localStorage.setItem('reader_theme', theme);
                        });
                    });
                    
                    this.elements.fullscreenButton.addEventListener('click', function() {
                        if (!document.fullscreenElement) {
                            document.documentElement.requestFullscreen().then(() => {
                                this.state.isFullscreen = true;
                                document.body.classList.add('fullscreen-mode');
                                this.elements.fullscreenButton.innerHTML = '<i class="fas fa-compress"></i>';
                            }).catch(err => {
                                console.error(`Ошибка: ${err.message}`);
                            });
                        } else {
                            if (document.exitFullscreen) {
                                document.exitFullscreen().then(() => {
                                    this.state.isFullscreen = false;
                                    document.body.classList.remove('fullscreen-mode');
                                    this.elements.fullscreenButton.innerHTML = '<i class="fas fa-expand"></i>';
                                });
                            }
                        }
                    }.bind(this));
                    
                    this.elements.layoutToggle.addEventListener('click', function() {
                        this.toggleTwoPageMode();
                    }.bind(this));
                    
                    this.elements.prevPageBtn.addEventListener('click', function() {
                        if (this.state.currentPageIndex > 1) {
                            this.goToPage(this.state.currentPageIndex - 1);
                        }
                    }.bind(this));
                    
                    this.elements.nextPageBtn.addEventListener('click', function() {
                        if (this.state.currentPageIndex < this.state.totalPagesCount) {
                            this.goToPage(this.state.currentPageIndex + 1);
                        }
                    }.bind(this));
                    
                    // Обработчики для областей перемотки страниц
                    this.elements.prevPageArea.addEventListener('click', function() {
                        if (this.state.currentPageIndex > 1) {
                            this.goToPage(this.state.currentPageIndex - 1);
                        }
                    });
                    
                    this.elements.nextPageArea.addEventListener('click', function() {
                        if (this.state.currentPageIndex < this.state.totalPagesCount) {
                            this.goToPage(this.state.currentPageIndex + 1);
                        }
                    });
                    
                    // Показываем индикаторы перемотки при наведении
                    this.elements.prevPageArea.addEventListener('mouseenter', function() {
                        this.querySelector('.page-turn-indicator').style.opacity = '1';
                    });
                    
                    this.elements.prevPageArea.addEventListener('mouseleave', function() {
                        this.querySelector('.page-turn-indicator').style.opacity = '0';
                    });
                    
                    this.elements.nextPageArea.addEventListener('mouseenter', function() {
                        this.querySelector('.page-turn-indicator').style.opacity = '1';
                    });
                    
                    this.elements.nextPageArea.addEventListener('mouseleave', function() {
                        this.querySelector('.page-turn-indicator').style.opacity = '0';
                    });
                    
                    this.elements.saveBtn.addEventListener('click', function() {
                        this.saveProgress(true);
                    }.bind(this));
                    
                    this.elements.tocButton.addEventListener('click', function() {
                        this.toggleTocModal();
                    }.bind(this));
                    
                    this.elements.tocClose.addEventListener('click', function() {
                        this.toggleTocModal();
                    }.bind(this));
                    
                    this.elements.tocItems.forEach(item => {
                        item.addEventListener('click', function() {
                            const elementId = this.getAttribute('data-element-id');
                            if (elementId) {
                                this.goToChapter(elementId);
                            }
                            this.elements.tocModal.classList.remove('open');
                        }); 
                    });
                    
                    // Закрытие модального окна при клике вне его
                    document.addEventListener('click', function(event) {
                        if (this.elements.settingsModal.style.display === 'block' && 
                            !this.elements.settingsModal.contains(event.target) && 
                            event.target !== this.elements.fontButton) {
                            this.elements.settingsModal.style.display = 'none';
                        }
                        
                        if (this.elements.tocModal.classList.contains('open') && 
                            !this.elements.tocModal.contains(event.target) && 
                            event.target !== this.elements.tocButton) {
                            this.elements.tocModal.classList.remove('open');
                        }
                    }.bind(this));
                    
                    // Обработка клавиш
                    document.addEventListener('keydown', function(event) {
                        if (event.key === 'Escape') {
                            if (this.state.isFullscreen) {
                                if (document.exitFullscreen) {
                                    document.exitFullscreen().then(() => {
                                        this.state.isFullscreen = false;
                                        document.body.classList.remove('fullscreen-mode');
                                        this.elements.fullscreenButton.innerHTML = '<i class="fas fa-expand"></i>';
                                    });
                                }
                            }
                            
                            if (this.elements.tocModal.classList.contains('open')) {
                                this.elements.tocModal.classList.remove('open');
                            }
                            
                            if (this.elements.settingsModal.style.display === 'block') {
                                this.elements.settingsModal.style.display = 'none';
                            }
                        } else if (event.key === 'ArrowLeft') {
                            if (this.state.currentPageIndex > 1) {
                                this.goToPage(this.state.currentPageIndex - 1);
                            }
                        } else if (event.key === 'ArrowRight') {
                            if (this.state.currentPageIndex < this.state.totalPagesCount) {
                                this.goToPage(this.state.currentPageIndex + 1);
                            }
                        } else if (event.key === 'f' && event.ctrlKey) {
                            // Ctrl+F для полноэкранного режима
                            event.preventDefault();
                            this.elements.fullscreenButton.click();
                        } else if (event.key === 'd' && event.ctrlKey) {
                            // Ctrl+D для двухстраничного режима
                            event.preventDefault();
                            this.elements.layoutToggle.click();
                        } else if (event.key === 'o' && event.ctrlKey) {
                            // Ctrl+O для оглавления
                            event.preventDefault();
                            this.elements.tocButton.click();
                        }
                    }.bind(this));
                    
                    // Обработка изменения истории браузера
                    window.addEventListener('popstate', function(event) {
                        if (event.state && event.state.page) {
                            this.state.currentPageIndex = event.state.page;
                            
                            if (event.state.chapter) {
                                this.goToChapter('chapter_' + event.state.chapter);
                            } else {
                                this.goToPage(this.state.currentPageIndex);
                            }
                        }
                    }.bind(this));
                },
                
                initializeVirtualPages: function() {
                    // Получаем все элементы содержимого книги
                    const bookContentElement = this.elements.currentPageElement;
                    const contentElements = Array.from(bookContentElement.querySelectorAll('p, h1, h2, h3, h4, h5, h6, div, img'));
                    
                    // Определяем высоту видимой области
                    const viewportHeight = window.innerHeight - 120; // Вычитаем высоту панелей
                    
                    // Создаем виртуальные страницы
                    let currentPage = [];
                    let currentHeight = 0;
                    this.state.virtualPages = [];
                    
                    contentElements.forEach(element => {
                        const elementHeight = element.offsetHeight;
                        
                        // Если элемент не помещается на текущую страницу, создаем новую
                        if (currentHeight + elementHeight > viewportHeight && currentPage.length > 0) {
                            this.state.virtualPages.push(currentPage);
                            currentPage = [element];
                            currentHeight = elementHeight;
                        } else {
                            currentPage.push(element);
                            currentHeight += elementHeight;
                        }
                    });
                    
                    // Добавляем последнюю страницу
                    if (currentPage.length > 0) {
                        this.state.virtualPages.push(currentPage);
                    }
                    
                    // Обновляем общее количество страниц
                    this.state.totalPagesCount = this.state.virtualPages.length;
                    
                    // Определяем текущую страницу на основе прокрутки
                    const scrollPosition = window.scrollY || document.documentElement.scrollTop;
                    this.state.currentPageIndex = 1;
                    
                    // Находим текущую виртуальную страницу на основе прокрутки
                    let accumulatedHeight = 0;
                    for (let i = 0; i < this.state.virtualPages.length; i++) {
                        const pageHeight = this.state.virtualPages[i].reduce((sum, element) => sum + element.offsetHeight, 0);
                        
                        if (accumulatedHeight + pageHeight > scrollPosition) {
                            this.state.currentPageIndex = i + 1;
                            break;
                        }
                        
                        accumulatedHeight += pageHeight;
                    }
                    
                    // Обновляем информацию о страницах
                    this.updatePageInfo();
                },
                
                init: function() {
                    // Получаем все элементы
                    this.getElements();
                    
                    // Устанавливаем обработчики событий
                    this.setupEventListeners();
                    
                    // Инициализация виртуальных страниц
                    this.initializeVirtualPages();
                    
                    // Восстанавливаем позицию прокрутки
                    window.scrollTo(0, this.state.progress.scroll_position);
                    
                    // Восстанавливаем режим отображения
                    if (localStorage.getItem('reader_two_page_mode') === '1') {
                        this.toggleTwoPageMode();
                    }
                    
                    // Обновляем информацию о страницах
                    this.updatePageInfo();
                    
                    // Обновляем индикатор прогресса
                    this.updateProgressIndicator();
                    
                    // Автоматически сохраняем прогресс
                    setTimeout(() => {
                        this.saveProgress(false);
                    }, 2000);
                    
                    // Если есть ID главы в URL, переходим к ней
                    const urlParams = new URLSearchParams(window.location.search);
                    const chapterId = urlParams.get('chapter');
                    if (chapterId) {
                        this.goToChapter('chapter_' + chapterId);
                    }
                }
            };
            
            // Инициализация приложения
            document.addEventListener('DOMContentLoaded', function() {
                ReaderApp.init();
            });
        })();
    </script>
</body>
</html> 