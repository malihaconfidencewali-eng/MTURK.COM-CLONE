<?php
// seed_demo.php
require 'db.php';

// Demo credentials (you can change)
$demo_requester = ['name'=>'Demo Requester','email'=>'alice@demo.com','password'=>'password123','role'=>'requester'];
$demo_worker    = ['name'=>'Demo Worker','email'=>'bob@demo.com','password'=>'password123','role'=>'worker'];

function ensure_user($pdo, $u){
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$u['email']]);
    $row = $stmt->fetch();
    if($row) return $row['id'];
    $hash = password_hash($u['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name,email,password,role,balance) VALUES (?,?,?,?,?)");
    $stmt->execute([$u['name'],$u['email'],$hash,$u['role'], ($u['role']=='requester'?100.00:5.00)]);
    return $pdo->lastInsertId();
}

$rid = ensure_user($pdo, $demo_requester);
$wid = ensure_user($pdo, $demo_worker);

// Insert default tasks only if none exist
$stmt = $pdo->query("SELECT COUNT(*) as c FROM tasks");
$count = $stmt->fetchColumn();
if($count > 0){
    echo "Tasks already present. Seeder skipped.\n";
    exit;
}

$tasks = [
    ['title'=>'Quick survey — 5 questions','description'=>'Answer 5 short questions about shopping habits.','category'=>'Surveys','payment'=>0.50,'slots'=>10,'auto_approve'=>1],
    ['title'=>'Image tags — 10 images','description'=>'Label objects in provided images.','category'=>'Image Labeling','payment'=>1.00,'slots'=>5,'auto_approve'=>0],
    ['title'=>'Transcribe 30 sec audio','description'=>'Transcribe short audio into text.','category'=>'Transcription','payment'=>0.80,'slots'=>8,'auto_approve'=>0],
    ['title'=>'Data entry — copy rows','description'=>'Copy 50 rows from provided CSV to form.','category'=>'Data Entry','payment'=>1.50,'slots'=>3,'auto_approve'=>1],
    ['title'=>'Opinion poll — 3 Q','description'=>'Three yes/no questions about a product.','category'=>'Surveys','payment'=>0.30,'slots'=>20,'auto_approve'=>1],
];

$stmt = $pdo->prepare("INSERT INTO tasks (requester_id,title,description,category,payment,total_slots,auto_approve) VALUES (?,?,?,?,?,?,?)");
foreach($tasks as $t){
    $stmt->execute([$rid, $t['title'], $t['description'], $t['category'], $t['payment'], $t['slots'], $t['auto_approve']]);
}

echo "Seeder finished: created demo users and default tasks.\n";
