<?php
session_start();
header('Content-Type: application/json');

ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/storage/logs/transport_errors.log');
error_reporting(E_ALL);

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not logged in');
    }

    require_once 'includes/connection.php';
    require_once __DIR__ . '/app/helpers/transport_companies.php';
    if (!isset($conn) && isset($connect)) $conn = $connect;
    if (!$conn) throw new Exception('Database connection failed');

    // Ensure session store_id
    if (!isset($_SESSION['store_id'])) {
        $stmt = $conn->prepare("SELECT id FROM stores WHERE user_id = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $_SESSION['store_id'] = $row['id'];
        } else {
            throw new Exception('No store found for this user');
        }
        $stmt->close();
    }
    $vendorStoreId = $_SESSION['store_id'];

    // Create notifications table if needed
    $conn->query("CREATE TABLE IF NOT EXISTS `transport_notifications` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `manifest_filename` VARCHAR(255) NOT NULL,
        `is_read` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `manifest_filename` (`manifest_filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Invalid JSON input');

    $orderIds = $input['order_ids'] ?? [];
    $company = $input['company'] ?? '';
    $notes = $input['notes'] ?? '';

    if (empty($orderIds)) throw new Exception('No orders selected');
    if (empty($company)) throw new Exception('Transport company not specified');
    if (!rdv_transport_company_is_valid($conn, $company)) {
        throw new Exception('Invalid transport company selected');
    }

    // Get column list
    $cols = [];
    $colResult = $conn->query("SHOW COLUMNS FROM orders");
    while ($c = $colResult->fetch_assoc()) {
        $cols[] = $c['Field'];
    }

    // Determine vendor column
    if (in_array('store_id', $cols)) $vendorCol = 'store_id';
    elseif (in_array('seller_id', $cols)) $vendorCol = 'seller_id';
    elseif (in_array('user_id', $cols)) $vendorCol = 'user_id';
    else throw new Exception('No store/seller column in orders');

    // Build address fields dynamically
    $addressFields = [];
    if (in_array('user_address', $cols)) $addressFields[] = 'user_address';
    if (in_array('shipping_address', $cols)) $addressFields[] = 'shipping_address';
    if (in_array('address', $cols)) $addressFields[] = 'address';
    if (in_array('city', $cols)) $addressFields[] = 'city';
    if (in_array('state', $cols)) $addressFields[] = 'state';
    if (in_array('zip', $cols)) $addressFields[] = 'zip';
    if (in_array('shipping_zip', $cols)) $addressFields[] = 'shipping_zip';

    $selectFields = ["id"];
    if (in_array('order_number', $cols)) $selectFields[] = "order_number";
    else $selectFields[] = "CONCAT('ORD-', id) as order_number";
    if (in_array('customer_name', $cols)) $selectFields[] = "customer_name";
    if (in_array('user_name', $cols)) $selectFields[] = "user_name as customer_name";
    if (in_array('customer_email', $cols)) $selectFields[] = "customer_email";
    if (in_array('user_email', $cols)) $selectFields[] = "user_email as customer_email";
    if (in_array('customer_phone', $cols)) $selectFields[] = "customer_phone as phone";
    if (in_array('user_phone', $cols)) $selectFields[] = "user_phone as phone";
    if (in_array('phone', $cols)) $selectFields[] = "phone";
    if (in_array('notes', $cols)) $selectFields[] = "notes";
    if (in_array('total_amount', $cols)) $selectFields[] = "total_amount";
    if (in_array('total', $cols)) $selectFields[] = "total";
    // Add address fields
    $selectFields = array_merge($selectFields, $addressFields);

    $selectStr = implode(', ', array_unique($selectFields));
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $sql = "SELECT $selectStr FROM orders WHERE id IN ($placeholders) AND $vendorCol = ?";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($orderIds)) . 'i';
    $params = array_merge($orderIds, [$vendorStoreId]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (count($orders) !== count($orderIds)) {
        $foundIds = array_column($orders, 'id');
        $missing = array_diff($orderIds, $foundIds);
        throw new Exception('Some orders do not belong to you (IDs: ' . implode(', ', $missing) . ')');
    }

    // Build manifest content
    $manifest = "==========================================\n";
    $manifest .= "          DELIVERY MANIFEST\n";
    $manifest .= "==========================================\n\n";
    $manifest .= "Store: " . ($_SESSION['store_name'] ?? 'RD Vendora Store') . "\n";
    $manifest .= "Transport Company: $company\n";
    $manifest .= "Special Instructions: $notes\n";
    $manifest .= "Generated: " . date('Y-m-d H:i:s') . "\n";
    $manifest .= str_repeat("-", 40) . "\n\n";

    foreach ($orders as $index => $order) {
        $manifest .= "ORDER #" . ($index + 1) . "\n";
        $manifest .= "  Order ID: {$order['order_number']}\n";
        $manifest .= "  Customer: " . ($order['customer_name'] ?? 'N/A') . "\n";
        $manifest .= "  Email: " . ($order['customer_email'] ?? 'N/A') . "\n";

        // Build address string – prioritize user_address (from checkout)
        $addressParts = [];
        if (!empty($order['user_address'])) $addressParts[] = $order['user_address'];
        elseif (!empty($order['shipping_address'])) $addressParts[] = $order['shipping_address'];
        elseif (!empty($order['address'])) $addressParts[] = $order['address'];
        if (!empty($order['city'])) $addressParts[] = $order['city'];
        if (!empty($order['state'])) $addressParts[] = $order['state'];
        if (!empty($order['zip'])) $addressParts[] = $order['zip'];
        elseif (!empty($order['shipping_zip'])) $addressParts[] = $order['shipping_zip'];
        $address = implode(', ', $addressParts) ?: 'Not provided';

        $manifest .= "  Address: $address\n";
        $manifest .= "  Phone: " . ($order['phone'] ?? 'N/A') . "\n";
        if (!empty($order['notes'])) $manifest .= "  Order Notes: {$order['notes']}\n";
        $total = $order['total_amount'] ?? $order['total'] ?? 0;
        $manifest .= "  Total: ₦" . number_format($total, 2) . "\n";
        $manifest .= str_repeat("-", 40) . "\n";
    }

    // Create manifest directory and file
    $manifestDir = 'transport_manifests/';
    if (!is_dir($manifestDir)) mkdir($manifestDir, 0777, true);
    $filename = $manifestDir . "manifest_" . date('Ymd_His') . ".txt";
    if (file_put_contents($filename, $manifest) === false) {
        throw new Exception('Could not write manifest file');
    }

    // Insert notification for admin
    $basename = basename($filename);
    $stmt = $conn->prepare("INSERT INTO transport_notifications (manifest_filename, is_read) VALUES (?, 0) ON DUPLICATE KEY UPDATE is_read = 0");
    $stmt->bind_param("s", $basename);
    $stmt->execute();
    $stmt->close();

    $response = ['success' => true, 'message' => 'Manifest created: ' . $basename];

} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
    error_log("Transport error: " . $e->getMessage());
}

if (isset($conn) && $conn) $conn->close();
echo json_encode($response);
?>