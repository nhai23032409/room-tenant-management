<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/permissions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: mobile_app.php');
    exit;
}

$hostelId = (int)($_SESSION['hostel_id'] ?? 0);
$role = $_SESSION['user_role'] ?? '';
function workflow_query(PDO $pdo, $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$leads = workflow_query($pdo, "SELECT t.id, t.name, t.phone FROM tenants t WHERE t.status IN ('pending', 'inactive') AND t.bed_id IS NULL ORDER BY t.created_at DESC", []);
$beds = workflow_query($pdo, "SELECT b.id, b.bed_number, r.room_number, r.monthly_rent FROM beds b JOIN rooms r ON r.id = b.room_id WHERE r.hostel_id = ? AND b.status = 'available' ORDER BY r.room_number, b.bed_number", [$hostelId]);
$pendingDeposits = workflow_query($pdo, "SELECT p.id AS payment_id, p.amount, t.id AS tenant_id, t.name, b.id AS bed_id, b.bed_number, r.room_number FROM payments p JOIN tenants t ON t.id = p.tenant_id JOIN beds b ON b.id = t.bed_id JOIN rooms r ON r.id = b.room_id WHERE r.hostel_id = ? AND p.payment_type = 'security_deposit' AND p.method = 'pending' AND b.status = 'deposited' ORDER BY p.id DESC", [$hostelId]);
$reserved = workflow_query($pdo, "SELECT t.id AS tenant_id, t.name, b.id AS bed_id, b.bed_number, r.room_number, r.monthly_rent, p.amount AS deposit FROM tenants t JOIN beds b ON b.id = t.bed_id JOIN rooms r ON r.id = b.room_id JOIN payments p ON p.tenant_id = t.id WHERE r.hostel_id = ? AND b.status = 'deposited' AND p.payment_type = 'security_deposit' AND p.method IN ('cash', 'bank_transfer') ORDER BY t.name", [$hostelId]);
$contracts = workflow_query($pdo, "SELECT c.id, c.tenant_id, c.start_date, c.end_date, c.signature_path, t.name, r.room_number FROM contracts c JOIN tenants t ON t.id = c.tenant_id JOIN rooms r ON r.id = c.room_id WHERE r.hostel_id = ? AND c.status = 'active' ORDER BY c.created_at DESC", [$hostelId]);
$activeTenants = workflow_query($pdo, "SELECT t.id, t.name, t.checkin_date, r.room_number, b.bed_number FROM tenants t JOIN beds b ON b.id = t.bed_id JOIN rooms r ON r.id = b.room_id WHERE r.hostel_id = ? AND t.status = 'active' ORDER BY t.name", [$hostelId]);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>HomeStay · Quy trình thuê phòng</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Quy trình thuê phòng</h1><p class="text-muted mb-0">Chi nhánh hiện tại · role: <?= htmlspecialchars($role) ?></p></div>
    <a class="btn btn-outline-secondary" href="mobile_app.php">Về dashboard</a>
  </div>
  <div id="notice"></div>

  <section class="card mb-4"><div class="card-header">1. Sale tạo yêu cầu cọc (một giường)</div><div class="card-body">
    <form class="row g-3 api-form" data-api="api/deposit.php" data-action="create_deposit_request">
      <div class="col-md-5"><label class="form-label">Lead</label><select class="form-select" name="tenant_id" required><option value="">Chọn khách</option><?php foreach ($leads as $lead): ?><option value="<?= $lead['id'] ?>"><?= htmlspecialchars($lead['name'] . ' · ' . $lead['phone']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-5"><label class="form-label">Giường trống</label><select class="form-select" name="bed_id" required><option value="">Chọn giường</option><?php foreach ($beds as $bed): ?><option value="<?= $bed['id'] ?>"><?= htmlspecialchars($bed['room_number'] . ' · giường ' . $bed['bed_number']) ?> (<?= number_format($bed['monthly_rent']) ?>đ/tháng)</option><?php endforeach; ?></select></div>
      <input type="hidden" name="bed_count" value="1"><div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Tạo cọc</button></div>
    </form>
  </div></section>

  <section class="card mb-4"><div class="card-header">2. Manager xác nhận cọc</div><div class="card-body">
    <?php if (!$pendingDeposits): ?><p class="text-muted mb-0">Không có cọc chờ xác nhận.</p><?php endif; ?>
    <?php foreach ($pendingDeposits as $item): ?><form class="row g-2 align-items-end border-bottom py-2 api-form" data-api="api/deposit.php" data-action="confirm_deposit">
      <div class="col-md-5"><strong><?= htmlspecialchars($item['name']) ?></strong><br><small><?= htmlspecialchars($item['room_number'] . ' · giường ' . $item['bed_number']) ?> · <?= number_format($item['amount']) ?>đ</small></div>
      <input type="hidden" name="bed_id" value="<?= $item['bed_id'] ?>"><input type="hidden" name="payment_id" value="<?= $item['payment_id'] ?>">
      <div class="col-md-4"><select class="form-select" name="method"><option value="cash">Tiền mặt</option><option value="bank_transfer">Chuyển khoản</option></select></div><div class="col-md-3"><button class="btn btn-success w-100">Xác nhận</button></div>
    </form><?php endforeach; ?>
  </div></section>

  <section class="card mb-4"><div class="card-header">3. Tạo hợp đồng và ký</div><div class="card-body">
    <form class="row g-3 api-form mb-3" data-api="api/contracts.php" data-action="generate_contract" data-next-signature="1">
      <div class="col-md-4"><label class="form-label">Khách đã cọc</label><select class="form-select contract-tenant" name="tenant_id" required><option value="">Chọn khách</option><?php foreach ($reserved as $item): ?><option value="<?= $item['tenant_id'] ?>" data-bed="<?= $item['bed_id'] ?>"><?= htmlspecialchars($item['name'] . ' · phòng ' . $item['room_number']) ?></option><?php endforeach; ?></select><input type="hidden" name="bed_id" class="contract-bed"></div>
      <div class="col-md-3"><label class="form-label">Ngày bắt đầu</label><input class="form-control" name="start_date" type="date" value="<?= date('Y-m-d') ?>" required></div>
      <div class="col-md-3"><label class="form-label">Ngày kết thúc</label><input class="form-control" name="end_date" type="date"></div>
      <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">Tạo & ký</button></div>
    </form>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Khách</th><th>Phòng</th><th>Thời hạn</th><th>Trạng thái</th><th></th></tr></thead><tbody><?php foreach ($contracts as $contract): ?><tr><td><?= htmlspecialchars($contract['name']) ?></td><td><?= htmlspecialchars($contract['room_number']) ?></td><td><?= htmlspecialchars($contract['start_date'] . ' → ' . ($contract['end_date'] ?: 'Không thời hạn')) ?></td><td><?= $contract['signature_path'] ? '<span class="badge text-bg-success">Đã ký</span>' : '<span class="badge text-bg-warning">Chờ ký</span>' ?></td><td><a class="btn btn-sm btn-outline-primary" href="signature-pad.php?contract_id=<?= $contract['id'] ?>">Ký</a> <a class="btn btn-sm btn-outline-secondary" href="generate-pdf-contract.php?contract_id=<?= $contract['id'] ?>">PDF</a></td></tr><?php endforeach; ?></tbody></table></div>
  </div></section>

  <section class="card mb-4"><div class="card-header">4. Accountant ghi nhận tiền thuê kỳ đầu</div><div class="card-body">
    <form class="row g-3 api-form" data-api="api/payments.php" data-action="record_payment">
      <div class="col-md-4"><label class="form-label">Khách</label><select class="form-select" name="tenant_id" required><option value="">Chọn khách</option><?php foreach ($reserved as $item): ?><option value="<?= $item['tenant_id'] ?>"><?= htmlspecialchars($item['name'] . ' · phòng ' . $item['room_number']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label">Số tiền</label><input class="form-control" type="number" min="1" name="amount" required></div><div class="col-md-2"><label class="form-label">Ngày thu</label><input class="form-control" type="date" name="date" value="<?= date('Y-m-d') ?>" required></div>
      <div class="col-md-2"><label class="form-label">Phương thức</label><select class="form-select" name="method"><option value="cash">Tiền mặt</option><option value="bank_transfer">Chuyển khoản</option></select></div><input type="hidden" name="payment_type" value="rent"><input type="hidden" name="notes" value="Thanh toán kỳ đầu">
      <div class="col-md-2 d-flex align-items-end"><button class="btn btn-success w-100">Ghi nhận</button></div>
    </form>
  </div></section>

  <section class="card mb-4"><div class="card-header">5. Bàn giao</div><div class="card-body"><div class="row g-2"><?php foreach ($contracts as $contract): if (!$contract['signature_path']) continue; ?><div class="col-md-4"><a class="btn btn-outline-primary w-100" href="admin/handover-room.php?tenant_id=<?= $contract['tenant_id'] ?>">Bàn giao <?= htmlspecialchars($contract['name']) ?></a></div><?php endforeach; ?></div></div></section>

  <section class="card"><div class="card-header">6. Đối soát và trả phòng</div><div class="card-body">
    <form id="checkout-form" class="row g-3"><div class="col-md-4"><label class="form-label">Khách đang ở</label><select class="form-select" name="tenant_id" required><option value="">Chọn khách</option><?php foreach ($activeTenants as $tenant): ?><option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name'] . ' · ' . $tenant['room_number'] . '/' . $tenant['bed_number']) ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Ngày trả</label><input class="form-control" name="checkout_date" type="date" value="<?= date('Y-m-d') ?>" required></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100" type="button" id="calculate-checkout">Tính</button></div><div class="col-md-3"><label class="form-label">Hoàn bằng</label><select class="form-select" name="refund_method"><option value="cash">Tiền mặt</option><option value="bank_transfer">Chuyển khoản</option></select></div><div class="col-12" id="checkout-result"></div><div class="col-12"><button class="btn btn-danger" id="process-checkout" type="button" disabled>Xác nhận đã đối soát & trả phòng</button></div></form>
  </div></section>
</main>
<script>
const csrfToken = <?= json_encode(csrf_token()) ?>;
const notice = (message, type = 'success') => document.querySelector('#notice').innerHTML = `<div class="alert alert-${type}">${message}</div>`;
async function callApi(url, data) { data.set('csrf_token', csrfToken); const response = await fetch(url, {method:'POST', body:data}); const result = await response.json(); if (!response.ok || !result.success) throw new Error(result.message || 'Yêu cầu thất bại'); return result; }
document.querySelectorAll('.api-form').forEach(form => form.addEventListener('submit', async event => { event.preventDefault(); const data = new FormData(form); data.set('action', form.dataset.action); try { const result = await callApi(form.dataset.api, data); if (form.dataset.nextSignature) { window.location.href = 'signature-pad.php?contract_id=' + result.contract_id; return; } notice(result.message || ('Hoàn tất. ' + (result.receipt_number ? 'Số phiếu: ' + result.receipt_number : ''))); setTimeout(() => location.reload(), 700); } catch (error) { notice(error.message, 'danger'); } }));
document.querySelector('.contract-tenant')?.addEventListener('change', event => document.querySelector('.contract-bed').value = event.target.selectedOptions[0]?.dataset.bed || '');
let checkoutCalculated = false;
document.querySelector('#calculate-checkout').addEventListener('click', async () => { const form = document.querySelector('#checkout-form'); const data = new FormData(form); data.set('action', 'calculate_checkout'); try { const r = await callApi('api/checkout.php', data); const c = r.calculation; document.querySelector('#checkout-result').innerHTML = `<div class="alert alert-info">Cọc: ${Number(c.deposit).toLocaleString()}đ · Khấu trừ: ${Number(c.total_deductions).toLocaleString()}đ · Hoàn: <strong>${Number(c.final_refund).toLocaleString()}đ</strong> · Thu thêm: <strong>${Number(c.amount_to_pay).toLocaleString()}đ</strong></div>`; checkoutCalculated = true; document.querySelector('#process-checkout').disabled = false; } catch (error) { notice(error.message, 'danger'); } });
document.querySelector('#process-checkout').addEventListener('click', async () => { if (!checkoutCalculated || !confirm('Xác nhận đã đối soát với khách và hoàn tất trả phòng?')) return; const data = new FormData(document.querySelector('#checkout-form')); data.set('action', 'process_checkout'); data.set('settlement_confirmed', '1'); try { await callApi('api/checkout.php', data); notice('Đã hoàn tất trả phòng.'); setTimeout(() => location.reload(), 700); } catch (error) { notice(error.message, 'danger'); } });
</script>
</body></html>
