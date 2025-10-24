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

// 2. Admin'in bilgilerini al
$user_id = $_SESSION['user_id'];
$stmt_user = $pdo->prepare("SELECT fullname FROM Users WHERE id = ?");
$stmt_user->execute([$user_id]);
$admin_fullname = $stmt_user->fetchColumn();

// 3. Panelde hızlı bir özet göstermek için verileri çek
// Tüm Firmalar
$all_companies = $pdo->query("SELECT id, name FROM Companies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
// Tüm Firma Adminleri (ve atandıkları firma adı)
$all_firma_admins = $pdo->query("
    SELECT U.fullname, U.email, C.name AS company_name 
    FROM Users U 
    LEFT JOIN Companies C ON U.company_id = C.id 
    WHERE U.role = 'Firma Admin' 
    ORDER BY U.fullname
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Admin Paneli</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGO Logosu" class="logo"></a>
        <h1>Sistem Yöneticisi Paneli (Admin)</h1>
        <nav>
            <span>Hoşgeldiniz, <?php echo htmlspecialchars($admin_fullname); ?>!</span>
            <a href="index.php" class="nav-button-white">Ana Sayfa</a>
            <a href="logout.php">Çıkış Yap</a>
        </nav>
    </header>

    <main>
        <h2>Yönetim Menüsü</h2>
        <div class="admin-menu">
            <a href="admin_firma_yonetimi.php">Firma Yönetimi</a>
            <a href="admin_kullanici_yonetimi.php">Firma Admin Kullanıcı Yönetimi</a>
            <a href="admin_kupon_yonetimi.php">Genel Kupon Yönetimi</a>
        </div>
        
        <hr>

        <h2>Sistem Özeti</h2>
        <div class="summary-tables">
            <div>
                <h3>Kayıtlı Otobüs Firmaları</h3>
                <table>
                    <thead><tr><th>Firma Adı</th></tr></thead>
                    <tbody>
                        <?php if (empty($all_companies)): ?>
                            <tr><td>Kayıtlı firma bulunmuyor.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_companies as $company): ?>
                                <tr><td><?php echo htmlspecialchars($company['name']); ?></td></tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div>
                <h3>Kayıtlı 'Firma Admin' Kullanıcıları</h3>
                <table>
                    <thead><tr><th>İsim</th><th>E-posta</th><th>Atandığı Firma</th></tr></thead>
                    <tbody>
                        <?php if (empty($all_firma_admins)): ?>
                            <tr><td colspan="3">Kayıtlı 'Firma Admin' kullanıcısı bulunmuyor.</td></tr>
                        <?php else: ?>
                            <?php foreach ($all_firma_admins as $fa_user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fa_user['fullname']); ?></td>
                                    <td><?php echo htmlspecialchars($fa_user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($fa_user['company_name'] ?? '<i>Atanmamış</i>'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

</body>
</html>