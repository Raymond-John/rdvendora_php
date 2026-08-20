<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php';   // permission helper

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Admin authentication
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    if (isset($_SESSION['email']) && $_SESSION['email'] === 'admin@example.com') {
        $_SESSION['is_admin'] = true;
        $isAdmin = true;
    } else {
        die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../">Go Home</a></div>');
    }
}

// ---------- PERMISSION CHECK FOR CUSTOMERS PAGE ----------
if (!adminHasPermission('customers', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view customers.</p><a href="admin">Go to Dashboard</a></div>');
}

// Fetch all unique customers from orders table
// We group by email or user_id (if exists) to avoid duplicates
$sql = "SELECT 
            user_email,
            MAX(user_name) as user_name,
            MAX(user_phone) as user_phone,
            MAX(user_address) as user_address,
            COUNT(*) as total_orders,
            SUM(total_amount) as total_spent,
            MAX(created_at) as last_order_date,
            GROUP_CONCAT(DISTINCT order_ref ORDER BY id DESC SEPARATOR ', ') as order_refs
        FROM orders 
        WHERE user_email IS NOT NULL AND user_email != ''
        GROUP BY user_email
        ORDER BY last_order_date DESC";

$customers = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$totalCustomers = count($customers);

$adminPageTitle = 'Customers - Admin Panel';
$adminPageHeading = 'Customers';
$adminPageSubtitle = 'Shoppers across the marketplace';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="customers-container">
        <div class="customers-table-wrapper">
            <table id="customersTable">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Orders</th>
                        <th>Total Spent</th>
                        <th>Last Order</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr><td colspan="8" style="text-align:center;">No customers found.</span></td></tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): ?>
                        <tr class="customer-row">
                            <td data-label="Name"><?= htmlspecialchars($customer['user_name'] ?: 'N/A') ?></td>
                            <td data-label="Email"><?= htmlspecialchars($customer['user_email']) ?></td>
                            <td data-label="Phone"><?= htmlspecialchars($customer['user_phone'] ?: 'N/A') ?></td>
                            <td data-label="Address"><?= htmlspecialchars($customer['user_address'] ?: 'N/A') ?></td>
                            <td data-label="Orders"><?= $customer['total_orders'] ?> order(s)</td>
                            <td data-label="Total">₦<?= number_format($customer['total_spent'], 2) ?></td>
                            <td data-label="Last Order"><?= date('M j, Y', strtotime($customer['last_order_date'])) ?></td>
                            <td data-label="Actions"><button class="btn-view" onclick="viewCustomerOrders('<?= htmlspecialchars($customer['user_email']) ?>')">View Orders</button></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<script>
    // Theme toggle
// Search filter
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#customersTable tbody tr');
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }

    // View customer orders (simple alert – you can replace with modal or redirect)
    function viewCustomerOrders(email) {
        window.location.href = `admin-receive-order.php?email=${encodeURIComponent(email)}`;
    }

    
</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
