if ($action === 'submit') {
    if ($user['role'] !== 'worker') { echo json_encode(['ok'=>false,'message'=>'Only workers can submit']); exit; }
    $text = trim($json['text'] ?? '');
    if (!$text) { echo json_encode(['ok'=>false,'message'=>'Submission text required']); exit; }
    // find application
    $stmt = $pdo->prepare("SELECT a.*, t.auto_approve, t.payment FROM applications a JOIN tasks t ON a.task_id=t.id WHERE a.task_id=? AND a.worker_id=?");
    $stmt->execute([$task_id, $user['id']]);
    $app = $stmt->fetch();
    if (!$app) { echo json_encode(['ok'=>false,'message'=>'You have not applied to this task']); exit; }
    if ($app['status'] !== 'accepted') { echo json_encode(['ok'=>false,'message'=>'Cannot submit in this state: '.$app['status']]); exit; }

    // update application to submitted
    $stmt = $pdo->prepare("UPDATE applications SET status='submitted', submitted_at=NOW(), submission_text=? WHERE id=?");
    $stmt->execute([$text, $app['id']]);
    // mark task status in_review unless auto_approve
    if ($app['auto_approve']) {
        // auto approve flow
        try {
            $pdo->beginTransaction();
            // update application approved
            $pdo->prepare("UPDATE applications SET status='approved' WHERE id=?")->execute([$app['id']]);
            // credit worker
            $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?")->execute([$app['payment'], $app['worker_id']]);
            // transaction record
            $pdo->prepare("INSERT INTO transactions (user_id,amount,type,note) VALUES (?,?,?,?)")->execute([$app['worker_id'],$app['payment'],'credit',"Auto payment for task #".$app['task_id']]);
            // update task slots_filled and possibly status
            $pdo->prepare("UPDATE tasks SET slots_filled = slots_filled + 0 WHERE id = ?")->execute([$app['task_id']]); // slots already incremented on apply; keep consistent
            $pdo->commit();
            echo json_encode(['ok'=>true,'message'=>'Submitted and auto-approved. Payment credited.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok'=>false,'message'=>'Error during auto-approve: '.$e->getMessage()]);
        }
    } else {
        // normal flow: set task in_review
        $pdo->prepare("UPDATE tasks SET status='in_review' WHERE id=?")->execute([$task_id]);
        echo json_encode(['ok'=>true,'message'=>'Submitted. Requester will review and approve/reject.']);
    }
    exit;
}
