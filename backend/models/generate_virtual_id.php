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

/**
 * Scale down an image if either dimension exceeds $maxDim.
 * Returns the new image resource.
 */
function scaleDownIfTooLarge($sourceImg, $maxDim = 2000)
{
    $originalWidth  = imagesx($sourceImg);
    $originalHeight = imagesy($sourceImg);

    // If both dimensions are within the limit, return the original
    if ($originalWidth <= $maxDim && $originalHeight <= $maxDim) {
        return $sourceImg;
    }

    // Calculate new dimensions preserving aspect ratio
    $ratio = min($maxDim / $originalWidth, $maxDim / $originalHeight);
    $newWidth  = (int)($originalWidth  * $ratio);
    $newHeight = (int)($originalHeight * $ratio);

    $scaledImg = imagecreatetruecolor($newWidth, $newHeight);

    // Preserve transparency for PNG images
    imagealphablending($scaledImg, false);
    imagesavealpha($scaledImg, true);

    imagecopyresampled(
        $scaledImg,
        $sourceImg,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $originalWidth,
        $originalHeight
    );

    return $scaledImg;
}

// ----------------------------------------------------------------
// 1) Fetch user details from DB
// ----------------------------------------------------------------
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
// 2) Generate the QR code
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
    // Debug: QR code generated successfully
} catch (Exception $e) {
    http_response_code(500);
    // Log error: $e->getMessage()
    die("Internal Server Error");
}

// ----------------------------------------------------------------
// 3) Load your template image
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
$cardWidth  = imagesx($idCard);  // e.g. 1495
$cardHeight = imagesy($idCard);  // e.g. 841

// ----------------------------------------------------------------
// 4) Load & crop the user's profile photo into a circle (no distortion)
// ----------------------------------------------------------------
$profileImage = null;

function loadLocalOrFallback($path)
{
    if (!file_exists($path)) {
        // fallback
        return imagecreatefromjpeg(__DIR__ . '/../../assets/default-profile.jpeg');
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            return @imagecreatefromjpeg($path);
        case 'png':
            return @imagecreatefrompng($path);
        case 'gif':
            return @imagecreatefromgif($path);
        default:
            return imagecreatefromjpeg(__DIR__ . '/../../assets/default-profile.jpeg');
    }
}

if (!empty($user['profile_image']) && strpos($user['profile_image'], '/s3proxy/') === 0) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $encryptedImageUrl = $protocol . $_SERVER['HTTP_HOST'] . '/capstone-php/backend/routes/decrypt_image.php?image_url=' . urlencode($user['profile_image']);
    $profileImageData = @file_get_contents($encryptedImageUrl);
    if ($profileImageData !== false) {
        $profileImage = imagecreatefromstring($profileImageData);
    } else {
        $profileImage = imagecreatefromjpeg(__DIR__ . '/../../assets/default-profile.jpeg');
    }
} else {
    $localPath = __DIR__ . '/../../' . ($user['profile_image'] ?? 'assets/default-profile.jpeg');
    $profileImage = loadLocalOrFallback($localPath);
}

// NEW: Scale down the profile image if it's too large
if ($profileImage) {
    $profileImage = scaleDownIfTooLarge($profileImage, 2000);
}

if ($profileImage) {
    // Step A: Crop the image to a square from center (to avoid distortion)
    $originalWidth = imagesx($profileImage);
    $originalHeight = imagesy($profileImage);
    $squareSize = min($originalWidth, $originalHeight);

    // Coordinates to center-crop
    $srcX = ($originalWidth - $squareSize) / 2;
    $srcY = ($originalHeight - $squareSize) / 2;

    // Crop to square
    $croppedSquare = imagecreatetruecolor($squareSize, $squareSize);
    imagecopy($croppedSquare, $profileImage, 0, 0, $srcX, $srcY, $squareSize, $squareSize);
    imagedestroy($profileImage);
    $profileImage = $croppedSquare;

    // Step B: Resize to the circle dimension
    $circleDiameter = 380;   // diameter of the circle
    $circleX = 1132;         // position on the template
    $circleY = 205;

    $finalPhoto = imagecreatetruecolor($circleDiameter, $circleDiameter);
    imagealphablending($finalPhoto, false);
    imagesavealpha($finalPhoto, true);
    $transparent = imagecolorallocatealpha($finalPhoto, 0, 0, 0, 127);
    imagefilledrectangle($finalPhoto, 0, 0, $circleDiameter, $circleDiameter, $transparent);

    // Resize the square-cropped image into the finalPhoto
    imagecopyresampled(
        $finalPhoto,
        $profileImage,
        0,
        0,
        0,
        0,
        $circleDiameter,
        $circleDiameter,
        $squareSize,
        $squareSize
    );
    imagedestroy($profileImage);

    // Step C: Create a circular mask
    $mask = imagecreatetruecolor($circleDiameter, $circleDiameter);
    imagealphablending($mask, false);
    imagesavealpha($mask, true);
    $maskTransparent = imagecolorallocatealpha($mask, 0, 0, 0, 127);
    imagefilledrectangle($mask, 0, 0, $circleDiameter, $circleDiameter, $maskTransparent);
    $maskOpaque = imagecolorallocate($mask, 0, 0, 0);
    imagefilledellipse($mask, $circleDiameter / 2, $circleDiameter / 2, $circleDiameter, $circleDiameter, $maskOpaque);

    // Step D: Apply the mask pixel by pixel
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
    // Debug: Circular profile image processed and placed on template
}

// ----------------------------------------------------------------
// 5) Place the QR code
// ----------------------------------------------------------------
$qrImage = imagecreatefromstring($qrCodeData);
if ($qrImage) {
    $qrFinalWidth  = 320;
    $qrFinalHeight = 320;
    $qrDestX = 345;
    $qrDestY = 520;

    imagecopyresampled(
        $idCard,
        $qrImage,
        $qrDestX,
        $qrDestY,
        0,
        0,
        $qrFinalWidth,
        $qrFinalHeight,
        imagesx($qrImage),
        imagesy($qrImage)
    );
    imagedestroy($qrImage);
    // Debug: QR code placed on template
}

// ----------------------------------------------------------------
// 6) Overlay the dynamic user data (with fallback for long text).
//    We'll define a small helper function to reduce font size if text is too long.
// ----------------------------------------------------------------
$fontPath = __DIR__ . '/fonts/arialbd.ttf';
if (!file_exists($fontPath)) {
    http_response_code(500);
    // Log error: missing font file
    die("Internal Server Error");
}

$textColor = imagecolorallocate($idCard, 0, 0, 0);
$roleColor = imagecolorallocate($idCard, 255, 255, 255);

// Because text can be too long, let's define a function to fit text in a max width:
function imagettftextfit(&$image, $maxFontSize, $angle, $x, $y, $color, $font, $text, $maxWidth)
{
    $fontSize = $maxFontSize;
    do {
        $box = imagettfbbox($fontSize, $angle, $font, $text);
        $textWidth = $box[2] - $box[0];
        if ($textWidth <= $maxWidth) {
            // Found a size that fits
            imagettftext($image, $fontSize, $angle, $x, $y, $color, $font, $text);
            return;
        }
        $fontSize--;
    } while ($fontSize > 8); // Minimum font size
    // If it doesn't fit even at size=8, we could just draw anyway or substring the text
    imagettftext($image, 8, $angle, $x, $y, $color, $font, mb_substr($text, 0, 40) . '...');
}

// Example coordinates for text:
$nameX  = 530;
$nameY  = 325;
$idNumX = 530;
$idNumY = 400;
$emailX = 530;
$emailY = 475;

// We'll set a max width for each line so text doesn't run off the card
$maxTextWidth = 563; // adjust as needed
$maxFontSize  = 30;  // starting font size

// 1) Name
imagettftextfit($idCard, $maxFontSize, 0, $nameX, $nameY, $textColor, $fontPath, $userFullName, $maxTextWidth);

// 2) ID Card Number
imagettftextfit($idCard, $maxFontSize, 0, $idNumX, $idNumY, $textColor, $fontPath, $user['virtual_id'], $maxTextWidth);

// 3) Email Address
imagettftextfit($idCard, $maxFontSize, 0, $emailX, $emailY, $textColor, $fontPath, $user['email'], $maxTextWidth);

// 4) Role below the profile image
$circleDiameter = 380; // same as above
$circleY = 205;        // same as above
$roleX = 1250;
$roleY = $circleY + $circleDiameter + 70;
imagettftextfit($idCard, $maxFontSize, 0, $roleX, $roleY, $roleColor, $fontPath, ucfirst(strtolower($user['role'])), 250);

// Debug: Dynamic text overlaid on the ID card

// ----------------------------------------------------------------
// 7) Log the virtual ID generation event
// ----------------------------------------------------------------
function recordAuditLog($uid, $action, $details)
{
    // Implementation depends on your existing code
    // e.g.:
    // $sql = "INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)";
    // ...
}
recordAuditLog($user_id, "Generate Virtual ID", "Virtual ID card generated for user: " . $userFullName);
// Debug: Audit log recorded

// ----------------------------------------------------------------
// 8) Output the final image as PNG --> Now create a PDF with password protection
// ----------------------------------------------------------------

// Save the generated ID card image (stored in $idCard) as a temporary PNG.
$tempFile = tempnam(sys_get_temp_dir(), 'idcard') . '.png';
imagepng($idCard, $tempFile);
imagedestroy($idCard);

// Require TCPDF (assuming it is installed via Composer in vendor folder)
require_once('../../vendor/tecnickcom/tcpdf/tcpdf.php');

// Create new PDF document.
$pdf = new TCPDF();
$pdf->SetCreator('Member Link');
$pdf->SetTitle('Virtual ID');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// NEW: Remove margins and set page size to match id card dimensions
$pdf->SetMargins(0, 0, 0);
$pdfWidth = ($cardWidth * 25.4) / 300;    // converting pixels to mm assuming 300 DPI
$pdfHeight = ($cardHeight * 25.4) / 300;
$pdf->AddPage('', array($pdfWidth, $pdfHeight));

// Add the temporary image covering the entire page.
$pdf->Image($tempFile, 0, 0, $pdfWidth, $pdfHeight, '', '', '', false, 300);

// Apply PDF password if provided via GET parameter.
$pdf_password = isset($_GET['pdf_password']) ? $_GET['pdf_password'] : '';
if ($pdf_password) {
    // Set user password (to open pdf); no owner password specified.
    $pdf->SetProtection(array('print', 'copy'), $pdf_password, null, 0, null);
}

// Remove the temporary image file.
unlink($tempFile);

// Force PDF download.
$pdf->Output('virtual_id.pdf', 'D');
