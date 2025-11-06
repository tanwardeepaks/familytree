<?php
// Redirect root delete to admin delete (preserve ID)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    header('Location: admin/delete_member.php?id=' . $id);
    exit;
}
header('Location: admin/dashboard.php');
exit;
?>