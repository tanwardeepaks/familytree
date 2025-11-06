<?php
// Redirect root edit to admin edit page (forward ID if provided)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    header('Location: admin/edit_member.php?id=' . $id);
    exit;
}
// If no id provided, go to admin dashboard
header('Location: admin/dashboard.php');
exit;
?>
