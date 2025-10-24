<?php
// Oturumu başlat
session_start();

// Veritabanı bağlantısı
require_once 'setup.php';

// 1. Güvenlik Kontrolü: Giriş yapmamışsa veya rolü 'Admin' değilse, login'e at.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}

$hata_mesaji = '';
$kupon = null; // Düzenlenecek kuponun bilgilerini tutacak

// 2. Form gönderildi mi? (POST) - GÜNCELLEME İŞLEMİ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Form verilerini al
    $coupon_id = $_POST['coupon_id']; // Gizli alandan gelen kupon ID'si
    $code = trim($_POST['code']);
    $discount_rate = (float)$_POST['discount_rate'];
    $usage_limit = (int)$_POST['usage_limit'];
    $expiration_date = $_POST['expiration_date'];

    // 3. Doğrulama
    if (empty($code) || $discount_rate <= 0 || $discount_rate > 1 || $usage_limit <= 0 || empty($expiration_date)) {
        $hata_mesaji = "Tüm alanlar zorunludur. İndirim oranı 0.01 (1%) ile 1.00 (100%) arasında olmalıdır.";
    } else {
        
        // 4. Veritabanında Güncelle
        try {
            $sql = "UPDATE Coupons SET 
                        code = ?, 
                        discount_rate = ?, 
                        usage_limit = ?, 
                        expiration_date = ?
                    WHERE id = ? AND company_id IS NULL"; // GÜVENLİK: Sadece genel kuponları güncelleyebilir
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $code,
                $discount_rate,
                $usage_limit,
                $expiration_date,
                $coupon_id
            ]);
            
            // 5. Başarılı: Panele yönlendir
            header("Location: admin_kupon_yonetimi.php?status=updated");
            exit;
            
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $hata_mesaji = "Hata: Bu kupon kodu ('$code') zaten kullanılıyor.";
            } else {
                $hata_mesaji = "Bir veritabanı hatası oluştu: " . $e->getMessage();
            }
        }
    }
    
    // Hata oluşursa, formu tekrar göstermek için bilgileri ayarla
    $kupon = $_POST;
    $kupon['id'] = $coupon_id; // ID'yi kaybetmemek için

} else {
    // 6. Sayfa ilk yüklendi mi? (GET) - FORMU DOLDURMA İŞLEMİ
    
    $coupon_id_from_url = $_GET['id'] ?? null;
    if (empty($coupon_id_from_url)) {
        die("Düzenlenecek kupon ID'si bulunamadı.");
    }

    // Kupon bilgilerini çek
    // GÜVENLİK: Sadece company_id'si NULL olanları çek
    $stmt_kupon = $pdo->prepare("SELECT * FROM Coupons WHERE id = ? AND company_id IS NULL");
    $stmt_kupon->execute([$coupon_id_from_url]);
    $kupon = $stmt_kupon->fetch(PDO::FETCH_ASSOC);

    if (!$kupon) {
        die("Genel kupon bulunamadı veya bu kuponu düzenleme yetkiniz yok.");
    }
}

// 7. <input type="date"> formatı (Y-m-d) için tarihi düzelt
if ($kupon) {
    // Hata durumunda (POST'tan gelirse) bu formatlanmış tarih olmayabilir, kontrol et
    if (isset($kupon['expiration_date'])) {
        $kupon['expiration_date_formatted'] = date('Y-m-d', strtotime($kupon['expiration_date']));
    }
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Genel Kupon Düzenle (Admin)</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* admin_kupon_yonetimi.php ile aynı stiller */
        .form-container { border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; max-width: 600px; }
        .form-container div { margin-bottom: 15px; }
        .form-container label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-container input { width: 100%; padding: 8px; box-sizing: border-box; }
    </style>
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGO Logosu" class="logo"></a>
        <h1>Genel Kupon Düzenle (Admin)</h1>
        <nav>
            <a href="admin_kupon_yonetimi.php">Genel Kupon Yönetimine Geri Dön</a>
            <a href="logout.php">Çıkış Yap</a>
        </nav>
    </header>

    <main>

        <?php if (!empty($hata_mesaji)): ?>
            <div style="background-color: #f44336; color: white; padding: 10px; margin-bottom: 15px; border-radius: 5px; max-width: 600px;">
                <?php echo htmlspecialchars($hata_mesaji); ?>
            </div>
        <?php endif; ?>

        <?php if ($kupon): ?>
        <div class="form-container">
            <h3>Genel Kuponu Güncelle</h3>
            <form action="admin_kupon_duzenle.php" method="POST">
                
                <input type="hidden" name="coupon_id" value="<?php echo htmlspecialchars($kupon['id']); ?>">
                
                <div>
                    <label for="code">Kupon Kodu:</label>
                    <input type="text" id="code" name="code" value="<?php echo htmlspecialchars($kupon['code']); ?>" required>
                </div>
                <div>
                    <label for="discount_rate">İndirim Oranı:</label>
                    <input type="number" id="discount_rate" name="discount_rate" min="0.01" max="1.00" step="0.01" value="<?php echo htmlspecialchars($kupon['discount_rate']); ?>" required placeholder="Örn: 0.15 (bu %15 demektir)">
                </div>
                <div>
                    <label for="usage_limit">Kullanım Limiti:</label>
                    <input type="number" id="usage_limit" name="usage_limit" min="1" step="1" value="<?php echo htmlspecialchars($kupon['usage_limit']); ?>" required>
                </div>
                <div>
                    <label for="expiration_date">Son Kullanma Tarihi:</label>
                    <input type="date" id="expiration_date" name="expiration_date" value="<?php echo htmlspecialchars($kupon['expiration_date_formatted'] ?? ''); ?>" required>
                </div>
                <button type="submit">Değişiklikleri Kaydet</button>
            </form>
        </div>
        <?php else: ?>
            <p>Kupon bilgileri yüklenemedi.</p>
        <?php endif; ?>
    </main>

</body>
</html>