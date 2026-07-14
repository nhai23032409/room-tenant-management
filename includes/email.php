<?php
// includes/email.php - Email notification functions
function send_email($to, $subject, $message, $is_html = true) {
    // Simple mail function - for production use PHPMailer or similar
    $headers = [];
    if ($is_html) {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=utf-8';
    }
    $headers[] = 'From: noreply@homestaydorm.com';
    
    return mail($to, $subject, $message, implode("\r\n", $headers));
}

function send_sms($phone, $message) {
    // Placeholder for SMS integration
    // In production, integrate with Twilio, Nexmo, or local SMS provider
    error_log("SMS to $phone: $message");
    return true;
}

// Notification functions
function notify_deposit_created($tenant_id, $bed_id, $amount, $expires_at) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT t.name, t.email, t.phone, r.room_number, h.name as hostel_name FROM tenants t JOIN beds b ON t.bed_id = b.id JOIN rooms r ON b.room_id = r.id JOIN hostels h ON r.hostel_id = h.id WHERE t.id = ?");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
    
    $subject = "Yêu cầu đặt cọc phòng - $tenant[hostel_name]";
    $message = "
        <h3>Chào $tenant[name],</h3>
        <p>Bạn đã đặt cọc phòng $tenant[room_number] tại $tenant[hostel_name] thành công!</p>
        <p><strong>Số tiền cọc:</strong> ₹" . number_format($amount) . "</p>
        <p><strong>Hết hạn vào:</strong> $expires_at</p>
        <p>Vui lòng thanh toán trong vòng 24h để giữ chỗ.</p>
    ";
    
    if ($tenant['email']) {
        send_email($tenant['email'], $subject, $message);
    }
    if ($tenant['phone']) {
        send_sms($tenant['phone'], "Dat coc phong $tenant[room_number] thanh cong. Han thanh toan: $expires_at");
    }
}

function notify_checkout_processed($tenant_id, $refund_amount, $amount_to_pay) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT t.name, t.email, t.phone FROM tenants t WHERE t.id = ?");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();
    
    $subject = "Xử lý trả phòng - HomeStay Dorm";
    $message = "
        <h3>Chào $tenant[name],</h3>
        <p>Quá trình trả phòng của bạn đã được xử lý.</p>
        <p><strong>Số tiền hoàn cọc:</strong> ₹" . number_format($refund_amount) . "</p>
        <p><strong>Số tiền phải trả thêm:</strong> ₹" . number_format($amount_to_pay) . "</p>
    ";
    
    if ($tenant['email']) {
        send_email($tenant['email'], $subject, $message);
    }
}
?>