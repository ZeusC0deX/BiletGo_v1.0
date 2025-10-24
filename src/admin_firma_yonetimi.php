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

    // --- YENİ FİRMA EKLEME ---
    if ($action == 'add') {
        $company_name = trim($_POST['name']);

        if (empty($company_name)) {
            $hata_mesaji = "Firma adı boş olamaz.";
        } else {
            try {
                $company_id = 'comp_' . uniqid(true);
                $sql = "INSERT INTO Companies (id, name) VALUES (?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$company_id, $company_name]);
                $mesaj = "Yeni firma ('$company_name') başarıyla eklendi.";
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') { // UNIQUE constraint
                    $hata_mesaji = "Hata: Bu firma adı ('$company_name') zaten kayıtlı.";
                } else {
                    $hata_mesaji = "Bir veritabanı hatası oluştu: " . $e->getMessage();
                }
            }
        }
    }
    
    // --- FİRMA SİLME ---
    if ($action == 'delete') {
        $company_id = $_POST['company_id'];
        if (empty($company_id)) {
            $hata_mesaji = "Silinecek firma ID'si bulunamadı.";
        } else {
            try {
                // Not: Bu firmaya atanmış 'Firma Admin' veya 'Sefer' varsa,
                // FOREIGN KEY kısıtlaması nedeniyle silme işlemi hata verecektir.
                // Bu, veri bütünlüğü için istenen bir durumdur.
                $sql = "DELETE FROM Companies WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$company_id]);
                
                if ($stmt->rowCount() > 0) {
                    $mesaj = "Firma başarıyla silindi.";
                } else {
                    $hata_mesaji = "Firma bulunamadı.";
                }
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') {
                    $hata_mesaji = "Hata: Bu firmaya ait seferler veya firma adminleri olduğu için firma silinemez.";
                } else {
                    $hata_mesaji = "Bir veritabanı hatası oluştu: " . $e->getMessage();
                }
            }
        }
    }
}

// 3. Sistemdeki TÜM firmaları çek
$all_companies = $pdo->query("SELECT * FROM Companies ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Firma Yönetimi (Admin)</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGo Logosu" class="logo"></a>
        <h1>Firma Yönetimi (Admin)</h1>
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

        <div class="form-container" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 20px; max-width: 500px;">
            <h3>Yeni Otobüs Firması Oluştur</h3>
            <form action="admin_firma_yonetimi.php" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label for="name">Firma Adı:</label>
                    <input type="text" id="name" name="name" required style="width: 300px;">
                </div>
                <button type="submit">Firmayı Oluştur</button>
            </form>
        </div>

        <h3>Sistemdeki Firmalar</h3>
        <table border="1" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Firma Adı</th>
                    <th>İşlemler (Sil/Düzenle)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_companies)): ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">Sistemde kayıtlı firma bulunmamaktadır.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_companies as $company): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($company['id']); ?></td>
                            <td><?php echo htmlspecialchars($company['name']); ?></td>
                            <td>
                                <a href="admin_firma_duzenle.php?id=<?php echo $company['id']; ?>">Düzenle</a>
                                |
                                <form action="admin_firma_yonetimi.php" method="POST" style="display:inline;" onsubmit="return confirm('Bu firmayı silmek istediğinizden emin misiniz? Bu firmaya ait kullanıcılar veya seferler varsa SİLİNEMEZ.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
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