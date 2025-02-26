<?php
require_once 'db.php';

function parseBookFB2($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("Файл не найден: $filePath");
    }
    
    // Читаем файл с обработкой ошибок
    $content = @file_get_contents($filePath);
    if ($content === false) {
        throw new Exception("Не удалось прочитать содержимое файла: $filePath");
    }
    
    // Проверка на пустой файл
    if (empty($content)) {
        throw new Exception("Файл пуст: $filePath");
    }
    
    // Определение и исправление кодировки
    $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'KOI8-R', 'CP1251'], true);
    if (!$encoding) {
        $encoding = 'Windows-1251'; // Предполагаем, что это Windows-1251, если не удалось определить
    }
    
    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    
    // Удаление BOM, если есть
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    
    // Проверка на валидность XML с более подробной обработкой ошибок
    libxml_use_internal_errors(true);
    
    // Попытка исправить некоторые распространенные проблемы в XML
    $content = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;)/', '&amp;', $content);
    
    // Создаем временный файл для обработки XML
    $tempFile = tempnam(sys_get_temp_dir(), 'fb2_');
    file_put_contents($tempFile, $content);
    
    try {
        $xml = simplexml_load_file($tempFile);
        
        if (!$xml) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $errorMsg = "Ошибка парсинга FB2 файла: ";
            
            if (!empty($errors)) {
                $errorMsg .= $errors[0]->message . " в строке " . $errors[0]->line;
                
                // Попытка показать проблемный фрагмент
                $lines = explode("\n", $content);
                if (isset($lines[$errors[0]->line - 1])) {
                    $errorMsg .= "\nПроблемная строка: " . htmlspecialchars($lines[$errors[0]->line - 1]);
                }
            } else {
                $errorMsg .= "неизвестная ошибка XML";
            }
            
            throw new Exception($errorMsg);
        }
        
        // Регистрация пространства имен FB2
        $namespaces = $xml->getNamespaces(true);
        $ns = '';
        
        if (isset($namespaces[''])) {
            $ns = '';
        } else if (isset($namespaces['fb'])) {
            $ns = 'fb:';
        }
        
        // Получение метаданных книги
        $title = 'Без названия';
        $author = 'Неизвестный автор';
        $coverImage = null;
        
        if (isset($xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'book-title'})) {
            $title = (string)$xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'book-title'};
        }
        
        if (isset($xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'author'})) {
            $firstName = (string)$xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'author'}->{$ns.'first-name'};
            $middleName = (string)$xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'author'}->{$ns.'middle-name'};
            $lastName = (string)$xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'author'}->{$ns.'last-name'};
            
            $author = trim("$firstName $middleName $lastName");
            if (empty($author)) {
                $author = 'Неизвестный автор';
            }
        }
        
        // Извлечение обложки, если есть
        if (isset($xml->{$ns.'binary'})) {
            foreach ($xml->{$ns.'binary'} as $binary) {
                $contentType = (string)$binary['content-type'];
                if (strpos($contentType, 'image/') === 0) {
                    $coverImage = 'data:' . $contentType . ';base64,' . (string)$binary;
                    break;
                }
            }
        }
        
        // Преобразование содержимого книги в HTML
        $bookContent = '';
        if (isset($xml->{$ns.'body'})) {
            $bookContent = convertFB2ToHTML($xml->{$ns.'body'}, $ns);
        }
        
        // Если контент пустой, создаем простой текст
        if (empty($bookContent)) {
            $bookContent = '<div class="section"><p>Содержимое книги не удалось извлечь или оно отсутствует.</p></div>';
        }
        
        // Удаляем временный файл
        @unlink($tempFile);
        
        return [
            'title' => $title,
            'author' => $author,
            'content' => $bookContent,
            'cover_image' => $coverImage
        ];
    } catch (Exception $e) {
        // Удаляем временный файл в случае ошибки
        @unlink($tempFile);
        throw $e;
    }
}

function convertFB2ToHTML($body, $ns) {
    $html = '';
    
    foreach ($body->children() as $child) {
        $nodeName = str_replace($ns, '', $child->getName());
        
        switch ($nodeName) {
            case 'section':
                $html .= '<div class="section">';
                $html .= convertFB2ToHTML($child, $ns);
                $html .= '</div>';
                break;
                
            case 'title':
                $html .= '<h2 class="chapter-title">';
                $html .= convertFB2ToHTML($child, $ns);
                $html .= '</h2>';
                break;
                
            case 'p':
                $html .= '<p>' . (string)$child . '</p>';
                break;
                
            case 'image':
                if (isset($child['href'])) {
                    $href = (string)$child['href'];
                    $href = str_replace('#', '', $href);
                    $html .= '<div class="image-container"><img src="data:image/jpeg;base64,' . $href . '" alt="Изображение" /></div>';
                }
                break;
                
            case 'subtitle':
                $html .= '<h3 class="subtitle">' . (string)$child . '</h3>';
                break;
                
            case 'empty-line':
                $html .= '<div class="empty-line"></div>';
                break;
                
            default:
                $html .= (string)$child;
        }
    }
    
    return $html;
}

function saveBook($bookData, $filePath) {
    global $db;
    
    // Проверка и очистка данных перед сохранением
    $title = mb_convert_encoding($bookData['title'], 'UTF-8', 'auto');
    $author = mb_convert_encoding($bookData['author'], 'UTF-8', 'auto');
    $content = mb_convert_encoding($bookData['content'], 'UTF-8', 'auto');
    
    // Логирование для отладки
    error_log("Сохранение книги: " . $title);
    error_log("Автор: " . $author);
    
    try {
        // Проверяем, не существует ли уже такая книга
        $checkSql = "SELECT id FROM books WHERE title = :title AND author = :author LIMIT 1";
        $checkResult = $db->query($checkSql, [
            ':title' => $title,
            ':author' => $author
        ]);
        
        $existingBook = $checkResult->fetch(PDO::FETCH_ASSOC);
        if ($existingBook) {
            return $existingBook['id']; // Возвращаем ID существующей книги
        }
        
        // Если книги нет, добавляем новую
        $sql = "INSERT INTO books (title, author, file_path, content, cover_image) 
                VALUES (:title, :author, :file_path, :content, :cover_image)";
        
        // Обрезаем слишком длинные значения
        if (strlen($title) > 250) {
            $title = mb_substr($title, 0, 250, 'UTF-8');
        }
        
        if (strlen($author) > 250) {
            $author = mb_substr($author, 0, 250, 'UTF-8');
        }
        
        $params = [
            ':title' => $title,
            ':author' => $author,
            ':file_path' => $filePath,
            ':content' => $content,
            ':cover_image' => $bookData['cover_image']
        ];
        
        $db->query($sql, $params);
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Ошибка SQL при сохранении книги: " . $e->getMessage());
        throw new Exception("Ошибка при сохранении книги в базу данных: " . $e->getMessage());
    }
}

function getBook($bookId) {
    global $db;
    
    $sql = "SELECT * FROM books WHERE id = :id";
    $result = $db->query($sql, [':id' => $bookId]);
    
    return $result->fetch(PDO::FETCH_ASSOC);
}

function getUserProgress($userId, $bookId) {
    global $db;
    
    $sql = "SELECT position FROM user_progress 
            WHERE user_id = :user_id AND book_id = :book_id";
    
    $result = $db->query($sql, [
        ':user_id' => $userId,
        ':book_id' => $bookId
    ]);
    
    $progress = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($progress) {
        return $progress['position'];
    }
    
    // Если записи нет, создаем новую
    $sql = "INSERT INTO user_progress (user_id, book_id, position) 
            VALUES (:user_id, :book_id, 0)";
    
    $db->query($sql, [
        ':user_id' => $userId,
        ':book_id' => $bookId
    ]);
    
    return 0;
}

function saveUserProgress($userId, $bookId, $page, $scrollPosition, $lastPageText = '') {
    global $db;
    
    // Проверяем, существует ли запись о прогрессе
    $sql = "SELECT id FROM user_progress 
            WHERE user_id = :user_id AND book_id = :book_id";
    
    $result = $db->query($sql, [
        ':user_id' => $userId,
        ':book_id' => $bookId
    ]);
    
    $progress = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($progress) {
        // Обновляем существующую запись
        $sql = "UPDATE user_progress 
                SET position = :position, page = :page, 
                    scroll_position = :scroll_position, 
                    last_page_text = :last_page_text,
                    last_read = NOW() 
                WHERE id = :id";
        
        $db->query($sql, [
            ':position' => $page * 1000 + $scrollPosition, // Для обратной совместимости
            ':page' => $page,
            ':scroll_position' => $scrollPosition,
            ':last_page_text' => $lastPageText,
            ':id' => $progress['id']
        ]);
    } else {
        // Создаем новую запись
        $sql = "INSERT INTO user_progress 
                (user_id, book_id, position, page, scroll_position, last_page_text) 
                VALUES (:user_id, :book_id, :position, :page, :scroll_position, :last_page_text)";
        
        $db->query($sql, [
            ':user_id' => $userId,
            ':book_id' => $bookId,
            ':position' => $page * 1000 + $scrollPosition, // Для обратной совместимости
            ':page' => $page,
            ':scroll_position' => $scrollPosition,
            ':last_page_text' => $lastPageText
        ]);
    }
    
    return true;
}

function getUserDetailedProgress($userId, $bookId) {
    global $db;
    
    $sql = "SELECT * FROM user_progress 
            WHERE user_id = :user_id AND book_id = :book_id";
    
    $result = $db->query($sql, [
        ':user_id' => $userId,
        ':book_id' => $bookId
    ]);
    
    $progress = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($progress) {
        return [
            'page' => $progress['page'],
            'scroll_position' => $progress['scroll_position'],
            'last_page_text' => $progress['last_page_text'],
            'last_read' => $progress['last_read']
        ];
    }
    
    // Если записи нет, создаем новую
    $sql = "INSERT INTO user_progress 
            (user_id, book_id, position, page, scroll_position) 
            VALUES (:user_id, :book_id, 0, 1, 0)";
    
    $db->query($sql, [
        ':user_id' => $userId,
        ':book_id' => $bookId
    ]);
    
    return [
        'page' => 1,
        'scroll_position' => 0,
        'last_page_text' => '',
        'last_read' => date('Y-m-d H:i:s')
    ];
}

function getAllBooks() {
    global $db;
    
    $sql = "SELECT id, title, author, upload_date, cover_image FROM books ORDER BY upload_date DESC";
    $result = $db->query($sql);
    
    return $result->fetchAll(PDO::FETCH_ASSOC);
}

function repairBook($bookId) {
    global $db;
    
    // Получаем информацию о книге
    $sql = "SELECT * FROM books WHERE id = :id";
    $result = $db->query($sql, [':id' => $bookId]);
    $book = $result->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        return false;
    }
    
    // Проверяем содержимое книги
    if (empty($book['content']) || $book['content'] == '<div class="section"><p>Содержимое книги не удалось извлечь или оно отсутствует.</p></div>') {
        // Пытаемся заново обработать файл
        $filePath = UPLOAD_DIR . $book['file_path'];
        
        if (file_exists($filePath)) {
            try {
                // Парсим FB2 файл заново
                $bookData = parseBookFB2($filePath);
                
                // Обновляем данные книги
                $sql = "UPDATE books SET 
                        title = :title, 
                        author = :author, 
                        content = :content, 
                        cover_image = :cover_image 
                        WHERE id = :id";
                
                $db->query($sql, [
                    ':title' => $bookData['title'],
                    ':author' => $bookData['author'],
                    ':content' => $bookData['content'],
                    ':cover_image' => $bookData['cover_image'],
                    ':id' => $bookId
                ]);
                
                return true;
            } catch (Exception $e) {
                // Если не удалось обработать, создаем простое содержимое
                $sql = "UPDATE books SET 
                        content = :content 
                        WHERE id = :id";
                
                $db->query($sql, [
                    ':content' => '<div class="section"><p>Не удалось обработать книгу. Ошибка: ' . $e->getMessage() . '</p></div>',
                    ':id' => $bookId
                ]);
                
                return false;
            }
        }
    }
    
    return true;
}
?> 