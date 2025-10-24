<?php 
// Oturumu başlat
session_start();

// Veritabanı bağlantısı
require_once 'setup.php';

// --- 1. Güvenlik ve Yetki Kontrolü ---

// A) Kullanıcı giriş yapmış mı ve rolü 'Firma Admin' mi?
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Firma Admin') {
    die("Bu işlemi yapmak için yetkiniz yok. <a href='login.php'>Giriş Yap</a>");
}

// B) Form POST metodu ile mi geldi? (Güvenlik için)
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    die("Geçersiz istek. <a href='firma_admin_panel.php'>Panele Dön</a>");
}

// C) Firma Admin'in company_id'sini al
$user_id = $_SESSION['user_id'];
$stmt_user = $pdo->prepare("SELECT company_id FROM Users WHERE id = ?");
$stmt_user->execute([$user_id]);
$company_id = $stmt_user->fetchColumn();

if (empty($company_id)) {
    die("Hata: Hesabınız herhangi bir firmaya atanmamış.");
}

// --- 2. Form Verilerini Al ---
$bus_id = $_POST['bus_id'] ?? null;

if (empty($bus_id)) {
    die("Silinecek sefer ID'si bulunamadı. <a href='firma_admin_panel.php'>Panele Dön</a>");
}

// TODO: İleri Düzey Güvenlik: Bu sefere ait 'ACTIVE' bilet varsa
// silinmesine izin verilmemeli veya biletler iptal edilip iade yapılmalı.
// Görev dökümanı bunu belirtmediği için şimdilik doğrudan siliyoruz.

// --- 3. Silme İşlemi ---
try {
    // A) Seferi sil
    // GÜVENLİK: Sadece bu firmanın (company_id) seferini (bus_id) sil.
    $sql = "DELETE FROM Buses WHERE id = ? AND company_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$bus_id, $company_id]);

    // B) Silme işlemi başarılı mı kontrol et (etkilenen satır > 0)
    if ($stmt->rowCount() > 0) {
        // Başarılı: Panele yönlendir
        header("Location: firma_admin_panel.php?status=deleted");
        exit;
    } else {
        // Hiç satır silinmedi (ya sefer ID'si yanlıştı ya da bu firmaya ait değildi)
        die("Silme hatası: Sefer bulunamadı veya bu seferi silme yetkiniz yok. <a href='firma_admin_panel.php'>Panele Dön</a>");
    }

} catch (PDOException $e) {
    // Biletler (Tickets) tablosu bu sefere (Buses) FOREIGN KEY ile bağlı.
    // Eğer bu sefere ait bilet varsa (iptal edilmiş bile olsa),
    // veritabanı (SQLite) "FOREIGN KEY constraint failed" hatası vererek
    // seferin silinmesini engelleyecektir. Bu aslında iyi bir şey.
    if ($e->getCode() == '23000') {
         die("Hata: Bu sefere ait biletler (aktif veya iptal edilmiş) bulunduğu için sefer silinemez. <a href='firma_admin_panel.php'>Panele Dön</a>");
    } else {
        die("Bir veritabanı hatası oluştu: " . $e->getMessage());
    }
}
?>