<?php
// signature-pad.php - Signature pad component for contract signing
session_start();
include('includes/config.php');
include('includes/permissions.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: mobile_app.php");
    exit;
}
require_permission('manage_contracts');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Contract</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .signature-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem auto;
            max-width: 600px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        #signatureCanvas {
            border: 2px solid #ddd;
            border-radius: 10px;
            cursor: crosshair;
            background: white;
        }
        .btn {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="signature-container">
            <h2 class="text-center mb-4">Ký hợp đồng</h2>
            
            <div class="mb-3">
                <label class="form-label">Chữ ký của bạn:</label>
                <canvas id="signatureCanvas" width="500" height="200"></canvas>
            </div>
            
            <div class="d-flex gap-2">
                <button class="btn btn-secondary flex-fill" onclick="clearSignature()">Xóa</button>
                <button class="btn btn-primary flex-fill" onclick="saveSignature()">Lưu chữ ký</button>
            </div>
            
            <form id="signatureForm" method="POST" action="api/contracts.php" style="display: none;">
                <input type="hidden" name="action" value="save_signature">
                <input type="hidden" name="contract_id" value="<?php echo $_GET['contract_id'] ?? 0; ?>">
                <input type="hidden" name="signature" id="signatureInput">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>">
            </form>
        </div>
    </div>
    
    <script>
        const canvas = document.getElementById('signatureCanvas');
        const ctx = canvas.getContext('2d');
        let isDrawing = false;
        
        // Set canvas background
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        // Drawing functions
        function startDrawing(e) {
            isDrawing = true;
            draw(e);
        }
        
        function stopDrawing() {
            isDrawing = false;
            ctx.beginPath();
        }
        
        function draw(e) {
            if (!isDrawing) return;
            
            const rect = canvas.getBoundingClientRect();
            const x = (e.clientX || e.touches[0].clientX) - rect.left;
            const y = (e.clientY || e.touches[0].clientY) - rect.top;
            
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000';
            
            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }
        
        // Event listeners
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseleave', stopDrawing);
        
        // Touch support
        canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            startDrawing(e);
        });
        canvas.addEventListener('touchend', stopDrawing);
        canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            draw(e);
        });
        
        function clearSignature() {
            ctx.fillStyle = 'white';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }
        
        function saveSignature() {
            const signatureData = canvas.toDataURL('image/png');
            document.getElementById('signatureInput').value = signatureData;
            const form = document.getElementById('signatureForm');
            fetch(form.action, { method: 'POST', body: new FormData(form) })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Không thể lưu chữ ký');
                    window.location.href = 'workflow.php';
                })
                .catch(error => alert(error.message));
        }
    </script>
</body>
</html>
