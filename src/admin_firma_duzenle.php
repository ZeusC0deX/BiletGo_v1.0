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
$company = null; // Düzenlenecek firmanın bilgilerini tutacak

// 2. Form gönderildi mi? (POST) - GÜNCELLEME İŞLEMİ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Form verilerini al
    $company_id = $_POST['company_id']; // Gizli alandan gelen ID
    $company_name = trim($_POST['name']);

    // 3. Doğrulama
    if (empty($company_name)) {
        $hata_mesaji = "Firma adı boş olamaz.";
    } else {
        
        // 4. Veritabanında Güncelle
        try {
            $sql = "UPDATE Companies SET name = ? WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$company_name, $company_id]);
            
            // 5. Başarılı: Panele yönlendir
            // (admin_firma_yonetimi.php'ye status=updated mesajı ekleyebiliriz)
            header("Location: admin_firma_yonetimi.php?status=updated");
            exit;
            
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // UNIQUE constraint
                $hata_mesaji = "Hata: Bu firma adı ('$company_name') zaten kayıtlı.";
            } else {
                $hata_mesaji = "Bir veritabanı hatası oluştu: " . $e->getMessage();
            }
        }
    }
    
    // Hata oluşursa, formu tekrar göstermek için bilgileri ayarla
    $company = $_POST;
    $company['id'] = $company_id; // ID'yi kaybetmemek için

} else {
    // 6. Sayfa ilk yüklendi mi? (GET) - FORMU DOLDURMA İŞLEMİ
    
    $company_id_from_url = $_GET['id'] ?? null;
    if (empty($company_id_from_url)) {
        die("Düzenlenecek firma ID'si bulunamadı.");
    }

    // Firma bilgilerini çek
    $stmt_company = $pdo->prepare("SELECT * FROM Companies WHERE id = ?");
    $stmt_company->execute([$company_id_from_url]);
    $company = $stmt_company->fetch(PDO::FETCH_ASSOC);

    if (!$company) {
        die("Firma bulunamadı.");
    }
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Firma Düzenle (Admin)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGO Logosu" class="logo"></a>
        <h1>Firma Düzenle (Admin)</h1>
        <nav>
            <a href="admin_firma_yonetimi.php">Firma Yönetimine Geri Dön</a>
            <a href="logout.php">Çıkış Yap</a>
        </nav>
    </header>

    <main>

        <?php if (!empty($hata_mesaji)): ?>
            <div style="background-color: #f44336; color: white; padding: 10px; margin-bottom: 15px; border-radius: 5px; max-width: 500px;">
                <?php echo htmlspecialchars($hata_mesaji); ?>
            </div>
        <?php endif; ?>

        <?php if ($company): ?>
        <div class="form-container" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; max-width: 500px;">
            <h3>Firmayı Güncelle</h3>
            <form action="admin_firma_duzenle.php" method="POST">
                
                <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($company['id']); ?>">
                
                <div>
                    <label for="name">Firma Adı:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($company['name']); ?>" required style="width: 300px;">
                </div>
                <button type="submit">Değişiklikleri Kaydet</button>
            </form>
        </div>
        <?php else: ?>
            <p>Firma bilgileri yüklenemedi.</p>
        <?php endif; ?>
    </main>

</body>
</html>