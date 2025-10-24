<?php 
// Oturumu başlat
session_start();

// Veritabanı bağlantısı
require_once 'setup.php';

// 1. Güvenlik Kontrolü: Giriş yapmamışsa veya rolü 'Firma Admin' değilse, login'e at.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Firma Admin') {
    header("Location: login.php");
    exit;
}

// 2. Firma Admin'in company_id'sini al
$user_id = $_SESSION['user_id'];
$stmt_user = $pdo->prepare("SELECT company_id FROM Users WHERE id = ?");
$stmt_user->execute([$user_id]);
$company_id = $stmt_user->fetchColumn();

if (empty($company_id)) {
    die("Hata: Hesabınız herhangi bir firmaya atanmamış.");
}

$hata_mesaji = '';
$sefer = null; // Düzenlenecek seferin bilgilerini tutacak

// 3. Form gönderildi mi? (POST) - GÜNCELLEME İŞLEMİ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Form verilerini al
    $bus_id = $_POST['bus_id']; // Gizli alandan gelen sefer ID'si
    $departure_location = $_POST['departure_location'];
    $arrival_location = $_POST['arrival_location'];
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $price = (float)$_POST['price'];
    $total_seats = (int)$_POST['total_seats'];

    // 4. Basit Doğrulama
    if (empty($departure_location) || empty($arrival_location) || empty($departure_time) || empty($arrival_time) || $price <= 0 || $total_seats <= 0) {
        $hata_mesaji = "Tüm alanlar zorunludur ve fiyat/koltuk sayısı 0'dan büyük olmalıdır.";
    } elseif (strtotime($arrival_time) <= strtotime($departure_time)) {
        $hata_mesaji = "Varış saati, kalkış saatinden daha ileri bir tarihte olmalıdır.";
    } else {
        
        // 5. Veritabanında Güncelle
        try {
            $sql = "UPDATE Buses SET 
                        departure_location = ?, 
                        arrival_location = ?, 
                        departure_time = ?, 
                        arrival_time = ?, 
                        price = ?, 
                        total_seats = ?
                    WHERE id = ? AND company_id = ?"; // GÜVENLİK: Sadece kendi firmasının seferini güncelleyebilir
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $departure_location,
                $arrival_location,
                $departure_time,
                $arrival_time,
                $price,
                $total_seats,
                $bus_id,
                $company_id // Güvenlik kilidi
            ]);
            
            // 6. Başarılı: Panele yönlendir
            header("Location: firma_admin_panel.php?status=updated");
            exit;
            
        } catch (Exception $e) {
            $hata_mesaji = "Sefer güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
    
    // Hata oluşursa, formu tekrar göstermek için sefer bilgilerini (yeni girilen haliyle) tekrar ayarla
    $sefer = $_POST;

} else {
    // 7. Sayfa ilk yüklendi mi? (GET) - FORMU DOLDURMA İŞLEMİ
    
    $bus_id_from_url = $_GET['id'] ?? null;
    if (empty($bus_id_from_url)) {
        die("Düzenlenecek sefer ID'si bulunamadı.");
    }

    // Sefer bilgilerini çek
    // GÜVENLİK: Sadece bu firmaya (company_id) aitse çek
    $stmt_sefer = $pdo->prepare("SELECT * FROM Buses WHERE id = ? AND company_id = ?");
    $stmt_sefer->execute([$bus_id_from_url, $company_id]);
    $sefer = $stmt_sefer->fetch(PDO::FETCH_ASSOC);

    if (!$sefer) {
        die("Sefer bulunamadı veya bu seferi düzenleme yetkiniz yok.");
    }
}

// 8. datetime-local input'unun "value" formatı (Y-m-d\TH:i) için tarihleri düzelt
// (Veritabanındaki format 'Y-m-d H:i:s' idi, 'T' ile ayırmalıyız)
if ($sefer) {
    $sefer['departure_time_formatted'] = date('Y-m-d\TH:i', strtotime($sefer['departure_time']));
    $sefer['arrival_time_formatted'] = date('Y-m-d\TH:i', strtotime($sefer['arrival_time']));
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sefer Düzenle</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGO Logosu" class="logo"></a>
        <h1>Sefer Düzenle</h1>
        <nav>
            <a href="firma_admin_panel.php">Panele Geri Dön</a>
            <a href="logout.php">Çıkış Yap</a>
        </nav>
    </header>

    <main>
        
        <?php if (!empty($hata_mesaji)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($hata_mesaji); ?>
            </div>
        <?php endif; ?>

        <?php if ($sefer): ?>
        <form action="sefer_duzenle.php" method="POST">
            
            <input type="hidden" name="bus_id" value="<?php echo htmlspecialchars($sefer['id']); ?>">
            
            <div>
                <label for="departure_location">Kalkış Yeri:</label>
                <input type="text" id="departure_location" name="departure_location" value="<?php echo htmlspecialchars($sefer['departure_location']); ?>" required>
            </div>
            <div>
                <label for="arrival_location">Varış Yeri:</label>
                <input type="text" id="arrival_location" name="arrival_location" value="<?php echo htmlspecialchars($sefer['arrival_location']); ?>" required>
            </div>
            <div>
                <label for="departure_time">Kalkış Zamanı:</label>
                <input type="datetime-local" id="departure_time" name="departure_time" value="<?php echo htmlspecialchars($sefer['departure_time_formatted']); ?>" required>
            </div>
            <div>
                <label for="arrival_time">Varış Zamanı:</label>
                <input type="datetime-local" id="arrival_time" name="arrival_time" value="<?php echo htmlspecialchars($sefer['arrival_time_formatted']); ?>" required>
            </div>
            <div>
                <label for="price">Fiyat (TL):</label>
                <input type="number" id="price" name="price" min="0.01" step="0.01" value="<?php echo htmlspecialchars($sefer['price']); ?>" required>
            </div>
            <div>
                <label for="total_seats">Toplam Koltuk Sayısı:</label>
                <input type="number" id="total_seats" name="total_seats" min="1" step="1" value="<?php echo htmlspecialchars($sefer['total_seats']); ?>" required>
            </div>
            <div>
                <button type="submit">Değişiklikleri Kaydet</button>
            </div>
        </form>
        <?php else: ?>
            <p>Sefer bilgileri yüklenemedi.</p>
        <?php endif; ?>

    </main>

</body>
</html>