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

// 2. Firma Admin'in bilgilerini (özellikle hangi firmaya ait olduğunu) al
$user_id = $_SESSION['user_id'];
$stmt_user = $pdo->prepare("SELECT company_id, fullname FROM Users WHERE id = ?");
$stmt_user->execute([$user_id]);
$firma_admin = $stmt_user->fetch(PDO::FETCH_ASSOC);

$company_id = $firma_admin['company_id'];
$admin_fullname = $firma_admin['fullname'];

if (empty($company_id)) {
    die("Hata: Hesabınız herhangi bir firmaya atanmamış. Lütfen sistem yöneticisi ile iletişime geçin.");
}

// 3. Firma adını al (Panelde göstermek için)
$stmt_company = $pdo->prepare("SELECT name FROM Companies WHERE id = ?");
$stmt_company->execute([$company_id]);
$company_name = $stmt_company->fetchColumn();

// 4. Bu firmaya ait TÜM seferleri (geçmiş ve gelecek) çek
$stmt_seferler = $pdo->prepare("SELECT * FROM Buses WHERE company_id = ? ORDER BY departure_time DESC");
$stmt_seferler->execute([$company_id]);
$seferler = $stmt_seferler->fetchAll(PDO::FETCH_ASSOC);

// 5. Diğer sayfalardan (örn: sefer_ekle.php) gelen durum mesajları
$mesaj = '';
if (isset($_GET['status']) && $_GET['status'] == 'added') {
    $mesaj = "Yeni sefer başarıyla eklendi.";
}
if (isset($_GET['status']) && $_GET['status'] == 'deleted') {
    $mesaj = "Sefer başarıyla silindi.";
}
if (isset($_GET['status']) && $_GET['status'] == 'updated') {
    $mesaj = "Sefer başarıyla güncellendi.";
}

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Firma Admin Paneli</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGO Logosu" class="logo"></a>
        <h1>Firma Yönetim Paneli</h1>
        <nav>
            <span>Hoşgeldiniz, <?php echo htmlspecialchars($admin_fullname); ?> (<?php echo htmlspecialchars($company_name); ?>)</span>
            <a href="index.php">Ana Sayfa</a>
            <a href="logout.php">Çıkış Yap</a>
        </nav>
    </header>

    <main>
        <h2><?php echo htmlspecialchars($company_name); ?> - Sefer Yönetimi</h2>
        
        <?php if (!empty($mesaj)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($mesaj); ?>
            </div>
        <?php endif; ?>

        <a href="sefer_ekle.php" style="padding: 10px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px;">
            + Yeni Sefer Ekle
        </a>

        <a href="kupon_yonetimi.php">Kupon Yönetimi</a>

        <hr style="margin-top: 20px;">

        <h3>Mevcut Seferler</h3>
        <table border="1" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Güzergah</th>
                    <th>Kalkış Zamanı</th>
                    <th>Varış Zamanı</th>
                    <th>Fiyat</th>
                    <th>Koltuk Sayısı</th>
                    <th>İşlemler</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($seferler)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">Firmanıza ait kayıtlı sefer bulunmamaktadır.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($seferler as $sefer): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($sefer['departure_location']); ?> &rarr; <?php echo htmlspecialchars($sefer['arrival_location']); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($sefer['departure_time'])); ?></td>
                            <td><?php echo date('d M Y, H:i', strtotime($sefer['arrival_time'])); ?></td>
                            <td><?php echo $sefer['price']; ?> TL</td>
                            <td><?php echo $sefer['total_seats']; ?></td>
                            <td>
                                <a href="sefer_duzenle.php?id=<?php echo $sefer['id']; ?>">Düzenle</a>
                                |
                                <form action="sefer_sil.php" method="POST" style="display:inline;" onsubmit="return confirm('Bu seferi silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.');">
                                    <input type="hidden" name="bus_id" value="<?php echo htmlspecialchars($sefer['id']); ?>">
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