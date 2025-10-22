<?php
require 'db.php';
$user = current_user();
if (!$user || $user['role'] !== 'requester') {
    echo "<script>alert('Requesters only');window.location='index.php'</script>";
    exit;
}
$stmt = $pdo->prepare("SELECT * FROM tasks WHERE requester_id=? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$tasks = $stmt->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Requester Dashboard</title>
<style>
 body{font-family:Inter,Arial;background:#fff;margin:0}
 .wrap{max-width:1000px;margin:18px auto;padding:12px}
 .card{background:#fbfbff;padding:14px;border-radius:12px}
 table{width:100%;border-collapse:collapse}
 td,th{padding:8px;border-bottom:1px solid #eef2ff}
 .btn{background:#7c3aed;color:white;padding:6px 10px;border-radius:8px;border:0}
</style>
</head><body>
<div class="wrap">
  <h2>Requester Dashboard</h2>
  <div style="margin-bottom:12px"><a class="btn" href="post_task.php">Post new task</a></div>
  <div class="card">
    <h3>Your tasks</h3>
    <table><thead><tr><th>Title</th><th>Payment</th><th>Slots</th><th>Status</th><th>Manage</th></tr></thead>
    <tbody>
    <?php foreach($tasks as $t): ?>
      <tr>
        <td><?php echo htmlspecialchars($t['title']); ?></td>
        <td>$<?php echo number_format($t['payment'],2); ?></td>
        <td><?php echo $t['slots_filled']; ?>/<?php echo $t['total_slots']; ?></td>
        <td><?php echo $t['status']; ?></td>
        <td>
          <a href="task.php?id=<?php echo $t['id']; ?>">View</a> |
          <a href="manage_task.php?id=<?php echo $t['id']; ?>">Manage</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
  </div>
</div>
</body></html>
