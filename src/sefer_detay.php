<?php
// Oturumu her zaman en üstte başlat
session_start();

// Veritabanı bağlantımızı dahil edelim
require_once 'setup.php';

// Kullanıcı bilgilerini al
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$user_fullname = $is_logged_in ? $_SESSION['fullname'] : '';
$user_role = $is_logged_in ? $_SESSION['role'] : 'Ziyaretçi';

// Hata mesajı için değişken
$hata = '';

// Seçili koltuğu tutmak için (Kupon hatası vb. durumlarda formu tekrar doldurmak için)
$selected_seat = $_POST['seat_number'] ?? null;

// 1. URL'den sefer ID'sini al
$bus_id = $_GET['id'] ?? null;

if (!$bus_id) {
    die("Sefer ID'si bulunamadı.");
}

// 2. Sefer bilgilerini veritabanından çek (Firma adıyla birlikte)
$sql_sefer = "SELECT Buses.*, Companies.name AS company_name 
              FROM Buses
              JOIN Companies ON Buses.company_id = Companies.id
              WHERE Buses.id = ?";
$stmt_sefer = $pdo->prepare($sql_sefer);
$stmt_sefer->execute([$bus_id]);
$sefer = $stmt_sefer->fetch(PDO::FETCH_ASSOC);

if (!$sefer) {
    die("Böyle bir sefer bulunamadı.");
}

// 3. Bu sefere ait dolu koltukları çek (Sadece 'ACTIVE' biletler)
$sql_dolu_koltuklar = "SELECT seat_number FROM Tickets WHERE bus_id = ? AND status = 'ACTIVE'";
$stmt_dolu = $pdo->prepare($sql_dolu_koltuklar);
$stmt_dolu->execute([$bus_id]);

// fetchAll(PDO::FETCH_COLUMN) sadece 'seat_number' sütunundaki değerleri bir diziye atar
// Sonuç: $dolu_koltuklar = [3, 5, 10] gibi olur
$dolu_koltuklar = $stmt_dolu->fetchAll(PDO::FETCH_COLUMN, 0);

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Sefer Detayları</title> 
    <link rel="stylesheet" href="style.css"> 
</head>
<body>

    <header>
        <a href="index.php"><img src="images/logo.png" alt="BiletGO Logosu" class="logo"></a>
        <h1>Sefer Detayları</h1>
        <nav>
            <?php if ($is_logged_in): ?>
                <span>Hoşgeldiniz, <?php echo htmlspecialchars($user_fullname); ?>!</span>
                <a href="logout.php">Çıkış Yap</a>
            <?php else: ?>
                <a href="login.php">Giriş Yap</a>
                <a href="register.php">Kayıt Ol</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <h2><?php echo htmlspecialchars($sefer['company_name']); ?></h2>
        <p>
            <strong><?php echo htmlspecialchars($sefer['departure_location']); ?> -> <?php echo htmlspecialchars($sefer['arrival_location']); ?></strong>
        </p>
        <p>
            Kalkış: <?php echo date('d M Y, H:i', strtotime($sefer['departure_time'])); ?> | 
            Varış: <?php echo date('d M Y, H:i', strtotime($sefer['arrival_time'])); ?>
        </p>
        <p><strong>Fiyat: <?php echo htmlspecialchars($sefer['price']); ?> TL</strong></p>
        <p>Toplam Koltuk: <?php echo htmlspecialchars($sefer['total_seats']); ?></p>

        <hr>

        <h3>Koltuk Seçimi</h3>

        <form action="buy_ticket.php" method="POST">
            
            <input type="hidden" name="bus_id" value="<?php echo htmlspecialchars($sefer['id']); ?>">
            <input type="hidden" name="price" value="<?php echo htmlspecialchars($sefer['price']); ?>">

            <div class="bus-display">
                <div class="bus-silhouette">
                    <div class="driver-cabin">
                        <div class="steering-wheel"></div>
                    </div>
                    <div class="koltuk-layout">
                        <?php
                        $total_seats = (int)$sefer['total_seats'];
                        $total_rows = ceil($total_seats / 4); // 40 koltuk / 4 = 10 sıra

                        for ($row = 1; $row <= $total_rows; $row++):
                            // Sol Taraf (2 Koltuk)
                            $seat_1 = ($row - 1) * 4 + 1;
                            $seat_2 = ($row - 1) * 4 + 2;
                            // Sağ Taraf (2 Koltuk)
                            $seat_3 = ($row - 1) * 4 + 3;
                            $seat_4 = ($row - 1) * 4 + 4;
                        ?>
                            <?php if ($seat_1 <= $total_seats): ?>
                                <?php $is_dolu = in_array($seat_1, $dolu_koltuklar); ?>
                                <div>
                                    <input type="radio" name="seat_number" value="<?php echo $seat_1; ?>" id="seat-<?php echo $seat_1; ?>"
                                           <?php if ($is_dolu) echo 'disabled'; ?>
                                           <?php if ($selected_seat == $seat_1) echo 'checked'; ?> required>
                                    <label class="seat" for="seat-<?php echo $seat_1; ?>"><?php echo $seat_1; ?></label>
                                </div>
                            <?php else: ?>
                                <div class="seat-placeholder"></div> <?php endif; ?>

                            <?php if ($seat_2 <= $total_seats): ?>
                                <?php $is_dolu = in_array($seat_2, $dolu_koltuklar); ?>
                                <div>
                                    <input type="radio" name="seat_number" value="<?php echo $seat_2; ?>" id="seat-<?php echo $seat_2; ?>"
                                           <?php if ($is_dolu) echo 'disabled'; ?>
                                           <?php if ($selected_seat == $seat_2) echo 'checked'; ?> required>
                                    <label class="seat" for="seat-<?php echo $seat_2; ?>"><?php echo $seat_2; ?></label>
                                </div>
                            <?php else: ?>
                                <div class="seat-placeholder"></div> <?php endif; ?>

                            <div class="aisle"></div>

                            <?php if ($seat_3 <= $total_seats): ?>
                                <?php $is_dolu = in_array($seat_3, $dolu_koltuklar); ?>
                                <div>
                                    <input type="radio" name="seat_number" value="<?php echo $seat_3; ?>" id="seat-<?php echo $seat_3; ?>"
                                           <?php if ($is_dolu) echo 'disabled'; ?>
                                           <?php if ($selected_seat == $seat_3) echo 'checked'; ?> required>
                                    <label class="seat" for="seat-<?php echo $seat_3; ?>"><?php echo $seat_3; ?></label>
                                </div>
                            <?php else: ?>
                                <div class="seat-placeholder"></div> <?php endif; ?>

                            <?php if ($seat_4 <= $total_seats): ?>
                                <?php $is_dolu = in_array($seat_4, $dolu_koltuklar); ?>
                                <div>
                                    <input type="radio" name="seat_number" value="<?php echo $seat_4; ?>" id="seat-<?php echo $seat_4; ?>"
                                           <?php if ($is_dolu) echo 'disabled'; ?>
                                           <?php if ($selected_seat == $seat_4) echo 'checked'; ?> required>
                                    <label class="seat" for="seat-<?php echo $seat_4; ?>"><?php echo $seat_4; ?></label>
                                </div>
                            <?php else: ?>
                                <div class="seat-placeholder"></div> <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <br>
            
            <div>
                <label for="coupon_code">İndirim Kuponu:</label>
                <input type="text" id="coupon_code" name="coupon_code">
            </div>

            <br>

            <?php if ($user_role == 'User'): ?>
                <button type="submit">Seçili Koltuğu Satın Al</button>
            <?php elseif ($user_role == 'Ziyaretçi'): ?>
                <a href="login.php" class="login-prompt-button">Bilet satın almak için lütfen giriş yapın</a>
            <?php else: ?>
                <p>Sadece 'Yolcu' (User) hesapları bilet satın alabilir.</p>
            <?php endif; ?>

        </form>

    </main>

</body>
</html>