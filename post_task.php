<?php
require 'db.php';
$user = current_user();
if (!$user || $user['role'] !== 'requester') {
    echo "<script>alert('Only requesters can post tasks');window.location='marketplace.php'</script>";
    exit;
}
$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $payment = floatval($_POST['payment'] ?? 0);
    $category = trim($_POST['category'] ?? 'Other');
    $slots = max(1,intval($_POST['slots'] ?? 1));
    $deadline = $_POST['deadline'] ? date('Y-m-d H:i:s',strtotime($_POST['deadline'])) : null;
    $auto_approve = isset($_POST['auto_approve']) ? 1 : 0;
    if (!$title || $payment <= 0) $err = "Title and positive payment required.";
    else {
        $stmt = $pdo->prepare("INSERT INTO tasks (requester_id,title,description,category,payment,total_slots,deadline,auto_approve) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$user['id'],$title,$desc,$category,$payment,$slots,$deadline,$auto_approve]);
        echo "<script>window.location='requester_dashboard.php'</script>";
        exit;
    }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Post Task</title>
<style>
 body{font-family:Inter,Arial;background:#f7f8fb}
 .wrap{max-width:700px;margin:30px auto}
 .card{background:white;padding:18px;border-radius:12px;box-shadow:0 8px 26px rgba(2,6,23,0.06)}
 input,textarea,select{width:100%;padding:10px;margin:8px 0;border-radius:8px;border:1px solid #e6e9ef}
 button{background:#0ea5a4;color:white;padding:10px;border:0;border-radius:8px}
 label.inline{display:inline-flex;align-items:center;gap:8px}
</style>
</head><body>
<div class="wrap">
  <div class="card">
    <h3>Post a new task</h3>
    <?php if($err) echo "<div style='color:#b91c1c'>$err</div>"; ?>
    <form method="post" onsubmit="this.querySelector('button').disabled=true">
      <label>Title</label><input name="title" required>
      <label>Description</label><textarea name="description" rows="6"></textarea>
      <label>Category</label><input name="category" placeholder="Data Entry">
      <label>Payment (USD)</label><input name="payment" type="number" step="0.01" required>
      <label>Slots (how many workers can do this)</label><input name="slots" type="number" value="1" min="1">
      <label>Deadline (optional)</label><input name="deadline" type="datetime-local">
      <label class="inline"><input type="checkbox" name="auto_approve"> Auto approve submissions (auto-complete when worker submits)</label>
      <div style="margin-top:10px"><button type="submit">Post Task</button></div>
    </form>
  </div>
</div>
</body></html>
