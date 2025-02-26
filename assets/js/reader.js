document.addEventListener('DOMContentLoaded', function() {
    const readerContent = document.getElementById('reader-content');
    const progressFill = document.getElementById('progress-fill');
    const currentPageEl = document.getElementById('current-page');
    const totalPagesEl = document.getElementById('total-pages');
    const prevPageBtn = document.getElementById('prev-page');
    const nextPageBtn = document.getElementById('next-page');
    const fontSizeDecreaseBtn = document.getElementById('font-size-decrease');
    const fontSizeIncreaseBtn = document.getElementById('font-size-increase');
    const themeToggleBtn = document.getElementById('theme-toggle');
    
    const bookId = readerContent.dataset.bookId;
    const userId = readerContent.dataset.userId;
    
    let currentPage = 0;
    let totalPages = 0;
    let pageHeight = 0;
    let fontSize = parseInt(localStorage.getItem('reader-font-size')) || 18;
    let isDarkTheme = localStorage.getItem('reader-theme') === 'dark';
    
    // Применяем сохраненные настройки
    applyFontSize();
    applyTheme();
    
    // Разбиваем содержимое на страницы
    function paginateContent() {
        pageHeight = readerContent.clientHeight;
        totalPages = Math.ceil(readerContent.scrollHeight / pageHeight);
        totalPagesEl.textContent = totalPages;
        
        // Восстанавливаем сохраненную позицию
        if (savedPosition > 0) {
            currentPage = Math.floor(savedPosition / pageHeight);
            goToPage(currentPage);
        }
    }
    
    // Переход на указанную страницу
    function goToPage(pageNum) {
        if (pageNum < 0) pageNum = 0;
        if (pageNum >= totalPages) pageNum = totalPages - 1;
        
        currentPage = pageNum;
        const scrollPosition = pageNum * pageHeight;
        
        readerContent.scrollTop = scrollPosition;
        currentPageEl.textContent = currentPage + 1;
        
        // Обновляем прогресс-бар
        const progress = ((currentPage + 1) / totalPages) * 100;
        progressFill.style.width = `${progress}%`;
        
        // Сохраняем позицию
        savePosition(scrollPosition);
    }
    
    // Сохранение позиции чтения
    function savePosition(position) {
        fetch('save_progress.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `user_id=${userId}&book_id=${bookId}&position=${position}`
        });
    }
    
    // Изменение размера шрифта
    function applyFontSize() {
        readerContent.style.fontSize = `${fontSize}px`;
        localStorage.setItem('reader-font-size', fontSize);
        
        // Перерасчет страниц после изменения размера шрифта
        setTimeout(paginateContent, 100);
    }
    
    // Переключение темы
    function applyTheme() {
        if (isDarkTheme) {
            document.body.classList.add('dark-theme');
        } else {
            document.body.classList.remove('dark-theme');
        }
        localStorage.setItem('reader-theme', isDarkTheme ? 'dark' : 'light');
    }
    
    // Обработчики событий
    prevPageBtn.addEventListener('click', function() {
        goToPage(currentPage - 1);
    });
    
    nextPageBtn.addEventListener('click', function() {
        goToPage(currentPage + 1);
    });
    
    fontSizeDecreaseBtn.addEventListener('click', function() {
        if (fontSize > 12) {
            fontSize -= 2;
            applyFontSize();
        }
    });
    
    fontSizeIncreaseBtn.addEventListener('click', function() {
        if (fontSize < 32) {
            fontSize += 2;
            applyFontSize();
        }
    });
    
    themeToggleBtn.addEventListener('click', function() {
        isDarkTheme = !isDarkTheme;
        applyTheme();
    });
    
    // Навигация с помощью клавиатуры
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') {
            goToPage(currentPage - 1);
        } else if (e.key === 'ArrowRight') {
            goToPage(currentPage + 1);
        }
    });
    
    // Обработка свайпов на мобильных устройствах
    let touchStartX = 0;
    let touchEndX = 0;
    
    readerContent.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
    });
    
    readerContent.addEventListener('touchend', function(e) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
    });
    
    function handleSwipe() {
        const swipeThreshold = 50;
        
        if (touchEndX < touchStartX - swipeThreshold) {
            // Свайп влево - следующая страница
            goToPage(currentPage + 1);
        }
        
        if (touchEndX > touchStartX + swipeThreshold) {
            // Свайп вправо - предыдущая страница
            goToPage(currentPage - 1);
        }
    }
    
    // Инициализация
    window.addEventListener('resize', paginateContent);
    paginateContent();
}); 