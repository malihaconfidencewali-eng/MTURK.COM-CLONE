<?php
require 'db.php';
$user = current_user();

// Basic filtering by category/search
$search = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';

$sql = "SELECT t.*, u.name as requester_name FROM tasks t JOIN users u ON t.requester_id=u.id WHERE status='open'";
$params = [];
if ($search) { $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
if ($category) { $sql .= " AND category = ?"; $params[]=$category; }
$sql .= " ORDER BY t.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();
$categories = ['Data Entry','Surveys','Transcription','Image Labeling','Other'];
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Marketplace - MicroTasks Hub</title>
<style>
 body{font-family:Inter,Arial;background:#f8fafc;margin:0;color:#0f172a}
 .wrap{max-width:1100px;margin:20px auto;padding:10px}
 .top{display:flex;gap:12px;align-items:center;justify-content:space-between}
 .card{background:white;padding:14px;border-radius:12px;box-shadow:0 8px 28px rgba(2,6,23,0.05)}
 input,select{padding:8px;border-radius:8px;border:1px solid #e6e9ef}
 .task{display:flex;justify-content:space-between;padding:12px;border-bottom:1px solid #f1f5f9}
 .task a{font-weight:600;color:#0ea5a4;text-decoration:none}
 @media(max-width:700px){.top{flex-direction:column;align-items:stretch}}
</style>
</head><body>
<div class="wrap">
  <div style="display:flex;justify-content:space-between;align-items:center">
    <h2>Task Marketplace</h2>
    <div>
      <?php if($user): ?>
        <span style="margin-right:8px">Hello, <?php echo htmlspecialchars($user['name']); ?></span>
        <a href="logout.php">Logout</a>
      <?php else: ?>
        <a href="login.php">Login</a> | <a href="register.php">Register</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="top" style="margin-top:10px">
    <div style="flex:1">
      <form method="get" style="display:flex;gap:8px">
        <input name="q" placeholder="Search tasks..." value="<?php echo htmlspecialchars($search); ?>">
        <select name="category">
          <option value="">All categories</option>
          <?php foreach($categories as $c): $sel = ($category==$c)?'selected':''; echo "<option $sel>".htmlspecialchars($c)."</option>"; endforeach; ?>
        </select>
        <button type="submit">Search</button>
      </form>
    </div>
    <div>
      <?php if($user && $user['role']=='requester'): ?>
        <button onclick="window.location='post_task.php'">Post a Task</button>
      <?php endif; ?>
    </div>
  </div>

  <div style="margin-top:14px" class="card">
    <?php if(!$tasks) echo "<p style='padding:12px;color:#6b7280'>No tasks found.</p>"; ?>
    <?php foreach($tasks as $t): ?>
      <div class="task">
        <div>
          <a href="task.php?id=<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['title']); ?></a>
          <div style="color:#475569;font-size:13px"><?php echo htmlspecialchars(substr($t['description'],0,180)); ?>...</div>
          <div style="font-size:12px;color:#64748b">Requester: <?php echo htmlspecialchars($t['requester_name']); ?> • Category: <?php echo htmlspecialchars($t['category']); ?></div>
        </div>
        <div style="text-align:right">
          <div style="font-weight:700;font-size:18px">$<?php echo number_format($t['payment'],2); ?></div>
          <div style="font-size:12px;color:#64748b"><?php echo $t['slots_filled']; ?>/<?php echo $t['total_slots']; ?> taken</div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
</body></html>
