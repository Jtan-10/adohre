<?php
// Production settings: disable error display
ini_set('display_errors', 0);
error_reporting(0);

require_once '../db/db_connect.php';
require '../../vendor/autoload.php'; // Include the QR Code library

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

// ----------------------------------------------------------------
// 1) Fetch user details from DB
// ----------------------------------------------------------------
// Fetch user details using validated input
$user_id = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
if (!$user_id) {
    http_response_code(400);
    // Log error here (invalid user_id)
    die("Bad Request");
}

$query = "SELECT first_name, last_name, email, role, profile_image, virtual_id
          FROM users
          WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    die("User not found");
}
$user = $result->fetch_assoc();
$userFullName = trim($user['first_name'] . ' ' . $user['last_name']);

// ----------------------------------------------------------------
// 2) Generate the QR code (to replace the old barcode)
// ----------------------------------------------------------------
try {
    $qrResult = Builder::create()
        ->writer(new PngWriter())
        ->data($user['virtual_id'])
        ->encoding(new Encoding('UTF-8'))
        ->errorCorrectionLevel(ErrorCorrectionLevel::High)
        ->size(200)
        ->margin(0)
        ->build();
    $qrCodeData = $qrResult->getString();
} catch (Exception $e) {
    http_response_code(500);
    // Log error: $e->getMessage()
    die("Internal Server Error");
}

// ----------------------------------------------------------------
// 3) Load your template image (1495 x 841)
//    Ensure your file is placed at the specified path.
// ----------------------------------------------------------------
$templatePath = __DIR__ . '/../../assets/id_template.png';
if (!file_exists($templatePath)) {
    http_response_code(500);
    // Log error: missing template file
    die("Internal Server Error");
}

$idCard = imagecreatefrompng($templatePath);
if (!$idCard) {
    http_response_code(500);
    // Log error: failed to load template image
    die("Internal Server Error");
}
$cardWidth  = imagesx($idCard);  // Expected: 1495
$cardHeight = imagesy($idCard);  // Expected: 841

// ----------------------------------------------------------------
// 4) Load & crop the user's profile photo into a circle
//    Adjust the coordinates to match the circular cutout on your template.
// ----------------------------------------------------------------
if (!empty($user['profile_image']) && strpos($user['profile_image'], '/s3proxy/') === 0) {
    // Load the image via remote URL if using S3 proxy path
    $remoteUrl = "http://".$_SERVER['HTTP_HOST'].$user['profile_image'];
    $profileImageData = @file_get_contents($remoteUrl);
    if ($profileImageData !== false) {
        $profileImage = imagecreatefromstring($profileImageData);
    } else {
        // Fallback to default image if remote load fails
        $profileImage = imagecreatefromjpeg(__DIR__ . '/../../assets/default-profile.jpeg');
    }
} else {
    $localPath = __DIR__ . '/../../' . ($user['profile_image'] ?? 'assets/default-profile.jpeg');
    if (!file_exists($localPath)) {
        $localPath = __DIR__ . '/../../assets/default-profile.jpeg';
    }
    $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $profileImage = @imagecreatefromjpeg($localPath);
            break;
        case 'png':
            $profileImage = @imagecreatefrompng($localPath);
            break;
        case 'gif':
            $profileImage = @imagecreatefromgif($localPath);
            break;
        default:
            $profileImage = imagecreatefromjpeg(__DIR__ . '/../../assets/default-profile.jpeg');
    }
}

if ($profileImage) {
    // For a 1495x841 template, these values are examples.
    // Adjust the circle size and position as needed.
    $circleDiameter = 380;   // New circle diameter
    $circleX = 1132;         // X-coordinate for the circular photo
    $circleY = 205;          // Y-coordinate for the circular photo

    // Create a canvas to hold the resized photo with transparency
    $finalPhoto = imagecreatetruecolor($circleDiameter, $circleDiameter);
    imagealphablending($finalPhoto, false);
    imagesavealpha($finalPhoto, true);
    $transparent = imagecolorallocatealpha($finalPhoto, 0, 0, 0, 127);
    imagefilledrectangle($finalPhoto, 0, 0, $circleDiameter, $circleDiameter, $transparent);

    // Resize the user's photo into the final canvas
    imagecopyresampled(
        $finalPhoto,
        $profileImage,
        0, 0,
        0, 0,
        $circleDiameter, $circleDiameter,
        imagesx($profileImage),
        imagesy($profileImage)
    );
    imagedestroy($profileImage);

    // Create a circular mask
    $mask = imagecreatetruecolor($circleDiameter, $circleDiameter);
    imagealphablending($mask, false);
    imagesavealpha($mask, true);
    $maskTransparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
    imagefilledrectangle($mask, 0, 0, $circleDiameter, $circleDiameter, $maskTransparent);
    $maskOpaque = imagecolorallocate($mask, 0, 0, 0);
    imagefilledellipse($mask, $circleDiameter/2, $circleDiameter/2, $circleDiameter, $circleDiameter, $maskOpaque);
    
    // Apply the mask pixel by pixel
    for ($x = 0; $x < $circleDiameter; $x++) {
        for ($y = 0; $y < $circleDiameter; $y++) {
            $alpha = (imagecolorat($mask, $x, $y) >> 24) & 0xFF;
            if ($alpha > 0) {
                imagesetpixel($finalPhoto, $x, $y, imagecolorallocatealpha($finalPhoto, 0, 0, 0, 127));
            }
        }
    }
    imagedestroy($mask);

    // Place the circular photo onto the template
    imagecopy($idCard, $finalPhoto, $circleX, $circleY, 0, 0, $circleDiameter, $circleDiameter);
    imagedestroy($finalPhoto);
}

// ----------------------------------------------------------------
// 5) Place the QR code (to replace the old barcode)
//    Adjust these coordinates to match your template's design.
// ----------------------------------------------------------------
$qrImage = imagecreatefromstring($qrCodeData);
if ($qrImage) {
    $qrFinalWidth  = 320;
    $qrFinalHeight = 320;
    // For a larger template, the QR code might be placed lower.
    $qrDestX = 345;
    $qrDestY = 520;  // Example position; adjust as necessary

    imagecopyresampled(
        $idCard,
        $qrImage,
        $qrDestX, $qrDestY,
        0, 0,
        $qrFinalWidth, $qrFinalHeight,
        imagesx($qrImage), imagesy($qrImage)
    );
    imagedestroy($qrImage);
}

// ----------------------------------------------------------------
// 6) Overlay the dynamic user data without replacing the template’s labels.
//     The left side contains the labels for Name, ID Card Number, and Email Address.
//     The Role is placed below the profile image.
// ----------------------------------------------------------------

// Use a bold font file (for example, arialbd.ttf)
// Ensure you have this file in your /fonts/ folder
$fontPath = __DIR__ . '/fonts/arialbd.ttf';
if (!file_exists($fontPath)) {
    http_response_code(500);
    // Log error: missing font file
    die("Internal Server Error");
}

$textColor = imagecolorallocate($idCard, 0, 0, 0);
$roleColor = imagecolorallocate($idCard, 255, 255, 255);
$fontSize  = 30;

// Coordinates for overlaying values next to the labels on the left
$nameX  = 530;
$nameY  = 325;
$idNumX = 530;
$idNumY = 400;
$emailX = 530;
$emailY = 475;

// 1) Name (from DB)
imagettftext($idCard, $fontSize, 0, $nameX, $nameY, $textColor, $fontPath, $userFullName);

// 2) ID Card Number (from DB)
imagettftext($idCard, $fontSize, 0, $idNumX, $idNumY, $textColor, $fontPath, $user['virtual_id']);

// 3) Email Address (from DB)
imagettftext($idCard, $fontSize, 0, $emailX, $emailY, $textColor, $fontPath, $user['email']);

// 4) Place the Role below the profile image.
$roleX = 1250;                 // Adjust to center the role text under the image if needed
$roleY = $circleY + $circleDiameter + 70; // 30px below the profile photo
imagettftext($idCard, $fontSize, 0, $roleX, $roleY, $roleColor, $fontPath, ucfirst(strtolower($user['role'])));

// ----------------------------------------------------------------
// 7) Log the virtual ID generation event
recordAuditLog($user_id, "Generate Virtual ID", "Virtual ID card generated for user: " . $userFullName);

// ----------------------------------------------------------------
// 8) Output the final image as PNG
header("Content-Type: image/png");
imagepng($idCard);
imagedestroy($idCard);
?>