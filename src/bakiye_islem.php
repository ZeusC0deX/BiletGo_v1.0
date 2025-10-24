<?php 
// Oturumu başlat
session_start();

// Veritabanı bağlantısı
require_once 'setup.php';

// --- 1. Güvenlik ve Yetki Kontrolü ---

// A) Kullanıcı giriş yapmış mı?
if (!isset($_SESSION['user_id'])) {
    die("Bu işlemi yapmak için giriş yapmalısınız. <a href='login.php'>GiriŞ Yap</a>");
}

// B) Sadece 'User' (Yolcu) rolü bu işlemi yapabilir
if ($_SESSION['role'] !== 'User') {
    die("Bu işlem sadece 'Yolcu' hesapları içindir. <a href='index.php'>Ana Sayfa</a>");
}

// C) Form POST metodu ile mi geldi?
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    die("Geçersiz istek. <a href='index.php'>Ana Sayfa</a>");
}

// --- 2. Form Verilerini Al ve Doğrula ---
$user_id = $_SESSION['user_id'];
$tutar = $_POST['tutar'] ?? null;

// Tutarın geçerli bir sayı (pozitif) olup olmadığını kontrol et
if (!is_numeric($tutar) || (float)$tutar <= 0) {
    die("Geçersiz tutar. Lütfen en az 1 TL giriniz. <a href='hesabim.php'>Geri Dön</a>");
}

$yuklenecek_tutar = (float)$tutar;
// $kart_no = $_POST['kart_no'];

// --- 3. Bakiye Güncelleme İşlemi ---
try {
    // Kullanıcının mevcut bakiyesine ekleme yap
    $stmt = $pdo->prepare("UPDATE Users SET balance = balance + ? WHERE id = ?");
    $stmt->execute([$yuklenecek_tutar, $user_id]);

    // İşlem başarılı, kullanıcıyı "Hesabım" sayfasına başarı mesajıyla yönlendir
    header("Location: hesabim.php?bakiye_status=success");
    exit;

} catch (Exception $e) {
    // Beklenmedik bir veritabanı hatası oluşursa
    die("Bakiye yüklenirken bir hata oluştu: " . $e->getMessage());
}

?>