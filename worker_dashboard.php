<?php
require 'db.php';
$user = current_user();
if (!$user || $user['role'] !== 'worker') {
    echo "<script>alert('Workers only');window.location='index.php'</script>";
    exit;
}
$stmt = $pdo->prepare("SELECT a.*, t.title, t.payment, t.requester_id, u.name as requester_name FROM applications a JOIN tasks t ON a.task_id=t.id JOIN users u ON t.requester_id=u.id WHERE a.worker_id=? ORDER BY a.applied_at DESC");
$stmt->execute([$user['id']]);
$apps = $stmt->fetchAll();

$transactions = $pdo->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
$transactions->execute([$user['id']]);
$txs = $transactions->fetchAll();
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Worker Dashboard</title>
<style>
 body{font-family:Inter,Arial;background:#ffffff;margin:0}
 .wrap{max-width:1000px;margin:20px auto;padding:12px}
 .card{background:#f8fafc;padding:14px;border-radius:12px}
 table{width:100%;border-collapse:collapse}
 td,th{padding:8px;border-bottom:1px solid #eef2ff}
 .btn{background:#0ea5a4;color:white;padding:6px 10px;border-radius:8px;border:0}
</style>
</head><body>
<div class="wrap">
  <h2>Worker Dashboard</h2>
  <div style="display:flex;gap:12px;align-items:center">
    <div class="card" style="flex:1">
      <h3>Your balance</h3>
      <div style="font-size:22px;font-weight:700">$<?php echo number_format($user['balance'],2); ?></div>
      <p><button class="btn" onclick="withdraw()">Withdraw (simulate)</button></p>
    </div>

    <div class="card" style="flex:2">
      <h3>Your tasks</h3>
      <table>
        <thead><tr><th>Task</th><th>Requester</th><th>Payment</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach($apps as $a): ?>
          <tr>
            <td><a href="task.php?id=<?php echo $a['task_id']; ?>"><?php echo htmlspecialchars($a['title']); ?></a></td>
            <td><?php echo htmlspecialchars($a['requester_name']); ?></td>
            <td>$<?php echo number_format($a['payment'],2); ?></td>
            <td><?php echo $a['status']; ?></td>
            <td>
              <?php if($a['status']=='approved'): ?><span>Paid</span>
              <?php elseif($a['status']=='submitted'): ?><span>Awaiting review</span>
              <?php elseif($a['status']=='accepted'): ?><a class="btn" href="task.php?id=<?php echo $a['task_id']; ?>">Submit</a>
              <?php else: echo '-'; endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="height:18px"></div>

  <div class="card">
    <h3>Transactions</h3>
    <?php if(!$txs) echo "<p>No transactions yet.</p>"; else { ?>
      <table><thead><tr><th>Date</th><th>Amount</th><th>Type</th><th>Note</th></tr></thead>
      <tbody>
      <?php foreach($txs as $t): ?>
        <tr><td><?php echo $t['created_at']; ?></td><td><?php echo $t['amount']; ?></td><td><?php echo $t['type']; ?></td><td><?php echo htmlspecialchars($t['note']); ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php } ?>
  </div>
</div>

<script>
function withdraw(){
  const w = prompt('Enter amount to withdraw (simulation):');
  if(!w) return;
  alert('Withdrawal simulated. In real app, integrate payment provider.');
}
</script>
</body></html>
