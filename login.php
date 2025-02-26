<?php
session_start();
require_once 'includes/db.php';
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

// Обработка стандартного входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Пожалуйста, введите email и пароль';
    } else {
        // Ищем пользователя по email
        $sql = "SELECT * FROM users WHERE email = :email";
        $result = $db->query($sql, [':email' => $email]);
        $user = $result->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Устанавливаем сессию
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            
            // Обновляем время последнего входа
            $sql = "UPDATE users SET last_login = NOW() WHERE id = :id";
            $db->query($sql, [':id' => $user['id']]);
            
            // Перенаправляем на главную или на страницу, с которой пришли
            $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            
            header("Location: $redirect");
            exit;
        } else {
            $error = 'Неверный email или пароль';
        }
    }
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
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn-submit {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-submit:hover {
            background-color: #45a049;
        }
        .or-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        .or-divider:before, .or-divider:after {
            content: "";
            position: absolute;
            top: 50%;
            width: 45%;
            height: 1px;
            background-color: #ddd;
        }
        .or-divider:before {
            left: 0;
        }
        .or-divider:after {
            right: 0;
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
                    <li><a href="register.php">Регистрация</a></li>
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
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Пароль:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn-submit">Войти</button>
                        </div>
                    </form>
                    
                    <div class="or-divider">или</div>
                    
                    <a href="<?php echo $authUrl; ?>" class="google-btn">Войти через Google</a>
                    
                    <p style="text-align: center; margin-top: 20px;">
                        Нет аккаунта? <a href="register.php">Зарегистрироваться</a>
                    </p>
                </div>
            </section>
        </main>
        
        <footer>
            <p>&copy; <?php echo date('Y'); ?> Читалка FB2</p>
        </footer>
    </div>
</body>
</html> 