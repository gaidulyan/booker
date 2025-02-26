<?php
session_start();
require_once 'includes/functions.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// Получаем список всех книг
$books = getAllBooks();

// Если запрошен ремонт книги
if (isset($_GET['repair']) && is_numeric($_GET['repair'])) {
    $bookId = (int)$_GET['repair'];
    
    if (repairBook($bookId)) {
        $message = "Книга успешно восстановлена!";
    } else {
        $error = "Не удалось восстановить книгу.";
    }
}

// Если запрошен ремонт всех книг
if (isset($_GET['repair_all'])) {
    $repaired = 0;
    $failed = 0;
    
    foreach ($books as $book) {
        if (repairBook($book['id'])) {
            $repaired++;
        } else {
            $failed++;
        }
    }
    
    $message = "Восстановлено книг: $repaired. Не удалось восстановить: $failed.";
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Проверка и восстановление книг - Читалка FB2</title>
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
        .repair-all-btn {
            display: inline-block;
            padding: 10px 15px;
            background-color: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            margin-top: 20px;
        }
        .repair-all-btn:hover {
            background-color: #0b7dda;
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
                    <li><a href="logout.php">Выход (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <section>
                <h2>Проверка и восстановление книг</h2>
                
                <?php if (!empty($message)): ?>
                    <div class="message success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <p>На этой странице вы можете проверить и восстановить книги, которые не отображаются корректно.</p>
                
                <a href="?repair_all=1" class="repair-all-btn">Восстановить все книги</a>
                
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
                                    <a href="reader.php?id=<?php echo $book['id']; ?>" class="btn">Читать</a>
                                    <a href="?repair=<?php echo $book['id']; ?>" class="repair-btn">Восстановить</a>
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