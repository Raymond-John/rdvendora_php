<?php
session_start();
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/admin_auth.php'; // RBAC permission helper

if (!isset($conn) && isset($connect)) $conn = $connect;
if (!$conn) die('Database connection failed.');

// Admin authentication – no hardcoded email fallback
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view this page.</p><a href="../index.php">Go Home</a></div>');
}

// Permission check for orders
if (!adminHasPermission('orders', $conn)) {
    die('<div style="text-align:center; padding:3rem;"><h1>Access Denied</h1><p>You do not have permission to view orders.</p><a href="admin.php">Go to Dashboard</a></div>');
}

// ---------- DETECT ORDER TABLE COLUMNS ----------
$columns_check = $conn->query("SHOW COLUMNS FROM orders");
$order_columns = [];
while ($col = $columns_check->fetch_assoc()) {
    $order_columns[] = $col['Field'];
}

// Detect name column
$name_col = null;
foreach (['user_name', 'shipping_name', 'billing_name', 'name', 'fullname'] as $cand) {
    if (in_array($cand, $order_columns)) {
        $name_col = $cand;
        break;
    }
}
if (!$name_col) $name_col = 'user_id';

// Detect email column
$email_col = null;
foreach (['user_email', 'email', 'shipping_email', 'billing_email'] as $cand) {
    if (in_array($cand, $order_columns)) {
        $email_col = $cand;
        break;
    }
}
if (!$email_col) $email_col = 'user_id';

// Detect phone column – includes 'user_phone'
$phone_col = null;
foreach (['user_phone', 'shipping_phone', 'billing_phone', 'phone', 'phone_number', 'contact_phone'] as $cand) {
    if (in_array($cand, $order_columns)) {
        $phone_col = $cand;
        break;
    }
}

// Detect total column
$total_col = 'total_amount';
if (!in_array('total_amount', $order_columns)) {
    if (in_array('order_total', $order_columns)) $total_col = 'order_total';
    elseif (in_array('amount', $order_columns)) $total_col = 'amount';
    elseif (in_array('grand_total', $order_columns)) $total_col = 'grand_total';
    else $total_col = 'total_amount';
}

// ---------- BUILD QUERY ----------
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : '';
$paymentFilter = isset($_GET['payment']) ? trim($_GET['payment']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Base SQL – only u.fullname from users; email and phone come from orders table
$sql = "SELECT o.*, s.store_name, u.fullname as user_fullname 
        FROM orders o
        LEFT JOIN stores s ON o.store_id = s.id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE 1=1";
$countSql = "SELECT COUNT(*) as total FROM orders o WHERE 1=1";
$params = [];
$types = "";

// Add filters
if (!empty($search)) {
    $sql .= " AND (o.order_number LIKE ? OR o.$name_col LIKE ? OR o.$email_col LIKE ?)";
    $countSql .= " AND (o.order_number LIKE ? OR o.$name_col LIKE ? OR o.$email_col LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types .= "sss";
}
if (!empty($statusFilter)) {
    $sql .= " AND o.status = ?";
    $countSql .= " AND o.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}
if (!empty($paymentFilter)) {
    $sql .= " AND o.payment_status = ?";
    $countSql .= " AND o.payment_status = ?";
    $params[] = $paymentFilter;
    $types .= "s";
}

// Count total
$stmt = $conn->prepare($countSql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$totalOrders = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Fetch paginated orders
$sql .= " ORDER BY o.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Check if order_items table exists
$has_order_items = $conn->query("SHOW TABLES LIKE 'order_items'")->num_rows > 0;

$orders = [];
while ($row = $result->fetch_assoc()) {
    // Customer name: user_fullname else order column else Guest
    $customerName = !empty($row['user_fullname']) ? $row['user_fullname'] : ($row[$name_col] ?? 'Guest');
    $row['customer_name'] = $customerName;

    // Customer email: order column (if detected) else ''
    $customerEmail = $email_col && isset($row[$email_col]) ? $row[$email_col] : '';
    $row['customer_email'] = $customerEmail;

    // Customer phone: order column (if detected) else ''
    $customerPhone = $phone_col && isset($row[$phone_col]) ? $row[$phone_col] : '';
    $row['customer_phone'] = $customerPhone;

    // Fetch items
    if (isset($row['items']) && !empty($row['items'])) {
        $row['items'] = json_decode($row['items'], true) ?? [];
    } elseif ($has_order_items) {
        $items_stmt = $conn->prepare("SELECT product_name as name, quantity as qty, price FROM order_items WHERE order_id = ?");
        $items_stmt->bind_param("i", $row['id']);
        $items_stmt->execute();
        $items_res = $items_stmt->get_result();
        $row['items'] = [];
        while ($item = $items_res->fetch_assoc()) {
            $row['items'][] = $item;
        }
        $items_stmt->close();
    } else {
        $row['items'] = [];
    }

    // Calculate total
    if (!isset($row['total_amount']) || $row['total_amount'] == 0) {
        $calc = 0;
        foreach ($row['items'] as $item) {
            $calc += ($item['price'] ?? 0) * ($item['qty'] ?? 1);
        }
        $row['total'] = $calc;
    } else {
        $row['total'] = $row['total_amount'];
    }

    // Order number fallback
    if (!isset($row['order_number']) || empty($row['order_number'])) {
        $row['order_number'] = 'ORD-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT);
    }

    $orders[] = $row;
}
$stmt->close();
$totalPages = ceil($totalOrders / $limit);

$adminPageTitle = 'All Orders - Admin | RD Vendora';
$adminPageHeading = 'All Orders';
$adminPageSubtitle = 'Orders across every store';
$adminSearchPlaceholder = 'Search platform...';
$adminShowHeader = true;
require __DIR__ . '/../includes/admin_layout_start.php';
?>
<div class="page-content">
        <div class="page-header">
            <h1 class="page-title">All Marketplace Orders</h1>
            <p class="page-subtitle">View and manage all orders from all stores.</p>
        </div>

        <div class="filters-bar">
            <div class="filters-left">
                <div class="search-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="orderSearch" placeholder="Order # or customer name..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <select class="filter-select" id="statusFilter">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $statusFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="processing" <?= $statusFilter == 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="shipped" <?= $statusFilter == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="delivered" <?= $statusFilter == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $statusFilter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
                <select class="filter-select" id="paymentFilter">
                    <option value="">All Payments</option>
                    <option value="paid" <?= $paymentFilter == 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="pending" <?= $paymentFilter == 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="refunded" <?= $paymentFilter == 'refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
                <button class="btn-sm" id="resetFilters">Reset</button>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Store</th>
                        <th>Date</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="9" style="text-align:center; padding:2rem;">No orders found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td style="font-weight:600; color:var(--primary);"><?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= htmlspecialchars($order['customer_name'] ?? 'Guest') ?></td>
                                <td><?= htmlspecialchars($order['store_name'] ?? 'N/A') ?></td>
                                <td><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></td>
                                <td><?= count($order['items']) ?> item<?= count($order['items']) != 1 ? 's' : '' ?></td>
                                <td style="font-weight:600;">₦<?= number_format($order['total'], 2) ?></td>
                                <td><span class="badge badge-<?= ($order['payment_status'] ?? 'pending') == 'paid' ? 'success' : 'warning' ?>"><?= ucfirst($order['payment_status'] ?? 'pending') ?></span></td>
                                <td><span class="badge badge-<?= $order['status'] == 'delivered' ? 'success' : ($order['status'] == 'cancelled' ? 'error' : 'info') ?>"><?= ucfirst($order['status'] ?? 'pending') ?></span></td>
                                <td><button class="btn-sm view-order" data-id="<?= $order['id'] ?>">View</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&payment=<?= urlencode($paymentFilter) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="modal-title">Order Details</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body" id="orderModalBody">
            <div style="text-align:center;">Loading...</div>
        </div>
    </div>
</div>
<script>
// Filters
    const orderSearch = document.getElementById('orderSearch');
    const statusFilter = document.getElementById('statusFilter');
    const paymentFilter = document.getElementById('paymentFilter');
    const resetBtn = document.getElementById('resetFilters');
    function applyFilters() {
        let url = 'admin-receive-order.php?';
        if (orderSearch.value) url += 'search=' + encodeURIComponent(orderSearch.value) + '&';
        if (statusFilter.value) url += 'status=' + encodeURIComponent(statusFilter.value) + '&';
        if (paymentFilter.value) url += 'payment=' + encodeURIComponent(paymentFilter.value) + '&';
        window.location.href = url;
    }
    orderSearch.addEventListener('change', applyFilters);
    statusFilter.addEventListener('change', applyFilters);
    paymentFilter.addEventListener('change', applyFilters);
    resetBtn.addEventListener('click', () => { window.location.href = 'admin-receive-order.php'; });

    // View order modal
    const modal = document.getElementById('orderModal');
    function closeModal() { modal.classList.remove('active'); document.body.style.overflow = ''; }
    function escapeHtml(str) { return String(str).replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'})[m]); }
    document.querySelectorAll('.view-order').forEach(btn => {
        btn.addEventListener('click', async function() {
            const orderId = this.dataset.id;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('orderModalBody').innerHTML = '<div style="text-align:center;">Loading...</div>';
            try {
                const res = await fetch('admin-get-order.php?id=' + orderId);
                const order = await res.json();
                if (order.error) throw new Error(order.error);
                let itemsHtml = '';
                if (order.items && order.items.length) {
                    itemsHtml = '<ul>' + order.items.map(i => `<li>${escapeHtml(i.name)} x ${i.qty} – ₦${(i.price * i.qty).toFixed(2)}</li>`).join('') + '</ul>';
                } else {
                    itemsHtml = '<p>No items found</p>';
                }
                let address = '';
                if (order.user_address) address = order.user_address;
                else if (order.shipping_address) address = order.shipping_address;
                else if (order.address) address = order.address;
                if (order.city) address += (address ? ', ' : '') + order.city;
                if (order.state) address += (address ? ', ' : '') + order.state;
                if (order.zip) address += (address ? ' ' : '') + order.zip;
                address = address || 'Not provided';
                document.getElementById('orderModalBody').innerHTML = `
                    <div class="order-detail-row"><div class="order-detail-label">Order #</div><div class="order-detail-value">${escapeHtml(order.order_number)}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Customer</div><div class="order-detail-value">${escapeHtml(order.user_name || 'Guest')}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Email</div><div class="order-detail-value">${escapeHtml(order.user_email || 'N/A')}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Phone</div><div class="order-detail-value">${escapeHtml(order.user_phone || 'N/A')}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Store</div><div class="order-detail-value">${escapeHtml(order.store_name || 'N/A')}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Date</div><div class="order-detail-value">${new Date(order.created_at).toLocaleString()}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Total</div><div class="order-detail-value">₦${parseFloat(order.total).toFixed(2)}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Payment Status</div><div class="order-detail-value">${escapeHtml(order.payment_status)}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Order Status</div><div class="order-detail-value">${escapeHtml(order.status)}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Address</div><div class="order-detail-value">${escapeHtml(address)}</div></div>
                    <div class="order-detail-row"><div class="order-detail-label">Items</div><div class="order-detail-value">${itemsHtml}</div></div>
                `;
            } catch (err) {
                document.getElementById('orderModalBody').innerHTML = '<div style="color:red;">Error loading order details: ' + err.message + '</div>';
            }
        });
    });
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    window.closeModal = closeModal;

    function logout() { if(confirm('Logout from admin panel?')) window.location.href='../logout.php'; }
</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
