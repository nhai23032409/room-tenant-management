<?php
// Run from cron/Task Scheduler every 10–30 minutes. Never expose this script
// through the web server; it deliberately runs without a browser session.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once dirname(__DIR__) . '/includes/config.php';

$pdo->beginTransaction();
try {
    $stmt = $pdo->query("SELECT b.id, t.id AS tenant_id FROM beds b LEFT JOIN tenants t ON t.bed_id = b.id AND t.status = 'pending' WHERE b.status = 'deposited' AND b.deposit_expires_at < NOW() FOR UPDATE");
    $expired = $stmt->fetchAll();
    foreach ($expired as $row) {
        $pdo->prepare("UPDATE beds SET status = 'available', deposit_expires_at = NULL WHERE id = ?")->execute([$row['id']]);
        if ($row['tenant_id']) {
            $pdo->prepare("UPDATE tenants SET bed_id = NULL, status = 'inactive' WHERE id = ?")->execute([$row['tenant_id']]);
            $pdo->prepare("UPDATE payments SET notes = CONCAT(COALESCE(notes, ''), ' | Expired') WHERE tenant_id = ? AND payment_type = 'security_deposit' AND method = 'pending'")->execute([$row['tenant_id']]);
        }
    }
    $pdo->commit();
    echo 'Released ' . count($expired) . " expired reservation(s).\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "Failed to release expired reservations.\n");
    exit(1);
}
