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

    // --- YENİ FİRMA ADMİN KULLANICISI EKLEME ---
    if ($action == 'add') {
        $fullname = trim($_POST['fullname']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $company_id = $_POST['company_id']; // Atanacak firmanın ID'si

        // Basit doğrulama
        if (empty($fullname) || empty($email) || empty($password) || empty($company_id)) {
            $hata_mesaji = "Tüm alanlar zorunludur (İsim, E-posta, Şifre, Atanacak Firma).";
        } elseif (strlen($password) < 6) {
            $hata_mesaji = "Şifre en az 6 karakter olmalıdır.";
        } else {
            try {
                $user_id = 'user_' . uniqid(true);
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'Firma Admin'; // Rolü 'Firma Admin' olarak ayarla

                $sql = "INSERT INTO Users (id, fullname, email, password, role, company_id) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id, $fullname, $email, $hashed_password, $role, $company_id]);
                
                $mesaj = "Yeni 'Firma Admin' kullanıcısı ('$email') başarıyla oluşturuldu ve firmaya atandı.";
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') { // UNIQUE constraint
                    $hata_mesaji = "Hata: Bu e-posta adresi ('$email') zaten kayıtlı.";
                } else {
                    $hata_mesaji = "Bir veritabanı hatası oluştu: " . $e->getMessage();
                }
            }
        }
    }
    
    // --- FİRMA ADMİN KULLANICISI SİLME ---
    if ($action == 'delete') {
        $user_id_to_delete = $_POST['user_id'];
        if (empty($user_id_to_delete)) {
            $hata_mesaji = "Silinecek kullanıcı ID'si bulunamadı.";
        } else {
            try {
                // Sadece rolü 'Firma Admin' olanları sil
                $sql = "DELETE FROM Users WHERE id = ? AND role = 'Firma Admin'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user_id_to_delete]);
                
                if ($stmt->rowCount() > 0) {
                    $mesaj = "'Firma Admin' kullanıcısı başarıyla silindi.";
                } else {
                    $hata_mesaji = "Kullanıcı bulunamadı veya bu bir 'Firma Admin' kullanıcısı değil.";
                }
            } catch (PDOException $e) {
                // Bu kullanıcıya ait bilet vb. varsa silinmesi engellenebilir (dökümanda yok)
                $hata_mesaji = "Bir veritabanı hatası oluştu: " . $e->getMessage();
            }
        }
    }
}

// 3. Verileri Çek (Listeleme ve Form için)
// A) Sistemdeki TÜM 'Firma Admin' kullanıcılarını çek (Firma adıyla birlikte)
$all_firma_admins = $pdo->query("
    SELECT U.id, U.fullname, U.email, C.name AS company_name 
    FROM Users U 
    LEFT JOIN Companies C ON U.company_id = C.id 
    WHERE U.role = 'Firma Admin' 
    ORDER BY U.fullname
")->fetchAll(PDO::FETCH_ASSOC);

// B) Sistemdeki TÜM firmaları çek (Yeni kullanıcı ataması için <select> doldurmak)
$all_companies = $pdo->query("SELECT id, name FROM Companies ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Firma Admin Kullanıcı Yönetimi</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGO Logosu" class="logo"></a>
        <h1>Firma Admin Kullanıcı Yönetimi</h1>
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
            <h3>Yeni 'Firma Admin' Kullanıcısı Oluştur</h3>
            <form action="admin_kullanici_yonetimi.php" method="POST">
                <input type="hidden" name="action" value="add">
                
                <div>
                    <label for="fullname">Kullanıcı Adı:</label>
                    <input type="text" id="fullname" name="fullname" required>
                </div>
                <div>
                    <label for="email">E-posta (Giriş Adı):</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div>
                    <label for="password">Varsayılan Şifre:</label>
                    <input type="password" id="password" name="password" required placeholder="En az 6 karakter">
                </div>
                <div>
                    <label for="company_id">Atanacak Firma:</label>
                    <select id="company_id" name="company_id" required>
                        <option value="">-- Lütfen bir firma seçin --</option>
                        <?php foreach ($all_companies as $company): ?>
                            <option value="<?php echo htmlspecialchars($company['id']); ?>">
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Kullanıcıyı Oluştur ve Ata</button>
            </form>
        </div>

        <h3>Sistemdeki 'Firma Admin' Kullanıcıları</h3>
        <table border="1" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Kullanıcı Adı</th>
                    <th>E-posta</th>
                    <th>Atandığı Firma</th>
                    <th>İşlemler (Sil/Düzenle)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($all_firma_admins)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center;">Sistemde kayıtlı 'Firma Admin' kullanıcısı bulunmamaktadır.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($all_firma_admins as $fa_user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($fa_user['fullname']); ?></td>
                            <td><?php echo htmlspecialchars($fa_user['email']); ?></td>
                            <td><?php echo htmlspecialchars($fa_user['company_name'] ?? '<i>Atanmamış</i>'); ?></td>
                            <td>
                                <a href="admin_kullanici_duzenle.php?id=<?php echo $fa_user['id']; ?>">Düzenle</a>
                                |
                                <form action="admin_kullanici_yonetimi.php" method="POST" style="display:inline;" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $fa_user['id']; ?>">
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