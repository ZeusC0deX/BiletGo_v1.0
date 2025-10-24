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

$mesaj = '';
$hata_mesaji = '';

// 2. Form İşlemleri (Ekleme veya Silme)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $action = $_POST['action'] ?? '';

    // --- YENİ GENEL KUPON EKLEME ---
    if ($action == 'add') {
        $code = trim($_POST['code']);
        $discount_rate = (float)$_POST['discount_rate']; // 0.15 (yani %15)
        $usage_limit = (int)$_POST['usage_limit'];
        $expiration_date = $_POST['expiration_date'];

        // Doğrulama
        if (empty($code) || $discount_rate <= 0 || $discount_rate > 1 || $usage_limit <= 0 || empty($expiration_date)) {
            $hata_mesaji = "Tüm alanlar zorunludur. İndirim oranı 0.01 (1%) ile 1.00 (100%) arasında olmalıdır.";
        } else {
            try {
                $coupon_id = 'coup_' . uniqid(true);
                // Dökümana göre, Admin tarafından oluşturulan genel kuponların
                // company_id'si NULL olmalıdır.
                $sql = "INSERT INTO Coupons (id, code, discount_rate, usage_limit, expiration_date, company_id) 
                        VALUES (?, ?, ?, ?, ?, NULL)"; // company_id NULL olarak ayarlandı
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$coupon_id, $code, $discount_rate, $usage_limit, $expiration_date]);
                $mesaj = "Yeni genel kupon ('$code') başarıyla eklendi.";
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $hata_mesaji = "Hata: Bu kupon kodu ('$code') zaten kullanılıyor.";
                } else {
                    $hata_mesaji = "Bir veritabanı hatası oluştu: " . $e->getMessage();
                }
            }
        }
    }
    
    // --- GENEL KUPON SİLME ---
    if ($action == 'delete') {
        $coupon_id = $_POST['coupon_id'];
        if (empty($coupon_id)) {
            $hata_mesaji = "Silinecek kupon ID'si bulunamadı.";
        } else {
            // Güvenlik: Sadece company_id'si NULL olanları sil
            $sql = "DELETE FROM Coupons WHERE id = ? AND company_id IS NULL";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$coupon_id]);
            
            if ($stmt->rowCount() > 0) {
                $mesaj = "Genel kupon başarıyla silindi.";
            } else {
                $hata_mesaji = "Kupon bulunamadı veya bu genel bir kupon değil.";
            }
        }
    }
}

// 3. Sistemdeki TÜM GENEL kuponları çek (company_id'si NULL olanlar)
$stmt_coupons = $pdo->prepare("SELECT * FROM Coupons WHERE company_id IS NULL ORDER BY expiration_date ASC");
$stmt_coupons->execute();
$kuponlar = $stmt_coupons->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Genel Kupon Yönetimi (Admin)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGO Logosu" class="logo"></a>
        <h1>Genel Kupon Yönetimi (Admin)</h1>
        <nav>
            <a href="admin_panel.php" class="nav-button-white">Admin Panele Geri Dön</a>
            <a href="logout.php">Çıkış Yap</a>
        </nav>
    </header>

    <main>

        <?php if (!empty($mesaj)): ?>
            <div style="background-color: lightgreen; padding: 10px; margin-bottom: 15px; border-radius: 5px;">
                <?php echo htmlspecialchars($mesaj); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($hata_mesaji)): ?>
            <div style="background-color: #f44336; color: white; padding: 10px; margin-bottom: 15px; border-radius: 5px;">
                <?php echo htmlspecialchars($hata_mesaji); ?>
            </div>
        <?php endif; ?>

        <div class="form-container" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; max-width: 600px;">
            <h3>Yeni Genel Kupon Oluştur (Tüm Firmalarda Geçerli)</h3>
            <form action="admin_kupon_yonetimi.php" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label for="code">Kupon Kodu:</label>
                    <input type="text" id="code" name="code" required>
                </div>
                <div>
                    <label for="discount_rate">İndirim Oranı:</label>
                    <input type="number" id="discount_rate" name="discount_rate" min="0.01" max="1.00" step="0.01" required placeholder="Örn: 0.15 (bu %15 demektir)">
                </div>
                <div>
                    <label for="usage_limit">Kullanım Limiti:</label>
                    <input type="number" id="usage_limit" name="usage_limit" min="1" step="1" required placeholder="Örn: 100">
                </div>
                <div>
                    <label for="expiration_date">Son Kullanma Tarihi:</label>
                    <input type="date" id="expiration_date" name="expiration_date" required>
                </div>
                <button type="submit">Genel Kuponu Oluştur</button>
            </form>
        </div>

        <h3>Sistemdeki Genel Kuponlar</h3>
        <table border="1" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Kod</th>
                    <th>İndirim Oranı</th>
                    <th>Kullanım Limiti</th>
                    <th>Son Kullanma Tarihi</th>
                    <th>İşlemler (Sil/Düzenle)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($kuponlar)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">Sistemde kayıtlı genel kupon bulunmamaktadır.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($kuponlar as $kupon): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($kupon['code']); ?></td>
                            <td><?php echo ($kupon['discount_rate'] * 100); ?>%</td>
                            <td><?php echo $kupon['usage_limit']; ?></td>
                            <td><?php echo date('d M Y', strtotime($kupon['expiration_date'])); ?></td>
                            <td>
                                <a href="admin_kupon_duzenle.php?id=<?php echo $kupon['id']; ?>">Düzenle</a>
                                |
                                <form action="admin_kupon_yonetimi.php" method="POST" style="display:inline;" onsubmit="return confirm('Bu genel kuponu silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="coupon_id" value="<?php echo $kupon['id']; ?>">
                                    <button type="submit" style="background:none; border:none; color:red; cursor:pointer; padding:0; text-decoration:underline;">Sil</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

    </main>

</body>
</html>