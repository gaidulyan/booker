<?php
require_once 'db.php';

function parseBookFB2($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("Файл не найден: $filePath");
    }
    
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new Exception("Не удалось прочитать содержимое файла: $filePath");
    }
    
    // Определение кодировки файла и преобразование в UTF-8 если нужно
    $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'KOI8-R'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    
    // Проверка на валидность XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    
    if (!$xml) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        $errorMsg = "Ошибка парсинга FB2 файла: ";
        if (!empty($errors)) {
            $errorMsg .= $errors[0]->message . " в строке " . $errors[0]->line;
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
    $title = '';
    $author = '';
    $coverImage = null;
    
    if (isset($xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'book-title'})) {
        $title = (string)$xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'book-title'};
    }
    
    if (isset($xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'author'})) {
        $firstName = (string)$xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'author'}->{$ns.'first-name'};
        $middleName = (string)$xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'author'}->{$ns.'middle-name'};
        $lastName = (string)$xml->{$ns.'description'}->{$ns.'title-info'}->{$ns.'author'}->{$ns.'last-name'};
        
        $author = trim("$firstName $middleName $lastName");
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
    
    return [
        'title' => $title ?: 'Без названия',
        'author' => $author ?: 'Неизвестный автор',
        'content' => $bookContent,
        'cover_image' => $coverImage
    ];
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

function saveUserProgress($userId, $bookId, $position) {
    global $db;
    
    $sql = "INSERT INTO user_progress (user_id, book_id, position) 
            VALUES (:user_id, :book_id, :position)
            ON DUPLICATE KEY UPDATE position = :position";
    
    $db->query($sql, [
        ':user_id' => $userId,
        ':book_id' => $bookId,
        ':position' => $position
    ]);
    
    return true;
}

function getAllBooks() {
    global $db;
    
    $sql = "SELECT id, title, author, upload_date, cover_image FROM books ORDER BY upload_date DESC";
    $result = $db->query($sql);
    
    return $result->fetchAll(PDO::FETCH_ASSOC);
}
?> 