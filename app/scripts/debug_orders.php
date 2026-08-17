<?php
session_start();
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('DB connection failed.');

echo "<h2>🔍 Order Debugging</h2>";

// 1. Check if logged in
echo "<h3>Session:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// 2. Check orders table structure
echo "<h3>Orders table columns:</h3>";
$cols = $conn->query("SHOW COLUMNS FROM orders");
if ($cols) {
    while($col = $cols->fetch_assoc()) {
        echo $col['Field'] . " – " . $col['Type'] . "<br>";
    }
} else {
    echo "Orders table does not exist!";
}

// 3. Show all orders (raw)
echo "<h3>All orders (raw):</h3>";
$all = $conn->query("SELECT * FROM orders ORDER BY id DESC LIMIT 10");
if ($all && $all->num_rows > 0) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr>";
    while($field = $all->fetch_field()) echo "<th>{$field->name}</th>";
    echo "</tr>";
    while($row = $all->fetch_assoc()) {
        echo "<tr>";
        foreach($row as $val) echo "<td>" . htmlspecialchars($val) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No orders found in database.";
}

// 4. For vendor: check their store_id from session vs orders.store_id
if (isset($_SESSION['user_id']) && !isset($_SESSION['is_admin'])) {
    $vendor_id = $_SESSION['user_id'];
    $store = $conn->query("SELECT id FROM stores WHERE user_id = $vendor_id")->fetch_assoc();
    if ($store) {
        $store_pk = $store['id'];
        echo "<h3>Vendor store primary key: $store_pk</h3>";
        $vendor_orders = $conn->query("SELECT * FROM orders WHERE store_id = $store_pk");
        echo "<h3>Orders for store_id = $store_pk : " . $vendor_orders->num_rows . " found</h3>";
    } else {
        echo "<h3>No store found for this vendor!</h3>";
    }
}

echo "<hr><a href='orders.php'>Back to Orders Page</a>";
?>
