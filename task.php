<?php
require 'db.php';
$user = current_user();
$id = intval($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT t.*, u.name as requester_name FROM tasks t JOIN users u ON t.requester_id=u.id WHERE t.id = ?");
$stmt->execute([$id]);
$task = $stmt->fetch();
if (!$task) { echo "<script>alert('Task not found');window.location='marketplace.php'</script>"; exit; }

// fetch application for current user if worker
$app = null;
if ($user && $user['role']=='worker') {
    $stmt = $pdo->prepare("SELECT * FROM applications WHERE task_id=? AND worker_id=?");
    $stmt->execute([$id, $user['id']]);
    $app = $stmt->fetch();
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo htmlspecialchars($task['title']); ?> - Task</title>
<style>
 body{font-family:Inter,Arial;background:#f8fafc;margin:0}
 .wrap{max-width:900px;margin:18px auto;padding:12px}
 .card{background:white;padding:18px;border-radius:12px;box-shadow:0 8px 28px rgba(2,6,23,0.04)}
 .meta{color:#64748b;font-size:13px}
 button{background:#7c3aed;color:white;padding:8px 12px;border:0;border-radius:8px;cursor:pointer}
 textarea{width:100%;padding:10px;border-radius:8px;border:1px solid #e6e9ef}
</style>
</head><body>
<div class="wrap">
  <div class="card">
    <h2><?php echo htmlspecialchars($task['title']); ?></h2>
    <div class="meta">By <?php echo htmlspecialchars($task['requester_name']); ?> • Category: <?php echo htmlspecialchars($task['category']); ?> • $<?php echo number_format($task['payment'],2); ?></div>
    <p style="margin-top:12px;white-space:pre-line"><?php echo nl2br(htmlspecialchars($task['description'])); ?></p>
    <p class="meta">Slots: <?php echo $task['slots_filled']; ?>/<?php echo $task['total_slots']; ?> • Status: <?php echo $task['status']; ?></p>

    <?php if(!$user): ?>
      <p><a href="login.php">Login</a> or <a href="register.php">Register</a> to apply for this task.</p>
    <?php elseif($user['role']=='requester' && $user['id']==$task['requester_id']): ?>
      <p>You are the requester. <a href="requester_dashboard.php">Manage task</a></p>
    <?php else: ?>
      <?php if(!$app): ?>
        <?php if($task['slots_filled'] >= $task['total_slots']): ?>
          <p style="color:#b91c1c">All slots filled.</p>
        <?php else: ?>
          <button onclick="apply()">Apply & Accept</button>
          <script>
            function apply(){
              if(!confirm('Apply and accept task? You will be required to submit by the deadline.')) return;
              fetch('apply_task.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({action:'apply','task_id':<?php echo $task['id']; ?>})
              }).then(r=>r.json()).then(d=>{
                alert(d.message);
                if(d.ok) window.location='task.php?id=<?php echo $task['id']; ?>';
              });
            }
          </script>
        <?php endif; ?>
      <?php else: ?>
        <div style="margin-top:12px;padding:12px;border-radius:8px;background:#f8fafc">
          <strong>Status:</strong> <?php echo $app['status']; ?><br>
          <?php if($app['status']=='accepted'): ?>
            <form id="submitForm" onsubmit="submitWork(event)">
              <label>Submission (text)</label>
              <textarea name="submission_text" id="submission_text" required></textarea>
              <div style="margin-top:8px"><button type="submit">Submit Work</button></div>
            </form>
            <script>
              function submitWork(e){
                e.preventDefault();
                const txt = document.getElementById('submission_text').value.trim();
                if(!txt) {alert('Enter submission text'); return;}
                fetch('apply_task.php',{
                  method:'POST',
                  headers:{'Content-Type':'application/json'},
                  body: JSON.stringify({action:'submit','task_id':<?php echo $task['id']; ?>,'text':txt})
                }).then(res=>res.json()).then(d=>{
                  alert(d.message);
                  if(d.ok) window.location='worker_dashboard.php';
                });
              }
            </script>
          <?php elseif($app['status']=='submitted'): ?>
            <p>You have submitted. Awaiting requester review.</p>
            <?php if($task['status']=='in_review'): ?><p>In review.</p><?php endif; ?>
          <?php elseif($app['status']=='approved'): ?>
            <p style="color:green">Approved — payment credited to your balance.</p>
          <?php else: ?>
            <p><?php echo htmlspecialchars($app['status']); ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body></html>
