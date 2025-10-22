<?php
require 'db.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $role = ($_POST['role'] === 'requester') ? 'requester' : 'worker';
    $password = $_POST['password'] ?? '';
    if (!$name || !$email || !$password) $errors[] = "All fields required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email.";
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) $errors[] = "Email already registered.";
        else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)");
            $stmt->execute([$name,$email,$hash,$role]);
            // Auto-login
            $uid = $pdo->lastInsertId();
            $_SESSION['user_id'] = $uid;
            echo "<script>window.location='marketplace.php'</script>";
            exit;
        }
    }
}
?>
<!doctype html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register - MicroTasks Hub</title>
<style>
 body{font-family:Inter,Segoe UI,Arial;background:#eef2ff;margin:0;padding:0}
 .wrap{max-width:420px;margin:36px auto;padding:20px}
 .card{background:white;padding:20px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.08)}
 input,select{width:100%;padding:10px;margin:8px 0;border-radius:8px;border:1px solid #e6e9ef}
 button{background:#7c3aed;color:white;padding:10px 12px;border:0;border-radius:8px;cursor:pointer}
 .muted{color:#6b7280;font-size:14px}
 .errors{color:#b91c1c}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>Register</h2>
    <?php if($errors): ?><div class="errors"><?php echo implode('<br>',$errors); ?></div><?php endif; ?>
    <form method="post" onsubmit="document.querySelector('button').disabled=true">
      <label>Name</label>
      <input name="name" required>
      <label>Email</label>
      <input name="email" type="email" required>
      <label>Role</label>
      <select name="role"><option value="worker">Worker</option><option value="requester">Requester</option></select>
      <label>Password</label>
      <input name="password" type="password" required>
      <div style="margin-top:8px"><button type="submit">Create account</button></div>
    </form>
    <p class="muted">Already registered? <a href="login.php">Login</a></p>
  </div>
</div>
</body>
</html>
