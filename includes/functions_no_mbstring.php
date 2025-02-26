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
    
    // Пропускаем определение кодировки, так как нет mbstring
    
    // Удаление BOM, если есть
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    
    // Проверка на валидность XML
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

// Остальные функции без изменений 