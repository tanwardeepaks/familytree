<?php
// Idempotent migration to add gotra and caste to families table
include '../db.php';

$queries = [
    "gotra" => "ALTER TABLE families ADD COLUMN gotra VARCHAR(255) DEFAULT NULL",
    "caste" => "ALTER TABLE families ADD COLUMN caste VARCHAR(255) DEFAULT NULL",
];

foreach ($queries as $col => $sql) {
    try {
        $conn->query($sql);
        echo "OK: added {$col} if not present.<br>";
    } catch (Exception $e) {
        // If column exists, MySQL will error; report but continue
        echo "Skipped/notice for {$col}: " . htmlspecialchars($conn->error) . "<br>";
    }
}

// Small sanity check: show families with their new fields
$res = $conn->query("SELECT id, name, gotra, caste FROM families ORDER BY id");
echo "<h3>Families (post-migration)</h3>";
echo "<table border=1 cellpadding=6><tr><th>ID</th><th>Name</th><th>Gotra</th><th>Caste</th></tr>";
while ($r = $res->fetch_assoc()) {
    echo "<tr><td>".(int)$r['id']."</td><td>".htmlspecialchars($r['name'])."</td><td>".htmlspecialchars($r['gotra'])."</td><td>".htmlspecialchars($r['caste'])."</td></tr>";
}
echo "</table>";

echo "<p>Migration complete. Back up your DB before running data backfills.</p>";

?>
