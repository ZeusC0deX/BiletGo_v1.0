<?php
// Oturumu her zaman en üstte başlat
session_start();

// Veritabanı bağlantımızı dahil edelim
require_once 'setup.php'; 

$hata_mesaji = ''; 

// 1. Form gönderilmiş mi?
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 2. Form verilerini al
    $email = $_POST['email'];
    $parola = $_POST['password'];

    // 3. Kullanıcıyı e-posta ile veritabanında ara
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // 4. Kullanıcı bulundu mu VE şifresi uyuşuyor mu?
    if ($user && password_verify($parola, $user['password'])) {
        
        // 5. Başarılı Giriş: Oturum (Session) değişkenlerini ayarla
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['role'] = $user['role']; // Rol yönetimi için bu çok önemli

        // 6. Ana sayfaya yönlendir
        header("Location: index.php");
        exit;

    } else {
        // Hatalı giriş
        $hata_mesaji = "E-posta veya şifre hatalı!";
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Giriş Yap</title>
    <link rel="stylesheet" href="style.css"> 
</head>
<body class="login-page-body">
    <div class="login-container">
        <img src="images/logo.png" alt="BiletGo Logosu" class="logo">
        <h2>Giriş Yap</h2>
        
        <?php 
            // Hata mesajı varsa göster
            if (!empty($hata_mesaji)) {
                echo "<p style='color:red;'>$hata_mesaji</p>";
            }
        ?>
    
        <form action="login.php" method="POST">
            <div>
                <label for="email">E-posta:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Şifre:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">Giriş Yap</button>
            </div>
        </form>
        
        <p>Hesabınız yok mu? <a href="register.php" class="secondary-action-link">Kayıt Olun</a></p>
    </div>

</body>
</html>