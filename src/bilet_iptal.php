<?php
// Oturumu başlat
session_start();

// Veritabanı bağlantısı
require_once 'setup.php';

// --- 1. Güvenlik ve Yetki Kontrolü ---

// A) Kullanıcı giriş yapmış mı?
if (!isset($_SESSION['user_id'])) {
    die("Bu işlemi yapmak için giriş yapmalısınız.");
}

// B) Sadece 'User' (Yolcu) rolü bu işlemi yapabilir
if ($_SESSION['role'] !== 'User') {
    die("Bu işlem sadece 'Yolcu' hesapları içindir.");
}

// C) Form POST metodu ile mi geldi?
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    die("Geçersiz istek.");
}

// --- 2. Form Verilerini Al ---
$user_id = $_SESSION['user_id'];
$ticket_id = $_POST['ticket_id'] ?? null;

if (empty($ticket_id)) {
    die("Bilet ID'si bulunamadı.");
}

// --- 3. İptal İşlemi (Transaction) ---
try {
    // A) Veritabanı Transaction başlat
    $pdo->beginTransaction();

    // B) İptal edilecek biletin bilgilerini al
    // Biletin varlığını, kullanıcıya ait olduğunu, durumunun 'ACTIVE' olduğunu
    // ve seferin kalkış saati ile fiyatını tek bir sorguda alalım.
    $sql_check = "SELECT 
                    T.id,
                    B.price,
                    B.departure_time
                  FROM Tickets T
                  JOIN Buses B ON T.bus_id = B.id
                  WHERE T.id = ? 
                  AND T.user_id = ? 
                  AND T.status = 'ACTIVE'";
    
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([$ticket_id, $user_id]);
    $ticket = $stmt_check->fetch(PDO::FETCH_ASSOC);

    // C) Bilet bulunamadı mı? (Kullanıcıya ait değil veya zaten iptal edilmiş)
    if (!$ticket) {
        $pdo->rollBack();
        die("İptal edilecek geçerli bir bilet bulunamadı.");
    }

    // D) ZAMAN KURALI: Kalkışa 1 saatten az mı kalmış? 
    $kalkis_saati_timestamp = strtotime($ticket['departure_time']);
    $simdiki_saat_timestamp = time();
    $kalan_saniye = $kalkis_saati_timestamp - $simdiki_saat_timestamp;
    
    // Kalan saniye 3600'den (1 saat) azsa, iptal edilemez.
    if ($kalan_saniye < 3600) {
        $pdo->rollBack();
        // Kullanıcıyı "Hesabım" sayfasına hata mesajıyla yönlendir 
        header("Location: hesabim.php?cancel_status=error_time");
        exit;
    }

    // E) Kural başarılı, iptal et ve parayı iade et.
    $iade_tutari = (float)$ticket['price'];

    // 1. Bileti 'CANCELLED' olarak güncelle
    $stmt_cancel = $pdo->prepare("UPDATE Tickets SET status = 'CANCELLED' WHERE id = ?");
    $stmt_cancel->execute([$ticket_id]);

    // 2. Bilet ücretini kullanıcının bakiyesine iade et [cite: 24, 21]
    $stmt_refund = $pdo->prepare("UPDATE Users SET balance = balance + ? WHERE id = ?");
    $stmt_refund->execute([$iade_tutari, $user_id]);

    // F) Her şey başarılı, işlemi onayla
    $pdo->commit();

    // G) Kullanıcıyı "Hesabım" sayfasına başarı mesajıyla yönlendir [cite: 24, 21]
    header("Location: hesabim.php?cancel_status=success");
    exit;

} catch (Exception $e) {
    // H) Bir hata oluştu, tüm işlemleri geri al
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Bilet iptali sırasında beklenmedik bir hata oluştu: ". $e->getMessage());
}

?>