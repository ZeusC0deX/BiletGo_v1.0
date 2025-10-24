<?php 
// Oturumu başlat
session_start();

// Veritabanı bağlantısı
require_once 'setup.php';

// --- 1. Güvenlik ve Yetki Kontrolü ---

// A) Kullanıcı giriş yapmış mı?
if (!isset($_SESSION['user_id'])) {
    die("Bu işlemi yapmak için giriş yapmalısınız. <a href='login.php'>Giriş Yap</a>");
}

// B) Sadece 'User' (Yolcu) rolü bu işlemi yapabilir
if ($_SESSION['role'] !== 'User') {
    die("Sadece 'Yolcu' hesapları bilet alabilir. <a href='index.php'>Ana Sayfa</a>");
}

// C) Form POST metodu ile mi geldi?
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    die("Geçersiz istek. <a href='index.php'>Ana Sayfa</a>");
}

// --- 2. Form Verilerini Al ---
$user_id = $_SESSION['user_id'];
$bus_id = $_POST['bus_id'] ?? null;
$seat_number = $_POST['seat_number'] ?? null;
$original_price = (float)($_POST['price'] ?? 0); // Orijinal Fiyat
$coupon_code = trim($_POST['coupon_code'] ?? ''); // Kupon Kodu

// D) Veriler geçerli mi?
if (empty($bus_id) || empty($seat_number) || $original_price <= 0) {
    die("Eksik veya hatalı bilgi. Lütfen sefer seçimine geri dönün.");
}

// --- YENİ KUPON KONTROLÜ (İşlemden Önce) ---

$price_to_pay = $original_price; // Ödenecek fiyat (şimdilik orijinal fiyat)
$valid_coupon_id = null; // Geçerli bir kupon kullanılırsa ID'sini burada tutacağız

if (!empty($coupon_code)) {
    
    // A) Bilet alınacak seferin firma ID'sini (company_id) bul
    $stmt_bus_comp = $pdo->prepare("SELECT company_id FROM Buses WHERE id = ?");
    $stmt_bus_comp->execute([$bus_id]);
    $bus_company_id = $stmt_bus_comp->fetchColumn();

    if (!$bus_company_id) {
        die("Sefer bilgisi bulunamadı. Kupon kontrol edilemiyor.");
    }

    // B) Geçerli bir kupon ara
    // 1. Kodu eşleşmeli
    // 2. Tarihi geçmemiş olmalı (expiration_date)
    // 3. Limiti 0'dan büyük olmalı (usage_limit)
    // 4. (En önemlisi) Kupon ya GENEL (company_id IS NULL) ya da BU FİRMAYA (company_id = ?) ait olmalı
    $sql_coupon = "SELECT id, discount_rate FROM Coupons 
                   WHERE code = ? 
                   AND expiration_date >= date('now')
                   AND usage_limit > 0
                   AND (company_id IS NULL OR company_id = ?)";
                   
    $stmt_coupon = $pdo->prepare($sql_coupon);
    $stmt_coupon->execute([$coupon_code, $bus_company_id]);
    $coupon = $stmt_coupon->fetch(PDO::FETCH_ASSOC);

    if ($coupon) {
        // Kupon GEÇERLİ! İndirimi uygula
        $indirim_orani = (float)$coupon['discount_rate'];
        $price_to_pay = $original_price * (1 - $indirim_orani);
        $valid_coupon_id = $coupon['id']; // Limiti düşürmek için ID'yi kaydet
        
        // Fiyatı 2 ondalığa yuvarla (kuruş)
        $price_to_pay = round($price_to_pay, 2); 
        
    } else {
        // Kupon GEÇERSİZ!
        die("Girdiğiniz kupon kodu geçersiz, süresi dolmuş, limiti bitmiş veya bu sefer için geçerli değil. <a href='sefer_detay.php?id=$bus_id'>Geri Dön</a>");
    }
}
// --- YENİ KUPON KONTROLÜ BİTTİ ---


// --- 3. Bilet Alma İşlemi (Transaction) ---
try {
    // A) Veritabanı Transaction başlat
    $pdo->beginTransaction();

    // B) Kullanıcının mevcut bakiyesini kontrol et
    $stmt_balance = $pdo->prepare("SELECT balance FROM Users WHERE id = ?");
    $stmt_balance->execute([$user_id]);
    $user_balance = (float)$stmt_balance->fetchColumn();

    // C) Bakiye yeterli mi? (İndirimli fiyatı [$price_to_pay] kontrol et)
    if ($user_balance < $price_to_pay) {
        $pdo->rollBack(); // İşlemi iptal et
        die("Yetersiz bakiye! Bakiyeniz: $user_balance TL. Gerekli (indirimli) Tutar: $price_to_pay TL. <a href='hesabim.php'>Bakiye Yükle</a>");
    }

    // D) Koltuk hala boş mu? (Çifte satın almayı önleme)
    $stmt_check_seat = $pdo->prepare("SELECT id FROM Tickets WHERE bus_id = ? AND seat_number = ? AND status = 'ACTIVE'");
    $stmt_check_seat->execute([$bus_id, $seat_number]);
    
    if ($stmt_check_seat->fetch()) {
        $pdo->rollBack(); // İşlemi iptal et
        die("Üzgünüz, siz işlemi tamamlarken bu koltuk (#$seat_number) başkası tarafından alındı. <a href='sefer_detay.php?id=$bus_id'>Lütfen başka bir koltuk seçin.</a>");
    }

    // E) Bakiye yeterli ve koltuk boş. İşlemleri gerçekleştir.

    // 1. Bakiyeyi Düşür (İndirimli fiyata göre)
    $new_balance = $user_balance - $price_to_pay;
    $stmt_update_balance = $pdo->prepare("UPDATE Users SET balance = ? WHERE id = ?");
    $stmt_update_balance->execute([$new_balance, $user_id]);

    // 2. Bileti Oluştur
    $ticket_id = 'tkt_' . uniqid(true); // Benzersiz bilet ID'si
    $stmt_insert_ticket = $pdo->prepare("INSERT INTO Tickets (id, user_id, bus_id, seat_number, status) VALUES (?, ?, ?, ?, 'ACTIVE')");
    $stmt_insert_ticket->execute([$ticket_id, $user_id, $bus_id, $seat_number]);

    // --- (YENİ ADIM 3) KUPON LİMİTİNİ DÜŞÜR ---
    // Eğer bu işlemde geçerli bir kupon kullanıldıysa (ID'si kaydedildiyse)
    // o kuponun 'usage_limit'ini 1 azalt.
    if ($valid_coupon_id) {
        $stmt_update_coupon = $pdo->prepare("UPDATE Coupons SET usage_limit = usage_limit - 1 WHERE id = ?");
        $stmt_update_coupon->execute([$valid_coupon_id]);
    }
    // ------------------------------------------

    // F) Her şey başarılı, işlemi onayla
    $pdo->commit();

    // G) Kullanıcıyı "Hesabım / Biletlerim" sayfasına yönlendir
    header("Location: hesabim.php?buy_status=success");
    exit;

} catch (Exception $e) {
    // H) Bir hata oluştu, tüm işlemleri geri al
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Bilet alımı sırasında beklenmedik bir hata oluştu: " . $e->getMessage());
}

?>