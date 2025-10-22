<?php
require 'db.php';
$err = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) $err = "Email & password required.";
    else {
        $stmt = $pdo->prepare("SELECT id,password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if (!$u || !password_verify($password, $u['password'])) $err = "Invalid credentials.";
        else {
            $_SESSION['user_id']=$u['id'];
            echo "<script>window.location='marketplace.php'</script>";
            exit;
        }
    }
}
?>
<!doctype html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - MicroTasks Hub</title>
<style>
 body{font-family:Inter,Segoe UI,Arial;background:#fff7ed;margin:0}
 .wrap{max-width:420px;margin:40px auto;padding:20px}
 .card{background:white;padding:20px;border-radius:12px;box-shadow:0 8px 30px rgba(2,6,23,0.06)}
 input{width:100%;padding:10px;margin:8px 0;border-radius:8px;border:1px solid #eee}
 button{background:#0ea5a4;color:white;padding:10px;border:0;border-radius:8px}
 .err{color:#b91c1c}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>Login</h2>
    <?php if($err) echo "<div class='err'>$err</div>"; ?>
    <form method="post" onsubmit="this.querySelector('button').disabled=true">
      <input name="email" type="email" placeholder="Email" required>
      <input name="password" type="password" placeholder="Password" required>
      <div style="margin-top:8px"><button type="submit">Login</button></div>
    </form>
    <p style="margin-top:12px">New? <a href="register.php">Register</a></p>
  </div>
</div>
</body>
</html>
