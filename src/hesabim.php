<?php
// Oturumu başlat
session_start();

// Veritabanı bağlantısı
require_once 'setup.php';

// 1. Güvenlik Kontrolü: Giriş yapmamışsa veya rolü 'User' değilse, login'e at.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$mesaj = ''; // Başarı mesajları için
$hata_mesaji = ''; // Hata mesajları için

// 2. Diğer sayfalardan gelen durum mesajlarını yakala
if (isset($_GET['buy_status']) && $_GET['buy_status'] == 'success') {
    $mesaj = "Biletiniz başarıyla satın alındı!";
}
if (isset($_GET['bakiye_status']) && $_GET['bakiye_status'] == 'success') {
    $mesaj = "Bakiyeniz başarıyla güncellendi!";
}
if (isset($_GET['cancel_status']) && $_GET['cancel_status'] == 'success') {
    $mesaj = "Biletiniz başarıyla iptal edildi ve ücret iade edildi.";
}
if (isset($_GET['cancel_status']) && $_GET['cancel_status'] == 'error_time') {
    $hata_mesaji = "Hata: Sefere 1 saatten az kaldığı için bilet iptal edilemez.";
}

// 3. Kullanıcının güncel bilgilerini (özellikle bakiyeyi) çek 
$stmt_user = $pdo->prepare("SELECT fullname, email, balance FROM Users WHERE id = ?");
$stmt_user->execute([$user_id]);
$user = $stmt_user->fetch(PDO::FETCH_ASSOC);

// 4. Kullanıcının tüm biletlerini çek (Sefer ve Firma bilgileriyle birleştirerek) [cite: 21]
$sql_tickets = "SELECT 
                    Tickets.id AS ticket_id,
                    Tickets.seat_number,
                    Tickets.status,
                    Buses.departure_location,
                    Buses.arrival_location,
                    Buses.departure_time,
                    Companies.name AS company_name
                FROM Tickets
                JOIN Buses ON Tickets.bus_id = Buses.id
                JOIN Companies ON Buses.company_id = Companies.id
                WHERE Tickets.user_id = ?
                ORDER BY Buses.departure_time DESC"; // En yeni seferler üstte
        
$stmt_tickets = $pdo->prepare($sql_tickets);
$stmt_tickets->execute([$user_id]);
$biletler = $stmt_tickets->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Hesabım ve Biletlerim</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGo Logosu" class="logo"></a>
        <h1>Hesabım</h1>
        <nav>
            <span>Hoşgeldiniz, <?php echo htmlspecialchars($user['fullname']); ?>!</span>
            <a href="index.php">Ana Sayfa</a>
            <a href="logout.php">Çıkış Yap</a>
        </nav>
    </header>

    <main>
        
        <?php if (!empty($mesaj)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($mesaj); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($hata_mesaji)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($hata_mesaji); ?>
            </div>
        <?php endif; ?>

        <div class="bakiye-bolumu" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 20px;">
            <h3>Profil ve Bakiye</h3>
            <p><strong>İsim:</strong> <?php echo htmlspecialchars($user['fullname']); ?></p>
            <p><strong>E-posta:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Mevcut Bakiyeniz:</strong> <strong style="color: blue;"><?php echo number_format($user['balance'], 2); ?> TL</strong></p>
            
            <h4>Bakiye Yükle</h4>
            <form action="bakiye_islem.php" method="POST">
                <label for="tutar">Yüklenecek Tutar (TL):</label> 
                <input type="number" id="tutar" name="tutar" min="10" step="1" required placeholder="100">
                <input type="text" name="kart_no" placeholder="Kart No" style="margin-left: 10px;"> <button type="submit">Ödemeyi Tamamla</button>
            </form>
        </div>

        <div class="biletler-bolumu">
            <h3>Satın Aldığım Biletler</h3>
            
            <?php if (empty($biletler)): ?>
                <p>Henüz satın alınmış biletiniz bulunmamaktadır.</p>
            <?php else: ?>
                <?php foreach ($biletler as $bilet): ?>
                    <div class="bilet-karti" style="border: 1px solid #ccc; padding: 15px; margin-bottom: 10px; <?php echo ($bilet['status'] == 'CANCELLED') ? 'background-color: #f0f0f0; opacity: 0.6;' : ''; ?>">
                        <h4><?php echo htmlspecialchars($bilet['company_name']); ?></h4>
                        <p>
                            <strong>Güzergah:</strong>
                            <?php echo htmlspecialchars($bilet['departure_location']); ?> -> <?php echo htmlspecialchars($bilet['arrival_location']); ?>
                        </p>
                        <p>
                            <strong>Kalkış Tarihi:</strong>
                            <?php echo date('d M Y, H:i', strtotime($bilet['departure_time'])); ?>
                        </p>
                        <p><strong>Koltuk No:</strong> <?php echo $bilet['seat_number']; ?></p>
                        <p><strong>Durum:</strong> <?php echo ($bilet['status'] == 'ACTIVE') ? 'Aktif' : 'İptal Edilmiş'; ?></p>

                        <?php if ($bilet['status'] == 'ACTIVE'): ?>
                            
                            <form action="bilet_iptal.php" method="POST" style="display:inline-block; margin-right: 10px;">
                                <input type="hidden" name="ticket_id" value="<?php echo $bilet['ticket_id']; ?>">
                                <button type="submit" onclick="return confirm('Bu bileti iptal etmek istediğinizden emin misiniz? Ücret iade edilecektir.');">Bileti İptal Et</button>
                            </form>
                            
                            <a href="bilet_pdf.php?id=<?php echo $bilet['ticket_id']; ?>" target="_blank" style="display:inline-block; padding: 5px 10px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;">PDF İndir</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>