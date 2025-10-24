<?php
// Oturumu başlat
session_start();

// 1. Composer'ın 'autoload' dosyasını dahil et
// (Dosya src'de, vendor ise bir üst dizinde olduğundan ../vendor)
require_once __DIR__ . '/vendor/autoload.php';

// 2. Kütüphaneleri 'use' ile çağır
use Dompdf\Dompdf;
use Dompdf\Options;

// Veritabanı bağlantısı
require_once 'setup.php';


// --- 3. Güvenlik ve Veri Çekme ---

// A) Giriş yapmış mı ve 'User' rolünde mi?
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    die("PDF görüntüleme yetkiniz yok.");
}

$user_id = $_SESSION['user_id'];
$ticket_id = $_GET['id'] ?? null;

if (empty($ticket_id)) {
    die("Bilet ID'si bulunamadı.");
}

// B) Bütün bilet bilgilerini (Kullanıcı, Sefer, Firma) çek
// Biletin bu kullanıcıya ait olduğundan emin ol!
$sql = "SELECT 
            T.seat_number, T.status,
            U.fullname AS user_fullname, U.email AS user_email,
            B.departure_location, B.arrival_location, B.departure_time, B.arrival_time, B.price,
            C.name AS company_name
        FROM Tickets T
        JOIN Users U ON T.user_id = U.id
        JOIN Buses B ON T.bus_id = B.id
        JOIN Companies C ON B.company_id = C.id
        WHERE T.id = ? AND T.user_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$ticket_id, $user_id]);
$bilet = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bilet) {
    die("Bilet bulunamadı veya bu bileti görüntüleme yetkiniz yok.");
}

// --- 4. PDF Oluşturma (HTML Tasarımı) ---

$logoHtml = ''; // Logo bulunamazsa boş kalsın
$logoPath = __DIR__ . '/images/logo.png'; // Logonun yolu (logo.png olduğunu varsayıyoruz)

if (file_exists($logoPath)) {
    $logoType = pathinfo($logoPath, PATHINFO_EXTENSION);
    $logoData = file_get_contents($logoPath);
    $logoBase64 = 'data:image/' . $logoType . ';base64,' . base64_encode($logoData);
    // Logoyu HTML img etiketine yerleştir [cite: 1]
    $logoHtml = '<img src="' . $logoBase64 . '" style="height: 100px; margin-bottom: 10px;">';
}



// PDF'e basılacak HTML içeriğini oluştur
$html = "
<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; } /* UTF-8 (Türkçe) desteği için */
        .ticket { border: 2px dashed #333; padding: 20px; max-width: 600px; margin: auto; }
        .header { text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { color: #15ff00ff; margin: 0; }
        .details { margin-top: 20px; }
        .details p { line-height: 1.6; }
        .details strong { min-width: 150px; display: inline-block; }
        .route { font-size: 1.2em; font-weight: bold; margin: 15px 0; }
        .footer { margin-top: 20px; text-align: center; font-size: 0.9em; color: #777; }
    </style>
</head>
<body>
    <div class='ticket'>
        <div class='header'>
            " . $logoHtml . "
            <p>" . htmlspecialchars($bilet['company_name']) . "</p>
        </div>
        
        <div class='details'>
            <p><strong>Yolcu Adı:</strong> " . htmlspecialchars($bilet['user_fullname']) . "</p>
            <p><strong>Yolcu E-posta:</strong> " . htmlspecialchars($bilet['user_email']) . "</p>
            <p><strong>Bilet Durumu:</strong> " . ($bilet['status'] == 'ACTIVE' ? 'AKTİF' : 'İPTAL EDİLMİŞ') . "</p>
        </div>

        <div class='route'>
            <p>" . htmlspecialchars($bilet['departure_location']) . "  &rarr;  " . htmlspecialchars($bilet['arrival_location']) . "</p>
        </div>

        <div class='details'>
            <p><strong>Kalkış Saati:</strong> " . date('d M Y, H:i', strtotime($bilet['departure_time'])) . "</p>
            <p><strong>Tahmini Varış:</strong> " . date('d M Y, H:i', strtotime($bilet['arrival_time'])) . "</p>
            <p><strong>Koltuk No:</strong> <span style='font-size: 1.5em; font-weight: bold;'>" . $bilet['seat_number'] . "</span></p>
            <p><strong>Ödenen Tutar:</strong> " . number_format($bilet['price'], 2) . " TL</p>
        </div>

        <div class='footer'>
            <p>Bilet ID: " . htmlspecialchars($ticket_id) . "</p>
            <p>İyi yolculuklar dileriz!</p>
        </div>
    </div>
</body>
</html>
";

// --- 5. Dompdf'i Yapılandırma ve Çalıştırma ---

// (Dompdf'in UTF-8 için 'DejaVu Sans' fontunu bulabilmesi için bu ayarlar önemlidir)
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans'); 

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8'); // HTML'i yükle

// (İsteğe bağlı) Kağıt boyutunu ve yönünü ayarla
$dompdf->setPaper('A4', 'portrait');

// HTML'i PDF'e dönüştür
$dompdf->render();

// PDF'i tarayıcıya gönder (indirmek yerine tarayıcıda açar)
// "bilet.pdf" = tarayıcı sekmesinde görünecek dosya adı
$dompdf->stream("bilet-" . $ticket_id . ".pdf", ["Attachment" => 0]);

?>