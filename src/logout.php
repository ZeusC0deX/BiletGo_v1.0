<?php
// 1. Oturumu başlat
session_start();

// 2. Tüm oturum değişkenlerini temizle
$_SESSION = array();

// 3. Oturumu sonlandır
session_destroy();

// 4. Kullanıcıyı giriş sayfasına yönlendir
header("Location: login.php");
exit;
?>