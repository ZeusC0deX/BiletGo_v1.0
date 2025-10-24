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

// 3. Form gönderildi mi? (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Form verilerini al
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
        
        // 5. Veritabanına Ekle
        try {
            $bus_id = 'bus_' . uniqid(true); // Yeni benzersiz sefer ID'si
            
            $sql = "INSERT INTO Buses 
                        (id, company_id, departure_location, arrival_location, departure_time, arrival_time, price, total_seats) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $bus_id,
                $company_id, // Güvenlik: Admin'in KENDİ firmasına ekle
                $departure_location,
                $arrival_location,
                $departure_time,
                $arrival_time,
                $price,
                $total_seats
            ]);
            
            // 6. Başarılı: Panele yönlendir
            header("Location: firma_admin_panel.php?status=added");
            exit;
            
        } catch (Exception $e) {
            $hata_mesaji = "Sefer eklenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yeni Sefer Ekle</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGO Logosu" class="logo"></a>
        <h1>Yeni Sefer Ekle</h1>
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

        <form action="sefer_ekle.php" method="POST">
            <div>
                <label for="departure_location">Kalkış Yeri:</label>
                <input type="text" id="departure_location" name="departure_location" required>
            </div>
            <div>
                <label for="arrival_location">Varış Yeri:</label>
                <input type="text" id="arrival_location" name="arrival_location" required>
            </div>
            <div>
                <label for="departure_time">Kalkış Zamanı:</label>
                <input type="datetime-local" id="departure_time" name="departure_time" required>
            </div>
            <div>
                <label for="arrival_time">Varış Zamanı:</label>
                <input type="datetime-local" id="arrival_time" name="arrival_time" required>
            </div>
            <div>
                <label for="price">Fiyat (TL):</label>
                <input type="number" id="price" name="price" min="0.01" step="0.01" required>
            </div>
            <div>
                <label for="total_seats">Toplam Koltuk Sayısı:</label>
                <input type="number" id="total_seats" name="total_seats" min="1" step="1" required>
            </div>
            <div>
                <button type="submit">Seferi Kaydet</button>
            </div>
        </form>

    </main>

</body>
</html>