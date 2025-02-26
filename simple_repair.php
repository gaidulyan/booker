<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Получаем список всех книг
try {
    $sql = "SELECT id, title, author, upload_date FROM books ORDER BY upload_date DESC";
    $result = $db->query($sql);
    $books = $result->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Ошибка при получении списка книг: " . $e->getMessage();
    $books = [];
}

// Если запрошен ремонт книги
if (isset($_GET['repair']) && is_numeric($_GET['repair'])) {
    $bookId = (int)$_GET['repair'];
    
    try {
        // Получаем информацию о книге
        $sql = "SELECT * FROM books WHERE id = :id";
        $result = $db->query($sql, [':id' => $bookId]);
        $book = $result->fetch(PDO::FETCH_ASSOC);
        
        if ($book) {
            // Простое обновление книги (пустышка)
            $sql = "UPDATE books SET 
                    title = :title, 
                    author = :author 
                    WHERE id = :id";
            
            $db->query($sql, [
                ':title' => $book['title'],
                ':author' => $book['author'],
                ':id' => $bookId
            ]);
            
            $message = "Книга обновлена!";
        } else {
            $error = "Книга не найдена.";
        }
    } catch (Exception $e) {
        $error = "Ошибка при обновлении книги: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление книгами - Читалка FB2</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .books-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .books-table th, .books-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .books-table th {
            background-color: #f2f2f2;
        }
        .books-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .repair-btn {
            display: inline-block;
            padding: 5px 10px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 3px;
        }
        .repair-btn:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Читалка FB2</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Главная</a></li>
                    <li><a href="upload.php">Загрузить книгу</a></li>
                    <li><a href="logout.php">Выход</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <section>
                <h2>Управление книгами</h2>
                
                <?php if (!empty($message)): ?>
                    <div class="message success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <p>На этой странице вы можете управлять вашими книгами.</p>
                
                <table class="books-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Название</th>
                            <th>Автор</th>
                            <th>Дата загрузки</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($books as $book): ?>
                            <tr>
                                <td><?php echo $book['id']; ?></td>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($book['upload_date'])); ?></td>
                                <td>
                                    <a href="reader_fix.php?id=<?php echo $book['id']; ?>" class="btn">Читать</a>
                                    <a href="?repair=<?php echo $book['id']; ?>" class="repair-btn">Обновить</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Читалка FB2</p>
        </footer>
    </div>
</body>
</html> 