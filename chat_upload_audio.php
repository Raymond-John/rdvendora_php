<?php
session_start();
require_once 'includes/connection.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Determine sender type
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$isVendor = isset($_SESSION['user_id']) && !$isAdmin;

if (!$isAdmin && !$isVendor) {
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

$sender_type = $isAdmin ? 'admin' : 'vendor';
$vendor_id = null;

if ($isAdmin && isset($_POST['vendor_id'])) {
    $vendor_id = intval($_POST['vendor_id']);
} elseif ($isVendor) {
    $vendor_id = $_SESSION['user_id'];
} else {
    die(json_encode(['success' => false, 'error' => 'Missing vendor ID']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['audio'])) {
    $uploadDir = 'uploads/chat_audio/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    $fileName = time() . '_' . bin2hex(random_bytes(8)) . '.webm';
    $targetPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($_FILES['audio']['tmp_name'], $targetPath)) {
        $stmt = $conn->prepare("INSERT INTO chat_messages (vendor_id, sender_type, message, audio_url) VALUES (?, ?, '', ?)");
        $stmt->bind_param("iss", $vendor_id, $sender_type, $targetPath);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'audio_url' => $targetPath]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Upload failed']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No audio file or invalid request']);
}
?>