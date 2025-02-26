<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'includes/google_config.php';
require_once 'vendor/autoload.php';

// Создаем клиент Google
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

try {
    // Обмен кода на токен доступа
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $client->setAccessToken($token);
    
    // Получаем информацию о пользователе
    $google_oauth = new Google_Service_Oauth2($client);
    $google_account_info = $google_oauth->userinfo->get();
    
    $email = $google_account_info->email;
    $name = $google_account_info->name;
    $google_id = $google_account_info->id;
    
    // Проверяем, существует ли пользователь в базе
    $sql = "SELECT * FROM users WHERE google_id = :google_id OR email = :email";
    $result = $db->query($sql, [
        ':google_id' => $google_id,
        ':email' => $email
    ]);
    
    $user = $result->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Обновляем данные пользователя
        $sql = "UPDATE users SET 
                google_id = :google_id, 
                username = :username, 
                email = :email, 
                last_login = NOW() 
                WHERE id = :id";
        
        $db->query($sql, [
            ':google_id' => $google_id,
            ':username' => $name,
            ':email' => $email,
            ':id' => $user['id']
        ]);
        
        $user_id = $user['id'];
    } else {
        // Создаем нового пользователя
        $sql = "INSERT INTO users (google_id, username, email, password, created_at) 
                VALUES (:google_id, :username, :email, :password, NOW())";
        
        $db->query($sql, [
            ':google_id' => $google_id,
            ':username' => $name,
            ':email' => $email,
            ':password' => password_hash(bin2hex(random_bytes(10)), PASSWORD_DEFAULT)
        ]);
        
        $user_id = $db->lastInsertId();
    }
    
    // Устанавливаем сессию
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $name;
    $_SESSION['email'] = $email;
    
    // Перенаправляем на главную страницу
    header('Location: index.php');
    exit;
    
} catch (Exception $e) {
    // В случае ошибки перенаправляем на страницу входа с сообщением об ошибке
    header('Location: login.php?error=1');
    exit;
}
?> 