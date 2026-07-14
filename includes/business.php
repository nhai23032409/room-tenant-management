<?php
// business.php - Shared business rules (PRD BR-2, BR-6)

function calculate_deposit_amount($monthly_rent, $bed_count) {
    return (float)$monthly_rent * 2 * max(1, (int)$bed_count);
}

/**
 * BR-6 deposit refund rates
 */
function calculate_deposit_refund($deposit_amount, $checkout_date, $contract = null) {
    $deposit = (float)$deposit_amount;

    if (!$contract || empty($contract['signature_path'])) {
        return $deposit * 0.80;
    }

    $start = new DateTime($contract['start_date']);
    $checkout = new DateTime($checkout_date);

    if (!empty($contract['end_date'])) {
        $end = new DateTime($contract['end_date']);
        if ($checkout >= $end) {
            return $deposit;
        }
    }

    $months = ($start->diff($checkout)->y * 12) + $start->diff($checkout)->m;
    return $months < 6 ? $deposit * 0.50 : $deposit * 0.70;
}

function get_tenant_outstanding_debts($pdo, $tenant_id) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM debts WHERE tenant_id = ? AND type = 'rent' AND status = 'pending'");
    $stmt->execute([$tenant_id]);
    $outstanding_rent = (float)$stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM debts WHERE tenant_id = ? AND type = 'service' AND status = 'pending'");
    $stmt->execute([$tenant_id]);
    $outstanding_services = (float)$stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM debts WHERE tenant_id = ? AND type = 'damage' AND status = 'pending'");
    $stmt->execute([$tenant_id]);
    $damage_fees = (float)$stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM debts WHERE tenant_id = ? AND type = 'penalty' AND status = 'pending'");
    $stmt->execute([$tenant_id]);
    $penalty_fees = (float)$stmt->fetch()['total'];

    return compact('outstanding_rent', 'outstanding_services', 'damage_fees', 'penalty_fees');
}

function calculate_checkout_settlement($pdo, $tenant_id, $checkout_date, $contract = null) {
    $debts = get_tenant_outstanding_debts($pdo, $tenant_id);
    $deposit = $contract ? (float)$contract['deposit'] : 0.0;
    $refund_amount = calculate_deposit_refund($deposit, $checkout_date, $contract);
    $total_deductions = array_sum($debts);

    return array_merge($debts, [
        'deposit' => $deposit,
        'refund_amount' => $refund_amount,
        'total_deductions' => $total_deductions,
        'final_refund' => max(0, $refund_amount - $total_deductions),
        'amount_to_pay' => max(0, $total_deductions - $refund_amount),
    ]);
}

?>
