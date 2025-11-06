<?php
session_start();
include 'db.php';

// Load families for dropdown
$families = [];
$fres = $conn->query("SELECT id, name, visibility, slug FROM families ORDER BY name");
while ($fr = $fres->fetch_assoc()) $families[] = $fr;

$members = [];
// Prepare default SVG avatars (data URIs) for male/female
$svgMale = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">'
    . '<rect width="100%" height="100%" fill="#e6f0ff"/>'
    . '<text x="50%" y="50%" font-size="72" text-anchor="middle" dy=".35em" fill="#2c7be5">♂</text>'
    . '</svg>';
$svgFemale = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">'
    . '<rect width="100%" height="100%" fill="#fff0f6"/>'
    . '<text x="50%" y="50%" font-size="72" text-anchor="middle" dy=".35em" fill="#e83e8c">♀</text>'
    . '</svg>';
$maleDataUri = 'data:image/svg+xml;utf8,' . rawurlencode($svgMale);
$femaleDataUri = 'data:image/svg+xml;utf8,' . rawurlencode($svgFemale);
$family_id = isset($_GET['family_id']) ? (int)$_GET['family_id'] : null;

// Check access for private families
$familyInfo = null;
if ($family_id) {
    $stmtF = $conn->prepare("SELECT * FROM families WHERE id = ?");
    $stmtF->bind_param('i', $family_id);
    $stmtF->execute();
    $familyInfo = $stmtF->get_result()->fetch_assoc();
}

$allowedView = true;
if ($familyInfo && $familyInfo['visibility'] === 'private') {
    // allow if logged-in member belongs to the family or admin session exists
    $isAdmin = isset($_SESSION['admin_id']);
    $isMember = isset($_SESSION['member_id']) && isset($_SESSION['member_family_id']) && $_SESSION['member_family_id'] == $family_id;
    if (!($isAdmin || $isMember)) {
        $allowedView = false;
    }
}

$query = "SELECT fm.*, 
    f.name AS father_name, m.name AS mother_name, s.name AS spouse_name
    FROM family_members fm
    LEFT JOIN family_members f ON fm.father_id = f.id
    LEFT JOIN family_members m ON fm.mother_id = m.id
    LEFT JOIN family_members s ON fm.spouse_id = s.id";
$where = '';
// If a family is selected, by default show members with that family_id.
// But if the viewer is a logged-in member who belongs to this family, expand the set
// to include connected relatives across families (parents/spouse) so owners can see
// relatives whose records may belong to other family entries.
// include_cross GET param: controls whether to include relatives across families
$isMember = isset($_SESSION['member_id']) && isset($_SESSION['member_family_id']) && $_SESSION['member_family_id'] == $family_id;
$include_cross = null;
if (isset($_GET['include_cross'])) {
    $include_cross = $_GET['include_cross'] === '1' ? true : false;
} else {
    // default: if viewer is a member of this family, default to true, otherwise false
    $include_cross = $isMember ? true : false;
}
if ($family_id) {
    if ($isMember && $include_cross) {
    // Gather connected member ids starting from members that are in this family OR whose paternal/maternal family is this family.
    $ids = [];
    $startRes = $conn->query("SELECT id FROM family_members WHERE family_id = " . (int)$family_id . " OR paternal_family_id = " . (int)$family_id . " OR maternal_family_id = " . (int)$family_id);
    while ($r = $startRes->fetch_assoc()) $ids[] = (int)$r['id'];

        // BFS/expansion to include parents/spouses recursively. Limit to avoid runaway queries.
        $processed = [];
        $pending = $ids;
        $limit = 1000;
        while (!empty($pending) && count($ids) < $limit) {
            $in = implode(',', array_map('intval', $pending));
            $pending = [];
            $sqlRel = "SELECT father_id, mother_id, spouse_id FROM family_members WHERE id IN (" . $in . ")";
            $rres = $conn->query($sqlRel);
            if (!$rres) break;
            while ($rowR = $rres->fetch_assoc()) {
                foreach (['father_id','mother_id','spouse_id'] as $col) {
                    $rid = isset($rowR[$col]) ? (int)$rowR[$col] : 0;
                    if ($rid && !in_array($rid, $ids, true)) {
                        $ids[] = $rid;
                        $pending[] = $rid;
                    }
                }
            }
        }

        if (empty($ids)) {
            // Fallback: show nothing for empty family
            $where = ' WHERE 0';
        } else {
            $where = ' WHERE fm.id IN (' . implode(',', array_map('intval', $ids)) . ')';
        }
    } else {
        $where = ' WHERE fm.family_id = ' . (int)$family_id;
    }
}
$query = $query . $where . "\n    ORDER BY fm.id";
$result = $conn->query($query);

// Process the results into nodes array
$nodes = [];
while ($row = $result->fetch_assoc()) {
    $node = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'gender' => $row['gender'],
        'img' => !empty($row['photo']) ? 'uploads/' . $row['photo'] : ($row['gender'] === 'female' ? $femaleDataUri : $maleDataUri),
        'date_of_birth' => $row['date_of_birth'] ?? null,
        'birth_time' => $row['birth_time'] ?? null,
        'birth_place' => $row['birth_place'] ?? null,
        'education' => $row['education'] ?? null,
        'gotra' => $row['gotra'] ?? null,
        'caste' => $row['caste'] ?? null,
        'status' => $row['status'] ?? null,
        'occupation' => $row['occupation'] ?? null,
        'mobile_no' => $row['mobile_no'] ?? null,
        'address' => $row['address'] ?? null,
        'father_name' => $row['father_name'] ?? null,
        'mother_name' => $row['mother_name'] ?? null,
        'spouse_name' => $row['spouse_name'] ?? null
    ];

    // Add father ID if exists
    if (!empty($row['father_id'])) {
        $node['fid'] = (int)$row['father_id'];
    }

    // Add mother ID if exists
    if (!empty($row['mother_id'])) {
        $node['mid'] = (int)$row['mother_id'];
    }

    // Add spouse ID if exists
    if (!empty($row['spouse_id'])) {
        $node['pids'] = [(int)$row['spouse_id']];
    }

    $nodes[] = $node;
}

// Convert nodes array to JSON for JavaScript
$nodesJson = json_encode($nodes);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Family Tree</title>
    <meta charset="utf-8">
    <style>
        body {
            margin: 0;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 20px;
        }
        .actions {
            text-align: center;
            margin-bottom: 20px;
        }
        .add-member-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .add-member-btn:hover {
            background: #45a049;
        }
        #tree {
            width: 100%;
            height: calc(100vh - 120px);
        }
        /* Modal & details table styling */
        #member-modal { display:none; align-items:center; justify-content:center; }
        #member-modal-content { box-shadow: 0 6px 20px rgba(0,0,0,0.15); }
        #member-modal-body table { border-collapse: collapse; width: 100%; }
        #member-modal-body th { background: #f5f5f5; padding: 10px; text-align: left; vertical-align: top; width: 30%; }
        #member-modal-body td { padding: 10px; border-bottom: 1px solid #eee; }
        #member-modal-body img { max-width: 120px; border-radius: 6px; display:block; margin-top:6px; }
    </style>
    <script src="FamilyTree.js"></script>
</head>
<body>
    <h2>Family Tree</h2>
    <div class="actions">
        <label style="margin-right:12px;">Family: 
            <select id="family-select-top" onchange="onFamilyChange()">
                <option value="">All</option>
                <?php foreach ($families as $f): ?>
                    <option value="<?php echo $f['id']; ?>" <?php echo ($family_id == $f['id'])? 'selected':''; ?>><?php echo htmlspecialchars($f['name']); ?> <?php echo $f['visibility']==='private' ? '(private)':''; ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label style="margin-left:10px;">
            <input type="checkbox" id="include-cross" <?php echo $include_cross ? 'checked' : ''; ?> <?php echo $isMember ? '' : 'disabled'; ?>> Include cross-family relatives
            <?php if (!$isMember && $family_id): ?>
                <small style="display:block;color:#666">(Only available to family members)</small>
            <?php endif; ?>
        </label>

        <?php if (isset($_SESSION['member_id'])): ?>
            <a href="member_dashboard.php" class="add-member-btn">My Account</a>
            <a href="member_logout.php" class="add-member-btn">Logout</a>
        <?php else: ?>
            <a href="member_login.php" class="add-member-btn">Login</a>
            <a href="member_register.php" class="add-member-btn">Register</a>
        <?php endif; ?>
    </div>
    <script>
    function onFamilyChange() {
        const f = document.getElementById('family-select-top').value;
        const include = document.getElementById('include-cross').checked ? '1' : '0';
        let url = '?';
        if (f) url += 'family_id=' + encodeURIComponent(f) + '&';
        url += 'include_cross=' + include;
        location.href = url;
    }
    // When checkbox toggled, reload keeping current family_id
    document.getElementById('include-cross').addEventListener('change', function(){
        const f = document.getElementById('family-select-top').value;
        let url = '?';
        if (f) url += 'family_id=' + encodeURIComponent(f) + '&';
        url += 'include_cross=' + (this.checked ? '1' : '0');
        location.href = url;
    });
    // keep select in sync if user used top query params
    (function(){
        const sel = document.getElementById('family-select-top');
        if (sel) sel.value = '<?php echo $family_id ?: ''; ?>';
    })();
    </script>

    <?php if (!$allowedView): ?>
        <div style="max-width:800px;margin:40px auto;padding:20px;background:#fff;border-radius:8px;text-align:center;">
            <h3>Private family</h3>
            <p>This family is private. Please <a href="member_login.php">login</a> with a member account that belongs to this family to view the tree.</p>
        </div>
    <?php else: ?>
        <div id="tree"></div>
    <?php endif; ?>
    <!-- Member detail modal -->
    <div id="member-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
        <div id="member-modal-content" style="background:#fff; max-width:720px; width:90%; margin:auto; padding:16px; border-radius:8px; position:relative;">
            <button id="member-modal-close" style="position:absolute;right:12px;top:8px;background:#f44336;color:#fff;border:none;padding:6px 10px;border-radius:4px;cursor:pointer;">Close</button>
            <div id="member-modal-body"></div>
        </div>
    </div>
    <script>
    let family = new FamilyTree("#tree", {
        siblingSeparation: 50,
        nodeBinding: {
            field_0: "name",
            img_0: "img"
        },
        nodes: <?php echo $nodesJson; ?>,
        template: "john",
        <?php if (isset($_GET['focus'])): ?>
        roots: [<?php echo (int)$_GET['focus']; ?>],
        <?php endif; ?>
        nodeMenu: {
            edit: {
                text: "Edit",
                icon: "✏️",
                onClick: function(nodeId) {
                    window.location.href = `admin/edit_member.php?id=${nodeId}`;
                }
            }
        }
    });
        
        // Custom node template for gender
        family.on('render', function(sender, args){
            args.content = args.content.replace(/{item.gender}/g, function(match, item, node) {
                return node.gender === 'female' ? '👩' : '👨';
            });
        });

        // Show member details in a modal when a node is clicked
        family.on('click', function(sender, args) {
            var node = args.node;
            if (!node) return;

            var rows = [];
            function addRow(label, value) {
                rows.push('<tr><th style="text-align:left;padding:6px 8px;background:#f5f5f5;width:35%">'+label+'</th><td style="padding:6px 8px">'+(value?value:'-')+'</td></tr>');
            }

            var imgHtml = node.img ? '<img src="'+node.img+'" style="max-width:120px;border-radius:6px;display:block;margin-bottom:8px">' : '';
            var nameHtml = '<strong style="font-size:1.2em">'+(node.name||'')+'</strong>';

            addRow('Name', nameHtml + imgHtml);
            addRow('Gender', node.gender);
            addRow('Date of Birth', node.date_of_birth || '-');
            addRow('Birth Time', node.birth_time || '-');
            addRow('Birth Place', node.birth_place || '-');
            addRow('Education', node.education || '-');
            addRow('Occupation', node.occupation || '-');
            addRow('Mobile', node.mobile_no || '-');
            addRow('Address', node.address || '-');
            addRow('Gotra', node.gotra || '-');
            addRow('Caste', node.caste || '-');
            addRow('Status', node.status || '-');
            addRow('Father', node.father_name || (node.fid?('ID: '+node.fid):'-'));
            addRow('Mother', node.mother_name || (node.mid?('ID: '+node.mid):'-'));
            addRow('Spouse', node.spouse_name || (node.pids && node.pids.length?('ID: '+node.pids[0]):'-'));

            var table = '<table style="width:100%;border-collapse:collapse;">'+rows.join('')+'</table>';
            document.getElementById('member-modal-body').innerHTML = table;
            var modal = document.getElementById('member-modal');
            modal.style.display = 'flex';
        });

        // Modal close
        document.addEventListener('click', function(e){
            var modal = document.getElementById('member-modal');
            if (!modal) return;
            if (e.target && e.target.id === 'member-modal-close') {
                modal.style.display = 'none';
            }
            // click outside content closes
            if (e.target && e.target.id === 'member-modal') {
                modal.style.display = 'none';
            }
        });
    </script>
</body>
</html>
