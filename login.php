<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/google_config.php';
require_once 'vendor/autoload.php';

// Если пользователь уже авторизован, перенаправляем на главную
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Создаем клиент Google
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

// Создаем URL для авторизации
$authUrl = $client->createAuthUrl();

$error = '';
if (isset($_GET['error'])) {
    $error = 'Ошибка авторизации. Пожалуйста, попробуйте снова.';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Читалка FB2</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .google-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #4285F4;
            color: white;
            text-align: center;
            border-radius: 4px;
            text-decoration: none;
            margin-top: 20px;
        }
        .google-btn:hover {
            background-color: #357ae8;
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
                    <li><a href="login.php">Вход</a></li>
                </ul>
            </nav>
        </header>
        
        <main>
            <section>
                <h2>Вход в систему</h2>
                
                <?php if (!empty($error)): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="login-container">
                    <p>Для использования читалки FB2 необходимо войти в систему.</p>
                    <a href="<?php echo $authUrl; ?>" class="google-btn">Войти через Google</a>
                </div>
            </section>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Читалка FB2</p>
        </footer>
    </div>
</body>
</html> 