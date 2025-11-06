<?php
session_start();
include '../db.php';

// Simple CRUD for families. Requires admin login.
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$action = $_GET['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_family'])) {
        $name = trim($_POST['name']);
        $slug = preg_replace('/[^a-z0-9]+/','-',strtolower(trim($_POST['slug']))) ?: null;
        $visibility = in_array($_POST['visibility'] ?? 'private', ['private','public']) ? $_POST['visibility'] : 'private';
        $owner_id = isset($_POST['owner_id']) && is_numeric($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
        $gotra = trim($_POST['gotra'] ?? '');
        $caste = trim($_POST['caste'] ?? '');

        $stmt = $conn->prepare("INSERT INTO families (name, slug, owner_id, visibility, gotra, caste) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssisss', $name, $slug, $owner_id, $visibility, $gotra, $caste);
        if ($stmt->execute()) {
            header('Location: families.php');
            exit;
        } else {
            $error = $conn->error;
        }
    }
    if (isset($_POST['update_family']) && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $slug = preg_replace('/[^a-z0-9]+/','-',strtolower(trim($_POST['slug']))) ?: null;
        $visibility = in_array($_POST['visibility'] ?? 'private', ['private','public']) ? $_POST['visibility'] : 'private';
        $owner_id = isset($_POST['owner_id']) && is_numeric($_POST['owner_id']) ? (int)$_POST['owner_id'] : null;
        $gotra = trim($_POST['gotra'] ?? '');
        $caste = trim($_POST['caste'] ?? '');

        $stmt = $conn->prepare("UPDATE families SET name = ?, slug = ?, owner_id = ?, visibility = ?, gotra = ?, caste = ? WHERE id = ?");
        $stmt->bind_param('ssisssi', $name, $slug, $owner_id, $visibility, $gotra, $caste, $id);
        if ($stmt->execute()) {
            header('Location: families.php');
            exit;
        } else {
            $error = $conn->error;
        }
    }
}

if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    // Before deleting, set family_id to NULL for members in that family
    $conn->query("UPDATE family_members SET family_id = NULL WHERE family_id = $id");
    $conn->query("DELETE FROM families WHERE id = $id");
    header('Location: families.php');
    exit;
}

// Fetch families with optional sorting
$allowedSort = [
    'id' => 'id',
    'name' => 'name',
    'slug' => 'slug',
    'gotra' => 'gotra',
    'caste' => 'caste',
    'visibility' => 'visibility',
    'created' => 'created_at'
];
$sort = $_GET['sort'] ?? 'created';
$dir = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
if (!isset($allowedSort[$sort])) $sort = 'created';
$orderBy = $allowedSort[$sort] . ' ' . $dir;

$families = [];
$res = $conn->query("SELECT * FROM families ORDER BY $orderBy");
while ($r = $res->fetch_assoc()) $families[] = $r;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Families - Admin</title>
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        table { width:100%; border-collapse: collapse; }
        th, td { padding:8px; border-bottom:1px solid #eee; }
        .actions { margin-bottom: 12px; }
        form.inline { display:inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Families</h1>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="actions">
            <a href="families.php?action=new" class="btn">Create Family</a>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>

        <?php if ($action === 'new' || ($action === 'edit' && isset($_GET['id']))):
            $edit = ['id'=>null,'name'=>'','slug'=>'','visibility'=>'private','owner_id'=>null];
            if ($action === 'edit') {
                $id = (int)$_GET['id'];
                $stmt = $conn->prepare('SELECT * FROM families WHERE id = ?');
                $stmt->bind_param('i',$id); $stmt->execute(); $edit = $stmt->get_result()->fetch_assoc();
            }
        ?>
            <h2><?php echo $action === 'new' ? 'Create Family' : 'Edit Family'; ?></h2>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($edit['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Slug (optional)</label>
                    <input type="text" name="slug" value="<?php echo htmlspecialchars($edit['slug']); ?>">
                </div>
                <div class="form-group">
                    <label>Visibility</label>
                    <select name="visibility">
                        <option value="private" <?php echo ($edit['visibility']==='private')? 'selected':''; ?>>Private</option>
                        <option value="public" <?php echo ($edit['visibility']==='public')? 'selected':''; ?>>Public</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Gotra</label>
                    <input type="text" name="gotra" value="<?php echo htmlspecialchars($edit['gotra'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Caste</label>
                    <input type="text" name="caste" value="<?php echo htmlspecialchars($edit['caste'] ?? ''); ?>">
                </div>
                <div>
                    <?php if ($action === 'new'): ?>
                        <button type="submit" name="create_family" class="btn">Create</button>
                    <?php else: ?>
                        <button type="submit" name="update_family" class="btn">Update</button>
                    <?php endif; ?>
                    <a href="families.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <h2>Existing Families</h2>
            <table>
                <thead>
                    <tr>
                        <?php
                        // Helper to build header links that toggle direction
                        function th_link($key, $label, $currentSort, $currentDir) {
                            $dir = 'asc';
                            if ($currentSort === $key && $currentDir === 'ASC') $dir = 'desc';
                            $href = "?sort={$key}&dir={$dir}";
                            return "<th><a href='" . htmlspecialchars($href) . "'>" . htmlspecialchars($label) . "</a></th>";
                        }
                        echo th_link('id','ID',$sort,$dir);
                        echo th_link('name','Name',$sort,$dir);
                        echo th_link('gotra','Gotra',$sort,$dir);
                        echo th_link('caste','Caste',$sort,$dir);
                        echo th_link('slug','Slug',$sort,$dir);
                        echo th_link('visibility','Visibility',$sort,$dir);
                        echo th_link('created','Created',$sort,$dir);
                        ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($families as $f): ?>
                    <tr>
                        <td><?php echo (int)$f['id']; ?></td>
                        <td><?php echo htmlspecialchars($f['name']); ?></td>
                        <td><?php echo htmlspecialchars($f['gotra'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($f['caste'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($f['slug']); ?></td>
                        <td><?php echo htmlspecialchars($f['visibility']); ?></td>
                        <td><?php echo htmlspecialchars($f['created_at']); ?></td>
                        <td>
                            <a href="families.php?action=edit&id=<?php echo $f['id']; ?>" class="btn">Edit</a>
                            <a href="families.php?action=delete&id=<?php echo $f['id']; ?>" class="btn btn-delete" onclick="return confirm('Delete this family? This will unassign its members.')">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>