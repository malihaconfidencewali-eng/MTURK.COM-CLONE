<?php
// index.php - Single-file MTurk-like microtask clone (MySQL-based)
// Put this file into your web server root. Uses internal CSS/JS only.
// DB credentials (change $DB_HOST if needed)
session_start();
$DB_HOST = 'localhost';
$DB_NAME = 'dbv7ifghadtkcu';
$DB_USER = 'ueyhm8rqreljw';
$DB_PASS = 'gutn2hie5vxa';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    echo "<h2>DB Connection failed</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

/* ----- Initialize DB (create tables if not exist) ----- */
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('worker','requester','admin') NOT NULL DEFAULT 'worker',
  balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  requester_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  category VARCHAR(80) DEFAULT 'Other',
  payment DECIMAL(8,2) NOT NULL,
  total_slots INT NOT NULL DEFAULT 1,
  slots_filled INT NOT NULL DEFAULT 0,
  deadline DATETIME NULL,
  status ENUM('open','in_review','completed','cancelled') DEFAULT 'open',
  auto_approve TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  task_id INT NOT NULL,
  worker_id INT NOT NULL,
  status ENUM('applied','accepted','submitted','rejected','approved') DEFAULT 'applied',
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME NULL,
  submission_text TEXT NULL,
  submission_file VARCHAR(255) NULL,
  rating INT NULL,
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (worker_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  type ENUM('credit','debit') NOT NULL,
  note VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

/* ----- Seeder: create demo users & tasks if DB empty ----- */
$countTasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
if ($countTasks == 0) {
    // create demo users if not exists
    $ensureUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $createUser = $pdo->prepare("INSERT INTO users (name,email,password,role,balance) VALUES (?,?,?,?,?)");
    $demoRequester = ['Demo Requester','alice@demo.com','password123','requester',100.00];
    $demoWorker = ['Demo Worker','bob@demo.com','password123','worker',5.00];

    $ensureUser->execute([$demoRequester[1]]);
    if (!$ensureUser->fetch()) {
        $createUser->execute([$demoRequester[0], $demoRequester[1], password_hash($demoRequester[2], PASSWORD_DEFAULT), $demoRequester[3], $demoRequester[4]]);
    }
    $ensureUser->execute([$demoWorker[1]]);
    if (!$ensureUser->fetch()) {
        $createUser->execute([$demoWorker[0], $demoWorker[1], password_hash($demoWorker[2], PASSWORD_DEFAULT), $demoWorker[3], $demoWorker[4]]);
    }
    // fetch requester id
    $rq = $pdo->prepare("SELECT id FROM users WHERE email=?"); $rq->execute([$demoRequester[1]]); $rqid = $rq->fetchColumn();

    $tasks = [
        ['Quick survey — 5 questions','Answer 5 short questions about shopping.', 'Surveys', 0.50, 10, 1],
        ['Image tags — 10 images','Label objects in provided images.', 'Image Labeling', 1.00, 5, 0],
        ['Transcribe 30 sec audio','Transcribe short audio into text.', 'Transcription', 0.80, 8, 0],
        ['Data entry — copy rows','Copy 50 rows from CSV to form.', 'Data Entry', 1.50, 3, 1],
        ['Opinion poll — 3 Q','Three yes/no questions about a product.', 'Surveys', 0.30, 20, 1],
    ];
    $ins = $pdo->prepare("INSERT INTO tasks (requester_id,title,description,category,payment,total_slots,auto_approve) VALUES (?,?,?,?,?,?,?)");
    foreach ($tasks as $t) $ins->execute([$rqid, $t[0], $t[1], $t[2], $t[3], $t[4], $t[5]]);
}

/* ----- Helpers ----- */
function is_logged_in(){ return !empty($_SESSION['user_id']); }
function current_user($pdo){ if(!is_logged_in()) return null; $st=$pdo->prepare("SELECT id,name,email,role,balance FROM users WHERE id=?"); $st->execute([$_SESSION['user_id']]); return $st->fetch(); }
$user = current_user($pdo);

/* ----- HTTP POST endpoints (AJAX JSON) ----- */
$raw = file_get_contents('php://input');
if ($raw) {
    $data = json_decode($raw, true);
    if ($data && !empty($data['action'])) {
        header('Content-Type: application/json');
        $action = $data['action'];

        // REGISTER
        if ($action === 'register') {
            $name = trim($data['name'] ?? '');
            $email = strtolower(trim($data['email'] ?? ''));
            $pass = $data['password'] ?? '';
            $role = ($data['role'] === 'requester') ? 'requester' : 'worker';
            if (!$name || !$email || !$pass) { echo json_encode(['ok'=>false,'message'=>'All fields required']); exit; }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'message'=>'Invalid email']); exit; }
            $st = $pdo->prepare("SELECT id FROM users WHERE email=?"); $st->execute([$email]); if ($st->fetch()) { echo json_encode(['ok'=>false,'message'=>'Email exists']); exit; }
            $h = password_hash($pass, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)"); $ins->execute([$name,$email,$h,$role]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            echo json_encode(['ok'=>true,'message'=>'Registered and logged in']);
            exit;
        }

        // LOGIN
        if ($action === 'login') {
            $email = strtolower(trim($data['email'] ?? '')); $pass = $data['password'] ?? '';
            if (!$email || !$pass) { echo json_encode(['ok'=>false,'message'=>'Email & password required']); exit; }
            $st = $pdo->prepare("SELECT id,password FROM users WHERE email=?"); $st->execute([$email]); $u = $st->fetch();
            if (!$u || !password_verify($pass, $u['password'])) { echo json_encode(['ok'=>false,'message'=>'Invalid credentials']); exit; }
            $_SESSION['user_id'] = $u['id'];
            echo json_encode(['ok'=>true,'message'=>'Logged in']);
            exit;
        }

        // LOGOUT
        if ($action === 'logout') {
            session_unset(); session_destroy(); echo json_encode(['ok'=>true]); exit;
        }

        // POST TASK (requester)
        if ($action === 'post_task') {
            if (!is_logged_in()) { echo json_encode(['ok'=>false,'message'=>'Login required']); exit; }
            $cu = current_user($pdo);
            if ($cu['role'] !== 'requester') { echo json_encode(['ok'=>false,'message'=>'Only requesters can post']); exit; }
            $title = trim($data['title'] ?? ''); $desc = trim($data['description'] ?? ''); $category = trim($data['category'] ?? 'Other');
            $payment = floatval($data['payment'] ?? 0); $slots = max(1,intval($data['slots'] ?? 1)); $auto = !empty($data['auto']) ? 1 : 0;
            if (!$title || $payment <= 0) { echo json_encode(['ok'=>false,'message'=>'Title and positive payment required']); exit; }
            $ins = $pdo->prepare("INSERT INTO tasks (requester_id,title,description,category,payment,total_slots,auto_approve) VALUES (?,?,?,?,?,?,?)");
            $ins->execute([$cu['id'],$title,$desc,$category,$payment,$slots,$auto]);
            echo json_encode(['ok'=>true,'message'=>'Task posted']);
            exit;
        }

        // APPLY (worker)
        if ($action === 'apply') {
            if (!is_logged_in()) { echo json_encode(['ok'=>false,'message'=>'Login required']); exit; }
            $cu = current_user($pdo); if ($cu['role'] !== 'worker') { echo json_encode(['ok'=>false,'message'=>'Only workers can apply']); exit; }
            $task_id = intval($data['task_id'] ?? 0); if (!$task_id) { echo json_encode(['ok'=>false,'message'=>'Invalid task']); exit; }
            $st = $pdo->prepare("SELECT * FROM tasks WHERE id=?"); $st->execute([$task_id]); $t = $st->fetch(); if (!$t) { echo json_encode(['ok'=>false,'message'=>'Task not found']); exit; }
            if ($t['slots_filled'] >= $t['total_slots']) { echo json_encode(['ok'=>false,'message'=>'Slots full']); exit; }
            $chk = $pdo->prepare("SELECT id FROM applications WHERE task_id=? AND worker_id=?"); $chk->execute([$task_id,$cu['id']]); if ($chk->fetch()) { echo json_encode(['ok'=>false,'message'=>'Already applied']); exit; }
            $ins = $pdo->prepare("INSERT INTO applications (task_id,worker_id,status) VALUES (?,?,?)"); $ins->execute([$task_id,$cu['id'],'accepted']);
            $pdo->prepare("UPDATE tasks SET slots_filled = slots_filled + 1 WHERE id = ?")->execute([$task_id]);
            echo json_encode(['ok'=>true,'message'=>'Applied and accepted']);
            exit;
        }

        // SUBMIT (worker)
        if ($action === 'submit') {
            if (!is_logged_in()) { echo json_encode(['ok'=>false,'message'=>'Login required']); exit; }
            $cu = current_user($pdo); if ($cu['role'] !== 'worker') { echo json_encode(['ok'=>false,'message'=>'Only workers can submit']); exit; }
            $task_id = intval($data['task_id'] ?? 0); $text = trim($data['text'] ?? '');
            if (!$task_id || !$text) { echo json_encode(['ok'=>false,'message'=>'Task and submission text required']); exit; }
            $appSt = $pdo->prepare("SELECT a.*, t.auto_approve, t.payment FROM applications a JOIN tasks t ON a.task_id=t.id WHERE a.task_id=? AND a.worker_id=?");
            $appSt->execute([$task_id, $cu['id']]); $app = $appSt->fetch();
            if (!$app) { echo json_encode(['ok'=>false,'message'=>'You have not applied to this task']); exit; }
            if ($app['status'] !== 'accepted') { echo json_encode(['ok'=>false,'message'=>'Cannot submit in this state: '.$app['status']]); exit; }
            $pdo->prepare("UPDATE applications SET status='submitted', submitted_at=NOW(), submission_text=? WHERE id=?")->execute([$text,$app['id']]);
            if ($app['auto_approve']) {
                // auto-approve: approve application and credit worker
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE applications SET status='approved' WHERE id=?")->execute([$app['id']]);
                    $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$app['payment'], $cu['id']]);
                    $pdo->prepare("INSERT INTO transactions (user_id,amount,type,note) VALUES (?,?,?,?)")->execute([$cu['id'],$app['payment'],'credit','Auto payment for task #'.$task_id]);
                    // optionally set task status to completed if slots filled == total_slots
                    $pdo->commit();
                    echo json_encode(['ok'=>true,'message'=>'Submitted and auto-approved. Payment credited.']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['ok'=>false,'message'=>'Auto-approve error: '.$e->getMessage()]);
                }
            } else {
                // normal flow, set task in_review
                $pdo->prepare("UPDATE tasks SET status='in_review' WHERE id=?")->execute([$task_id]);
                echo json_encode(['ok'=>true,'message'=>'Submitted. Requester will review.']);
            }
            exit;
        }

        // APPROVE (requester) - approve a particular application id
        if ($action === 'approve') {
            if (!is_logged_in()) { echo json_encode(['ok'=>false,'message'=>'Login required']); exit; }
            $cu = current_user($pdo); if ($cu['role'] !== 'requester') { echo json_encode(['ok'=>false,'message'=>'Only requesters can approve']); exit; }
            $application_id = intval($data['application_id'] ?? 0); if (!$application_id) { echo json_encode(['ok'=>false,'message'=>'Invalid application']); exit; }
            $q = $pdo->prepare("SELECT a.*, t.requester_id, t.payment FROM applications a JOIN tasks t ON a.task_id=t.id WHERE a.id=?");
            $q->execute([$application_id]); $a = $q->fetch(); if (!$a) { echo json_encode(['ok'=>false,'message'=>'Application not found']); exit; }
            if ($a['requester_id'] != $cu['id']) { echo json_encode(['ok'=>false,'message'=>'Not your task']); exit; }
            if ($a['status'] !== 'submitted') { echo json_encode(['ok'=>false,'message'=>'Invalid status for approval']); exit; }
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE applications SET status='approved' WHERE id=?")->execute([$application_id]);
                // credit worker
                $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$a['payment'], $a['worker_id']]);
                $pdo->prepare("INSERT INTO transactions (user_id,amount,type,note) VALUES (?,?,?,?)")->execute([$a['worker_id'],$a['payment'],'credit','Payment for task #'.$a['task_id']]);
                // optionally set task status completed if all slots used & approved etc (simple)
                $pdo->commit();
                echo json_encode(['ok'=>true,'message'=>'Approved & worker paid']);
            } catch (Exception $e) {
                $pdo->rollBack(); echo json_encode(['ok'=>false,'message'=>'Approve failed: '.$e->getMessage()]);
            }
            exit;
        }

        // GET CURRENT USER (for client)
        if ($action === 'me') {
            if (!is_logged_in()) { echo json_encode(['ok'=>false]); exit; }
            $cu = current_user($pdo);
            echo json_encode(['ok'=>true,'user'=>$cu]); exit;
        }

        echo json_encode(['ok'=>false,'message'=>'Unknown action']);
        exit;
    }
}

/* ----- Helper queries for page render ----- */
$categories = ['Data Entry','Surveys','Transcription','Image Labeling','Other'];
$search = trim($_GET['q'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$sql = "SELECT t.*, u.name as requester_name FROM tasks t JOIN users u ON t.requester_id=u.id WHERE 1=1";
$params = [];
if ($search) { $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($categoryFilter) { $sql .= " AND t.category = ?"; $params[] = $categoryFilter; }
$sql .= " ORDER BY t.created_at DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

/* ----- Page HTML starts here ----- */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MicroTasks Hub — MTurk Clone (Single file)</title>
<style>
  /* ---------- Internal CSS: Clean modern look ---------- */
  :root{--bg:#f3f7fb;--card:#ffffff;--muted:#6b7280;--accent1:#0ea5a4;--accent2:#7c3aed}
  *{box-sizing:border-box}
  body{margin:0;font-family:Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;background:var(--bg);color:#0f172a}
  header{background:linear-gradient(90deg,var(--accent1),var(--accent2));color:white;padding:28px 16px}
  header .wrap{max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between}
  header h1{margin:0;font-size:22px}
  .container{max-width:1100px;margin:18px auto;padding:12px}
  .grid{display:grid;grid-template-columns:1fr 360px;gap:18px}
  .card{background:var(--card);border-radius:12px;padding:16px;box-shadow:0 8px 30px rgba(2,6,23,0.06)}
  .muted{color:var(--muted)}
  .btn{background:var(--accent2);color:white;padding:10px 12px;border-radius:10px;border:0;cursor:pointer}
  .btn-ghost{background:transparent;color:var(--accent2);border:1px solid rgba(124,58,237,0.12);padding:8px 10px;border-radius:8px}
  input,select,textarea{width:100%;padding:10px;border-radius:8px;border:1px solid #e6e9ef;margin-top:8px}
  .task-row{display:flex;justify-content:space-between;padding:12px;border-bottom:1px solid #f1f5f9;align-items:center}
  .task-row:last-child{border-bottom:none}
  .small{font-size:13px;color:var(--muted)}
  .pill{background:#f1f5f9;padding:6px 10px;border-radius:999px;font-size:13px}
  .nav{display:flex;gap:10px;align-items:center}
  .tabs{display:flex;gap:8px;margin-bottom:12px}
  .tabs button{background:transparent;border:0;padding:8px 10px;border-radius:8px;cursor:pointer}
  .tabs button.active{background:#eef2ff}
  @media(max-width:980px){ .grid{grid-template-columns:1fr} header .wrap{flex-direction:column;gap:12px} .nav{flex-wrap:wrap} }
</style>
</head>
<body>
<header>
  <div class="wrap">
    <div>
      <h1>MicroTasks Hub</h1>
      <div class="small">Complete micro-tasks and earn — post tasks as requester.</div>
    </div>
    <div class="nav" id="topNav">
      <?php if ($user): ?>
        <div class="small" style="background:rgba(255,255,255,0.08);padding:8px 10px;border-radius:8px"><?php echo htmlspecialchars($user['name']); ?> (<?php echo $user['role']; ?>)</div>
        <div class="small">Balance: $<?php echo number_format($user['balance'],2); ?></div>
        <button class="btn-ghost" onclick="logout()">Logout</button>
      <?php else: ?>
        <button class="btn-ghost" onclick="showPanel('login')">Login</button>
        <button class="btn" onclick="showPanel('register')">Sign up</button>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="container">
  <div class="grid">
    <main>
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <div>
            <h2 style="margin:0">Task Marketplace</h2>
            <div class="small">Browse tasks and apply. Requesters can add tasks.</div>
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <input id="searchInput" placeholder="Search tasks" style="width:220px" value="<?php echo htmlspecialchars($search); ?>">
            <select id="catFilter" style="width:140px">
              <option value="">All categories</option>
              <?php foreach($categories as $c): $sel = ($c === $categoryFilter) ? 'selected' : ''; echo "<option $sel>".htmlspecialchars($c)."</option>"; endforeach; ?>
            </select>
            <button class="btn" onclick="doSearch()">Search</button>
          </div>
        </div>

        <div style="margin-top:14px">
          <?php if (!$tasks) echo "<p class='muted'>No tasks available.</p>"; ?>
          <?php foreach($tasks as $t): ?>
            <div class="task-row">
              <div style="flex:1;min-width:0">
                <a href="#" style="font-weight:700;color:#0ea5a4;text-decoration:none" onclick="openTask(<?php echo $t['id']; ?>);return false"><?php echo htmlspecialchars($t['title']); ?></a>
                <div class="small"><?php echo htmlspecialchars(mb_substr($t['description'],0,160)); ?>...</div>
                <div class="small">By <?php echo htmlspecialchars($t['requester_name']); ?> • <?php echo htmlspecialchars($t['category']); ?></div>
              </div>
              <div style="text-align:right;margin-left:12px">
                <div style="font-weight:800;font-size:18px">$<?php echo number_format($t['payment'],2); ?></div>
                <div class="small" style="margin-top:6px"><?php echo $t['slots_filled']; ?>/<?php echo $t['total_slots']; ?> slots</div>
                <div style="margin-top:8px">
                  <?php if ($user && $user['role']=='worker'): ?>
                    <!-- Worker actions -->
                    <button class="btn-ghost" onclick="applyTask(<?php echo $t['id']; ?>)">Apply</button>
                    <button class="btn" onclick="openTask(<?php echo $t['id']; ?>)">Open</button>
                  <?php else: ?>
                    <button class="btn" onclick="openTask(<?php echo $t['id']; ?>)">Open</button>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div style="height:12px"></div>

      <div class="card" id="taskPanel" style="display:none"></div>

      <div style="height:12px"></div>

      <div class="card" id="myPanel">
        <?php if ($user): ?>
          <?php if ($user['role'] === 'worker'): ?>
            <h3>Your tasks & activity</h3>
            <?php
              $apps = $pdo->prepare("SELECT a.*, t.title, t.payment, u.name AS requester_name FROM applications a JOIN tasks t ON a.task_id=t.id JOIN users u ON t.requester_id=u.id WHERE a.worker_id=? ORDER BY a.applied_at DESC");
              $apps->execute([$user['id']]); $apps = $apps->fetchAll();
            ?>
            <?php if (!$apps) echo "<p class='muted'>No activity yet. Apply to tasks from the marketplace.</p>"; else { ?>
              <?php foreach($apps as $a): ?>
                <div style="padding:10px;border-bottom:1px solid #f1f5f9">
                  <div style="display:flex;justify-content:space-between">
                    <div>
                      <strong><?php echo htmlspecialchars($a['title']); ?></strong>
                      <div class="small">Requester: <?php echo htmlspecialchars($a['requester_name']); ?></div>
                    </div>
                    <div style="text-align:right">
                      <div class="small"><?php echo $a['status']; ?></div>
                      <div style="font-weight:700">$<?php echo number_format($a['payment'],2); ?></div>
                    </div>
                  </div>
                  <div style="margin-top:8px">
                    <?php if ($a['status']=='accepted'): ?>
                      <button class="btn" onclick="openTask(<?php echo $a['task_id']; ?>)">Submit Work</button>
                    <?php elseif ($a['status']=='submitted'): ?>
                      <span class="pill">Submitted — awaiting review</span>
                    <?php elseif ($a['status']=='approved'): ?>
                      <span class="pill" style="background:#ecfdf5;color:#065f46">Approved — Paid</span>
                    <?php else: ?>
                      <span class="small">Status: <?php echo $a['status']; ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php } ?>
          <?php else: // requester ?>
            <h3>Your posted tasks</h3>
            <?php
              $tasksReq = $pdo->prepare("SELECT t.*, (SELECT COUNT(*) FROM applications a WHERE a.task_id=t.id) as apps FROM tasks t WHERE t.requester_id=? ORDER BY t.created_at DESC");
              $tasksReq->execute([$user['id']]); $tasksReq = $tasksReq->fetchAll();
            ?>
            <?php if (!$tasksReq) echo "<p class='muted'>You haven't posted any tasks. Use the form on the right to post one.</p>"; else { ?>
              <?php foreach($tasksReq as $tr): ?>
                <div style="padding:10px;border-bottom:1px solid #f1f5f9">
                  <div style="display:flex;justify-content:space-between">
                    <div>
                      <strong><?php echo htmlspecialchars($tr['title']); ?></strong>
                      <div class="small"><?php echo htmlspecialchars($tr['category']); ?> • <?php echo $tr['slots_filled']; ?>/<?php echo $tr['total_slots']; ?> slots • $<?php echo number_format($tr['payment'],2); ?></div>
                    </div>
                    <div style="text-align:right">
                      <div class="small">Applications: <?php echo $tr['apps']; ?></div>
                      <a href="#" onclick="openTask(<?php echo $tr['id']; ?>);return false" class="small">Manage</a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php } ?>
          <?php endif; ?>
        <?php else: ?>
          <h3>Get started</h3>
          <p class="muted">Login or sign up to apply for tasks (as worker) or post tasks (as requester).</p>
        <?php endif; ?>
      </div>
    </main>

    <aside>
      <div class="card">
        <h3>Post a Task</h3>
        <div class="small">Only Requesters can post. If you're a worker, sign up as requester or switch account.</div>
        <div style="margin-top:10px">
          <label>Title</label>
          <input id="taskTitle" placeholder="Task title">
          <label>Description</label>
          <textarea id="taskDesc" rows="4" placeholder="Task details"></textarea>
          <label>Category</label>
          <select id="taskCat"><?php foreach($categories as $c) echo "<option>".htmlspecialchars($c)."</option>"; ?></select>
          <label>Payment (USD)</label>
          <input id="taskPay" type="number" step="0.01" value="0.50">
          <label>Slots</label>
          <input id="taskSlots" type="number" value="1" min="1">
          <label style="display:flex;align-items:center;gap:8px;margin-top:8px"><input type="checkbox" id="taskAuto"> Auto approve (auto-complete & pay when worker submits)</label>
          <div style="margin-top:10px">
            <button class="btn" onclick="postTask()">Post Task</button>
          </div>
          <div id="postMsg" class="small muted" style="margin-top:10px"></div>
        </div>
      </div>

      <div style="height:12px"></div>

      <div class="card">
        <h3>Your account</h3>
        <?php if ($user): ?>
          <div style="font-weight:700;font-size:20px">$<?php echo number_format($user['balance'],2); ?></div>
          <div class="small" style="margin-top:8px">Name: <?php echo htmlspecialchars($user['name']); ?></div>
          <div class="small">Role: <?php echo $user['role']; ?></div>
        <?php else: ?>
          <div class="small">Not logged in</div>
        <?php endif; ?>
        <div style="margin-top:8px">
          <button class="btn-ghost" onclick="showPanel('login')">Login</button>
          <button class="btn" onclick="showPanel('register')">Sign up</button>
        </div>
      </div>

      <div style="height:12px"></div>

      <div class="card">
        <h3>Categories</h3>
        <ul>
          <?php foreach($categories as $c) echo "<li class='small'>".htmlspecialchars($c)."</li>"; ?>
        </ul>
      </div>
    </aside>
  </div>
</div>

<!-- Login/Register panels (modal-like) -->
<div id="overlay" style="display:none;position:fixed;inset:0;background:rgba(2,6,23,0.45);z-index:99"></div>
<div id="panel" style="display:none;position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:100;max-width:520px;width:96%">
  <div class="card" id="panelContent"></div>
</div>

<script>
/* ---------- Client-side JS: uses fetch to call JSON endpoints at top of this same file ---------- */
async function api(action, data = {}) {
  data.action = action;
  const res = await fetch(location.href, { method: 'POST', body: JSON.stringify(data) });
  return res.json();
}

function showPanel(which){
  const overlay = document.getElementById('overlay');
  const panel = document.getElementById('panel');
  const content = document.getElementById('panelContent');
  overlay.style.display='block'; panel.style.display='block';
  if (which === 'login') {
    content.innerHTML = `
      <h3>Login</h3>
      <label>Email</label><input id="loginEmail" placeholder="Email">
      <label>Password</label><input id="loginPass" type="password" placeholder="Password">
      <div style="margin-top:10px"><button class="btn" onclick="doLogin()">Login</button> <button class="btn-ghost" onclick="closePanel()">Cancel</button></div>
      <div id="loginMsg" class="small muted" style="margin-top:8px"></div>
    `;
  } else {
    content.innerHTML = `
      <h3>Sign up</h3>
      <label>Name</label><input id="regName" placeholder="Your name">
      <label>Email</label><input id="regEmail" placeholder="Email">
      <label>Password</label><input id="regPass" type="password" placeholder="Password">
      <label>Role</label>
      <select id="regRole"><option value="worker">Worker</option><option value="requester">Requester</option></select>
      <div style="margin-top:10px"><button class="btn" onclick="doRegister()">Create account</button> <button class="btn-ghost" onclick="closePanel()">Cancel</button></div>
      <div id="regMsg" class="small muted" style="margin-top:8px"></div>
    `;
  }
}
function closePanel(){ document.getElementById('overlay').style.display='none'; document.getElementById('panel').style.display='none'; }

async function doRegister(){
  const name = document.getElementById('regName').value.trim();
  const email = document.getElementById('regEmail').value.trim();
  const pass = document.getElementById('regPass').value;
  const role = document.getElementById('regRole').value;
  const msg = document.getElementById('regMsg');
  msg.textContent = 'Creating...';
  const res = await api('register', { name, email, password: pass, role });
  if (res.ok) { msg.textContent = res.message; window.location.reload(); } else { msg.textContent = res.message; }
}

async function doLogin(){
  const email = document.getElementById('loginEmail').value.trim();
  const pass = document.getElementById('loginPass').value;
  const msg = document.getElementById('loginMsg');
  msg.textContent = 'Logging in...';
  const res = await api('login', { email, password: pass });
  if (res.ok) { msg.textContent = res.message; window.location.reload(); } else { msg.textContent = res.message; }
}

async function logout(){
  await api('logout', {});
  window.location.reload();
}

function doSearch(){
  const q = encodeURIComponent(document.getElementById('searchInput').value.trim());
  const cat = encodeURIComponent(document.getElementById('catFilter').value);
  let url = location.pathname + '?';
  if (q) url += 'q=' + q + '&';
  if (cat) url += 'category=' + cat + '&';
  location.href = url;
}

/* Open task detail and allow apply/submit/approve */
async function openTask(id){
  const panel = document.getElementById('taskPanel');
  panel.style.display = 'block';
  // fetch minimal HTML from server via GET params (we will generate inline using DOM operations)
  // but we already have full information server-rendered; instead call an endpoint? For simplicity, reload page with anchor-like behaviour:
  // We'll open a mini-overlay built by fetching task info via a quick fetch to server by using a tiny query param
  const res = await fetch(location.pathname + '?_task=' + id);
  const txt = await res.text();
  // The server will ignore _task param normally; however we can instead open a window...
  // Simpler approach: navigate to page with task id param (this current file can parse it on load). We'll use location.hash approach:
  location.href = location.pathname + '?q=' + encodeURIComponent(document.getElementById('searchInput').value || '') + '&_open=' + id;
}

/* When page loaded, if _open param present, display task inline */
(async function handleOpenOnLoad(){
  const params = new URLSearchParams(location.search);
  if (params.has('_open')) {
    const id = params.get('_open');
    // fetch task details via a simple GET endpoint implemented below: we'll call this same file with ?task_detail=ID to return JSON or HTML
    // But because page is single file, we will request a small fragment: ?task_json=ID
    const r = await fetch(location.href.split('?')[0] + '?task_json=' + id);
    if (r.ok) {
      const data = await r.json();
      if (data.ok) showTaskPanel(data.task, data.app, data.appsForTask);
    }
    // remove _open param without reload
    params.delete('_open');
    const base = location.pathname + (Array.from(params).length ? ('?' + params.toString()) : '');
    window.history.replaceState({}, '', base);
  }
})();

function showTaskPanel(task, myApp, allApps){
  const el = document.getElementById('taskPanel');
  el.style.display = 'block';
  el.innerHTML = `
    <h3>${escapeHtml(task.title)}</h3>
    <div class="small">By ${escapeHtml(task.requester_name)} • ${escapeHtml(task.category)} • $${parseFloat(task.payment).toFixed(2)}</div>
    <p style="margin-top:12px;white-space:pre-line">${escapeHtml(task.description)}</p>
    <div class="small">Slots: ${task.slots_filled}/${task.total_slots} • Status: ${task.status} • Auto approve: ${task.auto_approve==1 ? 'Yes' : 'No'}</div>
    <div style="margin-top:12px" id="taskActions"></div>
    <div style="margin-top:12px"><a href="#" onclick="closeTaskPanel();return false">Close</a></div>
  `;
  const actions = document.getElementById('taskActions');
  if (!task.logged_in) {
    actions.innerHTML = '<div class="small">Please login to apply or submit.</div>';
    showPanel('login');
    return;
  }
  if (task.amIRequester) {
    // show applications
    let html = '<h4>Applications</h4>';
    if (allApps.length === 0) html += '<div class="small">No applications yet.</div>';
    else {
      html += '<div style="max-height:220px;overflow:auto">';
      allApps.forEach(a=>{
        html += `<div style="padding:8px;border-bottom:1px solid #f1f5f9">
          <div style="display:flex;justify-content:space-between">
            <div><strong>${escapeHtml(a.worker_name)}</strong> <div class="small">${escapeHtml(a.status)}</div></div>
            <div style="text-align:right">
              ${a.status === 'submitted' ? `<button class="btn" onclick="approveApp(${a.id})">Approve</button>` : ''}
            </div>
          </div>
          <div class="small" style="margin-top:6px">${escapeHtml(a.submission_text || '')}</div>
        </div>`;
      });
      html += '</div>';
    }
    actions.innerHTML = html;
  } else {
    if (myApp) {
      if (myApp.status === 'accepted') {
        actions.innerHTML = `<div><label>Submission</label><textarea id="subText" rows="4"></textarea><div style="margin-top:8px"><button class="btn" onclick="submitWork(${task.id})">Submit</button></div></div>`;
      } else if (myApp.status === 'submitted') {
        actions.innerHTML = '<div class="pill">Submitted — awaiting review</div>';
      } else if (myApp.status === 'approved') {
        actions.innerHTML = '<div class="pill" style="background:#ecfdf5;color:#065f46">Approved — Paid</div>';
      } else {
        actions.innerHTML = `<div class="small">Status: ${myApp.status}</div>`;
      }
    } else {
      if (task.slots_filled >= task.total_slots) actions.innerHTML = '<div class="small">All slots filled.</div>';
      else actions.innerHTML = `<button class="btn" onclick="applyTask(${task.id})">Apply & Accept</button>`;
    }
  }
}

function closeTaskPanel(){ document.getElementById('taskPanel').style.display='none'; document.getElementById('taskPanel').innerHTML=''; }

/* Escape helper */
function escapeHtml(s){ if(!s) return ''; return s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }

/* Apply, submit, approve, postTask functions */
async function applyTask(id) {
  const res = await api('apply', { task_id: id });
  alert(res.message || (res.ok ? 'Applied' : 'Error'));
  if (res.ok) window.location.reload();
}

async function submitWork(task_id) {
  const text = document.getElementById('subText').value.trim();
  if (!text) { alert('Enter submission'); return; }
  const res = await api('submit', { task_id: task_id, text: text });
  alert(res.message || (res.ok ? 'Submitted' : 'Error'));
  if (res.ok) window.location.reload();
}

async function approveApp(application_id) {
  if (!confirm('Approve and pay worker?')) return;
  const res = await api('approve', { application_id: application_id });
  alert(res.message || (res.ok ? 'Approved' : 'Error'));
  if (res.ok) window.location.reload();
}

async function postTask() {
  const title = document.getElementById('taskTitle').value.trim();
  const desc = document.getElementById('taskDesc').value.trim();
  const cat = document.getElementById('taskCat').value;
  const pay = parseFloat(document.getElementById('taskPay').value) || 0;
  const slots = parseInt(document.getElementById('taskSlots').value) || 1;
  const auto = document.getElementById('taskAuto').checked ? 1 : 0;
  const msg = document.getElementById('postMsg'); msg.textContent = 'Posting...';
  const res = await api('post_task', { title, description: desc, category: cat, payment: pay, slots: slots, auto });
  msg.textContent = res.message || (res.ok ? 'Posted' : 'Error');
  if (res.ok) window.location.reload();
}

/* Utility: If server provides ?task_json=ID return JSON describing task etc. We'll implement it server-side below. */
</script>

<?php
/* ----- Server-side: if GET param task_json is present, return JSON about task (used by JS openTask) ----- */
if (isset($_GET['task_json'])) {
    $tid = intval($_GET['task_json']);
    $st = $pdo->prepare("SELECT t.*, u.name as requester_name FROM tasks t JOIN users u ON t.requester_id=u.id WHERE t.id=?");
    $st->execute([$tid]); $task = $st->fetch();
    if (!$task) { header('Content-Type: application/json'); echo json_encode(['ok'=>false]); exit; }
    $me = current_user($pdo);
    $amIRequester = $me && $me['id'] == $task['requester_id'];
    $myApp = null;
    if ($me && $me['role'] == 'worker') {
        $q = $pdo->prepare("SELECT a.*, u.name as worker_name FROM applications a JOIN users u ON a.worker_id=u.id WHERE a.task_id=? AND a.worker_id=?");
        $q->execute([$tid, $me['id']]); $myApp = $q->fetch();
    }
    // fetch all apps if requester
    $appsForTask = [];
    if ($amIRequester) {
        $qa = $pdo->prepare("SELECT a.*, u.name as worker_name FROM applications a JOIN users u ON a.worker_id=u.id WHERE a.task_id=? ORDER BY a.applied_at DESC");
        $qa->execute([$tid]); $appsForTask = $qa->fetchAll();
    }
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'task'=>$task, 'app'=>$myApp, 'appsForTask'=>$appsForTask, 'logged_in'=>!!$me, 'amIRequester'=>$amIRequester]);
    exit;
}

/* ----- normal HTML end ----- */
?>

</body>
</html>
