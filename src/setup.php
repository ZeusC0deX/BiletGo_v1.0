<?php

$db_path = __DIR__ . '/../database/bilet.sqlite';

try {
    $pdo = new PDO('sqlite:' . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

?>