<?php
session_start();

// Очищаем все данные сессии
$_SESSION = array();

// Уничтожаем сессию
session_destroy();

// Перенаправляем на главную страницу
header('Location: index.php');
exit;
?> 