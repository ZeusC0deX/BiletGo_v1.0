<?php 
// Oturumu her zaman en üstte başlat
session_start();

// Veritabanı bağlantımızı dahil edelim
require_once 'setup.php';

// Kullanıcı giriş yapmış mı?
$is_logged_in = isset($_SESSION['user_id']);
$user_fullname = $is_logged_in ? $_SESSION['fullname'] : '';
$user_role = $is_logged_in ? $_SESSION['role'] : 'Ziyaretçi';


// Formdan gelen verileri al (boşsa null ata)
$kalkis = $_GET['kalkis'] ?? 'Antalya';
$varis = $_GET['varis'] ?? 'Kayseri';

$seferler = []; // Seferleri tutacak boş bir dizi

// Eğer form gönderilmişse (kalkış ve varış boş değilse) arama yap
if (!empty($kalkis) && !empty($varis)) {
    
    // (Kalkış LIKE A AND Varış LIKE B) OR (Kalkış LIKE B AND Varış LIKE A)
    $sql = "SELECT Buses.*, Companies.name AS company_name 
            FROM Buses
            JOIN Companies ON Buses.company_id = Companies.id
            WHERE 
                (Buses.departure_location LIKE ? AND Buses.arrival_location LIKE ?)
                OR 
                (Buses.departure_location LIKE ? AND Buses.arrival_location LIKE ?)
            AND Buses.departure_time > datetime('now')"; // Sadece gelecekteki seferleri göster

    // LIKE operatörü için joker karakterleri ekle (% işareti)
    $param_kalkis = '%' . $kalkis . '%';
    $param_varis = '%' . $varis . '%';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$param_kalkis, $param_varis, $param_varis, $param_kalkis]);
    
    $seferler = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>BiletGo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php">
            <img src="images/logo.png" alt="BiletGo Logosu" class="logo"> 
        </a>
        
        <nav>
            <?php if ($is_logged_in): ?>
                <span>Hoşgeldiniz, <?php echo htmlspecialchars($user_fullname); ?>! (Rol: <?php echo htmlspecialchars($user_role); ?>)</span>
                
                <?php if ($user_role == 'Admin'): ?>
                    <a href="admin_panel.php">Admin Paneli</a>
                <?php endif; ?>
                
                <?php if ($user_role == 'Firma Admin'): ?>
                    <a href="firma_admin_panel.php">Firma Paneli</a>
                <?php endif; ?>

                <?php if ($user_role == 'User'): ?>
                    <a href="hesabim.php">Hesabım / Biletlerim</a>
                <?php endif; ?>
                
                <a href="logout.php">Çıkış Yap</a>
                
            <?php else: ?>
                <span class="visitor-welcome">Hoşgeldiniz, Ziyaretçi!</span>
                <a href="login.php">Giriş Yap</a>
                <a href="register.php">Kayıt Ol</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <h2>Sefer Arama</h2>
        
        <form class="sefer-arama-form" action="index.php" method="GET">
            <div>
                <label for="kalkis">Kalkış Yeri:</label>
                <input type="text" id="kalkis" name="kalkis" value="<?php echo htmlspecialchars($kalkis ?? ''); ?>">
            </div>
            <div>
                <label for="varis">Varış Yeri:</label>
                <input type="text" id="varis" name="varis" value="<?php echo htmlspecialchars($varis ?? ''); ?>">
            </div>
            <button type="submit">Sefer Ara</button>
        </form>
        
        <div class="sefer-listesi">
            <h3>Arama Sonuçları</h3>
            
            <?php if (empty($seferler) && !empty($kalkis)): ?>
                <p style="color: #bbb;">Aradığınız kriterlere (<?php echo htmlspecialchars($kalkis); ?> - <?php echo htmlspecialchars($varis); ?>) uygun sefer bulunamadı.</p>
            
            <?php elseif (empty($seferler) && empty($kalkis)): ?>
                <p>Lütfen kalkış ve varış noktalarını seçerek arama yapınız.</p>
                
            <?php else: ?>
                <?php foreach ($seferler as $sefer): ?>
                    <div class="sefer-list-item">
                        <span class="sefer-firma"><?php echo htmlspecialchars($sefer['company_name']); ?></span>
                        <span class="sefer-guzergah">
                            <strong><?php echo htmlspecialchars($sefer['departure_location']); ?></strong> &rarr; <strong><?php echo htmlspecialchars($sefer['arrival_location']); ?></strong>
                        </span>
                        <span class="sefer-zaman"><?php echo date('d M Y, H:i', strtotime($sefer['departure_time'])); ?></span>
                        <span class="sefer-fiyat"><?php echo htmlspecialchars($sefer['price']); ?> TL</span>
                        <div class="sefer-actions">
                            <a href="sefer_detay.php?id=<?php echo $sefer['id']; ?>" class="sefer-detay-button">
                                Sefer Detayları
                            </a>
                            <a href="sefer_detay.php?id=<?php echo $sefer['id']; ?>" class="sefer-detay-button">Bilet Al</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        </main>

</body>
</html>