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
$user_to_edit = null; // Düzenlenecek kullanıcının bilgilerini tutacak

// 2. Form gönderildi mi? (POST) - GÜNCELLEME İŞLEMİ
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Form verilerini al
    $user_id = $_POST['user_id']; // Gizli alandan gelen ID
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password']; // Yeni şifre (boş olabilir)
    $company_id = $_POST['company_id']; // Atanacak firmanın ID'si

    // 3. Doğrulama
    if (empty($fullname) || empty($email) || empty($company_id)) {
        $hata_mesaji = "İsim, E-posta ve Atanacak Firma alanları zorunludur.";
    } elseif (!empty($password) && strlen($password) < 6) {
        $hata_mesaji = "Yeni şifre girildiyse en az 6 karakter olmalıdır.";
    } else {
        
        // 4. Veritabanında Güncelle
        try {
            // Şifre alanı doluysa: Şifreyi de güncelle
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE Users SET 
                            fullname = ?, 
                            email = ?, 
                            password = ?, 
                            company_id = ?
                        WHERE id = ? AND role = 'Firma Admin'";
                $params = [$fullname, $email, $hashed_password, $company_id, $user_id];
            } else {
            // Şifre alanı boşsa: Şifreyi GÜNCELLEME (mevcut şifreyi koru)
                $sql = "UPDATE Users SET 
                            fullname = ?, 
                            email = ?, 
                            company_id = ?
                        WHERE id = ? AND role = 'Firma Admin'";
                $params = [$fullname, $email, $company_id, $user_id];
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // 5. Başarılı: Panele yönlendir
            header("Location: admin_kullanici_yonetimi.php?status=updated");
            exit;
            
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // UNIQUE constraint
                $hata_mesaji = "Hata: Bu e-posta adresi ('$email') zaten kayıtlı.";
            } else {
                $hata_mesaji = "Bir veritabanı hatası oluştu: " . $e->getMessage();
            }
        }
    }
    
    // Hata oluşursa, formu tekrar göstermek için bilgileri ayarla
    $user_to_edit = $_POST;
    $user_to_edit['id'] = $user_id; // ID'yi kaybetmemek için

} else {
    // 6. Sayfa ilk yüklendi mi? (GET) - FORMU DOLDURMA İŞLEMİ
    
    $user_id_from_url = $_GET['id'] ?? null;
    if (empty($user_id_from_url)) {
        die("Düzenlenecek kullanıcı ID'si bulunamadı.");
    }

    // Kullanıcı bilgilerini çek (Sadece 'Firma Admin' rolündekileri)
    $stmt_user = $pdo->prepare("SELECT id, fullname, email, company_id FROM Users WHERE id = ? AND role = 'Firma Admin'");
    $stmt_user->execute([$user_id_from_url]);
    $user_to_edit = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$user_to_edit) {
        die("Kullanıcı bulunamadı veya bu bir 'Firma Admin' kullanıcısı değil.");
    }
}

// 7. Formdaki <select> için TÜM firmaları çek
$all_companies = $pdo->query("SELECT id, name FROM Companies ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Firma Admin Kullanıcı Düzenle</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGO Logosu" class="logo"></a>
        <h1>Firma Admin Kullanıcı Düzenle</h1>
        <nav>
            <a href="admin_kullanici_yonetimi.php">Kullanıcı Yönetimine Geri Dön</a>
            <a href="logout.php">Çıkış Yap</a>
        </nav>
    </header>

    <main>

        <?php if (!empty($hata_mesaji)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($hata_mesaji); ?>
            </div>
        <?php endif; ?>

        <?php if ($user_to_edit): ?>
        <div class="form-container">
            <h3>Kullanıcıyı Güncelle: <?php echo htmlspecialchars($user_to_edit['fullname']); ?></h3>
            <form action="admin_kullanici_duzenle.php" method="POST">
                
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_to_edit['id']); ?>">
                
                <div>
                    <label for="fullname">İsim Soyisim:</label>
                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user_to_edit['fullname']); ?>" required>
                </div>
                <div>
                    <label for="email">E-posta (Giriş Adı):</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email']); ?>" required>
                </div>
                <div>
                    <label for="password">Yeni Şifre (İsteğe bağlı):</label>
                    <input type="password" id="password" name="password" placeholder="Değiştirmek istemiyorsanız boş bırakın">
                </div>
                <div>
                    <label for="company_id">Atanacak Firma:</label>
                    <select id="company_id" name="company_id" required>
                        <option value="">-- Lütfen bir firma seçin --</option>
                        <?php foreach ($all_companies as $company): ?>
                            <option 
                                value="<?php echo htmlspecialchars($company['id']); ?>"
                                <?php if ($company['id'] == $user_to_edit['company_id']) echo 'selected'; // Mevcut firmasını seçili getir ?>
                            >
                                <?php echo htmlspecialchars($company['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit">Değişiklikleri Kaydet</button>
            </form>
        </div>
        <?php else: ?>
            <p>Kullanıcı bilgileri yüklenemedi.</p>
        <?php endif; ?>
    </main>

</body>
</html>