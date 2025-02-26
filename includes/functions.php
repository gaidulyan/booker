<?php
require_once 'db.php';

function parseBookFB2($filePath) {
    $content = file_get_contents($filePath);
    
    // Проверка на валидность XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    
    if (!$xml) {
        $errors = libxml_get_errors();
        libxml_clear_errors();
        throw new Exception("Ошибка парсинга FB2 файла: " . $errors[0]->message);
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
    
    $sql = "INSERT INTO books (title, author, file_path, content, cover_image) 
            VALUES (:title, :author, :file_path, :content, :cover_image)";
    
    $params = [
        ':title' => $bookData['title'],
        ':author' => $bookData['author'],
        ':file_path' => $filePath,
        ':content' => $bookData['content'],
        ':cover_image' => $bookData['cover_image']
    ];
    
    $db->query($sql, $params);
    return $db->lastInsertId();
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