<?php
// includes/admin_auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function adminHasPermission($pageName, $conn) {
    // Super admin (role_name = 'super_admin') has all permissions
    if (isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'super_admin') {
        return true;
    }

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        return false;
    }

    $roleId = $_SESSION['role_id'] ?? null;
    if (!$roleId) return false;

    $stmt = $conn->prepare("SELECT can_access FROM role_permissions WHERE role_id = ? AND page_name = ?");
    $stmt->bind_param("is", $roleId, $pageName);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return (bool)$row['can_access'];
    }
    return false;
}
?>