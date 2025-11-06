<?php
session_start();
include '../db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Sorting: allowed fields and mapping to SQL
$allowedSort = [
    'id' => 'fm.id',
    'name' => 'fm.name',
    'gender' => 'fm.gender',
    'father' => 'father_name',
    'mother' => 'mother_name',
    'spouse' => 'spouse_name'
];

// Build query with joins to fetch related names for display
// Fetch families for selector
$families = [];
$fres = $conn->query("SELECT id, name FROM families ORDER BY name");
while ($fr = $fres->fetch_assoc()) $families[] = $fr;

$selectedFamily = isset($_GET['family_id']) ? (int)$_GET['family_id'] : null;

$sql = "SELECT fm.*, 
               f.name AS father_name, f.id AS father_id,
               m.name AS mother_name, m.id AS mother_id,
               s.name AS spouse_name, s.id AS spouse_id
        FROM family_members fm
        LEFT JOIN family_members f ON fm.father_id = f.id
        LEFT JOIN family_members m ON fm.mother_id = m.id
        LEFT JOIN family_members s ON fm.spouse_id = s.id";

if ($selectedFamily) {
    $sql .= " WHERE fm.family_id = " . (int)$selectedFamily;
}

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard - Family Tree</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="../css/styles.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .actions {
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
        }
        .btn-delete {
            background: #f44336;
        }
        .btn-view {
            background: #2196F3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #4CAF50;
            color: white;
            cursor: pointer;
            padding-right: 25px !important;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .logout {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        /* DataTables customization */
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 15px;
        }
        .dataTables_wrapper .dataTables_filter input {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-left: 10px;
        }
        .dataTables_wrapper .dataTables_length select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 5px 10px;
            margin: 0 2px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4CAF50 !important;
            color: white !important;
            border-color: #4CAF50;
        }
        .dataTables_wrapper .dataTables_info {
            margin-top: 15px;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            outline: none;
            border-color: #4CAF50;
        }
    </style>
</head>
<body>
    <h2>Family Tree Admin Dashboard</h2>
    <a href="logout.php" class="btn btn-delete logout">Logout</a>
    
    <div class="dashboard-container">
        <div class="actions">
            <a href="add_member.php<?php echo $selectedFamily ? '?family_id='.$selectedFamily : ''; ?>" class="btn">Add New Member</a>
            <a href="../index.php<?php echo $selectedFamily ? '?family_id='.$selectedFamily : ''; ?>" target="_blank" class="btn">View Family Tree</a>
            <a href="families.php" class="btn btn-secondary">Manage Families</a>
            <label style="margin-left:10px;">Family: 
                <select id="family-select" onchange="location.href='?family_id='+this.value">
                    <option value="">All</option>
                    <?php foreach ($families as $f): ?>
                        <option value="<?php echo $f['id']; ?>" <?php echo ($selectedFamily == $f['id'])? 'selected':''; ?>><?php echo htmlspecialchars($f['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        
        <table id="members-table" class="display nowrap" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Gender</th>
                    <th>Father</th>
                    <th>Mother</th>
                    <th>Spouse</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($member = $res->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $member['id']; ?></td>
                        <td><?php echo htmlspecialchars($member['name']); ?></td>
                        <td><?php echo htmlspecialchars($member['gender']); ?></td>
                        <td>
                            <?php
                            if (!empty($member['father_id'])) {
                                echo htmlspecialchars($member['father_name']) . " (ID: " . htmlspecialchars($member['father_id']) . ")";
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if (!empty($member['mother_id'])) {
                                echo htmlspecialchars($member['mother_name']) . " (ID: " . htmlspecialchars($member['mother_id']) . ")";
                            }
                            ?>
                        </td>
                        <td>
                            <?php
                            if (!empty($member['spouse_id'])) {
                                echo htmlspecialchars($member['spouse_name']) . " (ID: " . htmlspecialchars($member['spouse_id']) . ")";
                            }
                            ?>
                        </td>
                        <td>
                            <a href="edit_member.php?id=<?php echo $member['id']; ?>" class="btn">Edit</a>
                            <a href="delete_member.php?id=<?php echo $member['id']; ?>" class="btn btn-delete" 
                               onclick="return confirm('Are you sure you want to delete this member?')">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script>
    $(document).ready(function() {
        $('#members-table').DataTable({
            responsive: true,
            pageLength: 25,
            order: [[1, 'asc']], // Default sort by Name (column 1)
            columnDefs: [
                { orderable: true, targets: [0,1,2,3,4,5] },
                { orderable: false, targets: -1 } // Actions column not sortable
            ],
            language: {
                search: "Search family members:",
                lengthMenu: "Show _MENU_ members per page",
                info: "Showing _START_ to _END_ of _TOTAL_ family members",
                infoEmpty: "No family members found",
                emptyTable: "No family members available"
            }
        });
    });
    </script>
</body>
</html>