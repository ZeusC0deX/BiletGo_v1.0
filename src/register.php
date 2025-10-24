<?php
// Oturumu başlat
session_start();

// Veritabanı bağlantımızı dahil edelim
require_once 'setup.php'; 

$hata_mesaji = '';
$basari_mesaji = '';

// 1. Form gönderilmiş mi?
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 2. Form verilerini al
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $parola = $_POST['password'];
    $parola_tekrar = $_POST['password_confirm'];

    // 3. Basit doğrulamalar
    if ($parola !== $parola_tekrar) {
        $hata_mesaji = "Şifreler uyuşmuyor!";
    } elseif (empty($fullname) || empty($email) || empty($parola)) {
        $hata_mesaji = "Tüm alanlar zorunludur!";
    } else {
        
        // 4. Şifreyi güvenli bir şekilde hash'le
        // Bu, login.php'deki password_verify'ın çalışması için ZORUNLUDUR
        $hashed_password = password_hash($parola, PASSWORD_DEFAULT);

        // 5. Kullanıcıyı veritabanına ekle
        try {
            $sql = "INSERT INTO Users (id, fullname, email, password, role, balance) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            // Dökümanda belirtilen Users tablosuna göre
            // (id, fullname, email, password, role, balance)
            $user_id = uniqid('user_', true); // Benzersiz bir ID
            $role = 'User'; // Yeni kullanıcılar her zaman 'User' rolündedir
            $balance = 0.0; // Varsayılan bakiye

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $fullname, $email, $hashed_password, $role, $balance]);

            // Başarılı kayıttan sonra login.php'ye yönlendirebiliriz:
            header("Location: login.php");
            exit;
            
        } catch (PDOException $e) {
            // Hata kodunu kontrol et (SQLSTATE[23000])
            if ($e->getCode() == '23000') {
                $hata_mesaji = "Bu e-posta adresi zaten kayıtlı!";
            } else {
                $hata_mesaji = "Bir hata oluştu: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Kayıt Ol</title>
    <link rel="stylesheet" href="style.css"> 
</head>
<body class="login-page-body">
    <div class="register-container">
        <img src="images/logo.png" alt="BiletGo Logosu" class="logo">
        <h2>Kayıt Ol</h2>
        
        <?php 
            // Hata veya başarı mesajlarını göster
            if (!empty($hata_mesaji)) {
                echo "<p style='color:red;'>$hata_mesaji</p>";
            }
            if (!empty($basari_mesaji)) {
                echo "<p style='color:green;'>$basari_mesaji</p>";
            }
        ?>

        <form action="register.php" method="POST">
            <div>
                <label for="fullname">Kullanıcı Adı:</label>
                <input type="text" id="fullname" name="fullname" required>
            </div>
            <div>
                <label for="email">E-posta:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Şifre:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="password_confirm">Şifre (Tekrar):</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>
            <div>
                <button type="submit">Kayıt Ol</button>
            </div>
        </form>
        
        <p>Zaten bir hesabınız var mı? <a href="login.php" class="secondary-action-link">Giriş Yapın</a></p>
    </div>

</body>
</html>