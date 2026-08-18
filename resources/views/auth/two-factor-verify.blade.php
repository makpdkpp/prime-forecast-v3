<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ยืนยันตัวตน 2FA | Prime Forecast V3</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
        }

        .verify-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .card-verify {
            width: 100%;
            max-width: 500px;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden;
        }

        .verify-header {
            background: linear-gradient(135deg, #d32f2f 0%, #b71c1c 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .verify-header .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .verify-header h3 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .verify-header p {
            margin: 5px 0 0 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .verify-body {
            padding: 40px 30px;
        }

        .email-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 25px;
            text-align: center;
        }

        .email-info p {
            margin: 0;
            font-size: 14px;
            color: #666;
        }

        .email-info strong {
            color: #d32f2f;
            font-size: 16px;
        }

        .otp-input-container {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 25px 0;
        }

        .otp-input {
            flex: 1;
            min-width: 36px;
            max-width: 55px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        @media (max-width: 360px) {
            .otp-input {
                height: 48px;
                font-size: 18px;
            }
        }

        .otp-input:focus {
            border-color: #d32f2f;
            outline: none;
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.1);
        }

        .timer-container {
            text-align: center;
            margin: 20px 0;
            font-size: 14px;
            color: #666;
        }

        .timer {
            font-size: 18px;
            font-weight: bold;
            color: #d32f2f;
        }

        .btn-verify {
            background-color: #d32f2f;
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 12px;
            font-size: 16px;
            border-radius: 6px;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-verify:hover {
            background-color: #b71c1c;
        }

        .btn-resend {
            background-color: #6c757d;
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 10px;
            font-size: 14px;
            border-radius: 6px;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        .btn-resend:hover {
            background-color: #5a6268;
        }

        .btn-resend:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        .help-text {
            text-align: center;
            font-size: 13px;
            color: #999;
            margin-top: 20px;
        }

        .help-text a {
            color: #d32f2f;
            text-decoration: none;
        }

        .help-text a:hover {
            text-decoration: underline;
        }

        .alert {
            border-radius: 6px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="verify-wrapper">
    <div class="card-verify">
        <div class="verify-header">
            <div class="icon">🔐</div>
            <h3>ยืนยันตัวตน 2FA</h3>
            <p>Two-Factor Authentication</p>
        </div>
        
        <div class="verify-body">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show">
                    {{ $errors->first() }}
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                </div>
            @endif

            <div class="email-info">
                <p>เราได้ส่งรหัส OTP ไปยัง</p>
                <strong>{{ $user->masked_email }}</strong>
            </div>

            <form method="POST" action="{{ route('2fa.verify.submit') }}" id="verifyForm">
                @csrf
                <div class="text-center mb-3">
                    <label style="font-weight: 600; color: #333;">กรุณากรอกรหัส 6 หลัก</label>
                </div>

                <div class="otp-input-container">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="0">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="1">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="2">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="3">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="4">
                    <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" autocomplete="off" data-index="5">
                </div>

                <input type="hidden" name="code" id="codeInput">

                <div class="timer-container">
                    <p>รหัสจะหมดอายุใน: <span class="timer" id="timer">5:00</span></p>
                </div>

                <button type="submit" class="btn btn-verify">
                    <i class="fas fa-check-circle"></i> ยืนยัน
                </button>
            </form>

            <form method="POST" action="{{ route('2fa.resend') }}" id="resendForm">
                @csrf
                <button type="submit" class="btn btn-resend" id="resendBtn">
                    <i class="fas fa-redo"></i> ส่งรหัสใหม่
                </button>
            </form>

            <div class="help-text">
                <p>ไม่ได้รับรหัส? ตรวจสอบ <strong>Spam folder</strong></p>
                <p><a href="{{ route('login') }}">← กลับไปหน้า Login</a></p>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.otp-input');
    const form = document.getElementById('verifyForm');
    const codeInput = document.getElementById('codeInput');
    const resendBtn = document.getElementById('resendBtn');
    
    // Auto-focus first input
    inputs[0].focus();
    
    // Handle input
    inputs.forEach((input, index) => {
        input.addEventListener('input', function(e) {
            const value = this.value;
            
            // Only allow numbers
            if (!/^\d*$/.test(value)) {
                this.value = '';
                return;
            }
            
            // Move to next input
            if (value && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
            
            // Update hidden input
            updateCode();
        });
        
        // Handle backspace
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !this.value && index > 0) {
                inputs[index - 1].focus();
            }
        });
        
        // Handle paste
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pastedData = e.clipboardData.getData('text').trim();
            
            if (/^\d{6}$/.test(pastedData)) {
                pastedData.split('').forEach((char, i) => {
                    if (inputs[i]) {
                        inputs[i].value = char;
                    }
                });
                inputs[5].focus();
                updateCode();
            }
        });
    });
    
    // Update hidden code input
    function updateCode() {
        const code = Array.from(inputs).map(input => input.value).join('');
        codeInput.value = code;
        
        // Auto-submit when all 6 digits entered
        if (code.length === 6) {
            setTimeout(() => {
                form.submit();
            }, 300);
        }
    }
    
    // Timer countdown (5 minutes)
    let timeLeft = 300; // 5 minutes in seconds
    const timerElement = document.getElementById('timer');
    
    const countdown = setInterval(() => {
        timeLeft--;
        
        const minutes = Math.floor(timeLeft / 60);
        const seconds = timeLeft % 60;
        
        timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        if (timeLeft <= 0) {
            clearInterval(countdown);
            timerElement.textContent = 'หมดอายุ';
            timerElement.style.color = '#dc3545';
            
            // Disable inputs
            inputs.forEach(input => input.disabled = true);
            
            alert('รหัส OTP หมดอายุแล้ว กรุณาขอรหัสใหม่');
        }
    }, 1000);
    
    // Resend button cooldown
    let resendCooldown = 0;
    
    resendBtn.addEventListener('click', function(e) {
        if (resendCooldown > 0) {
            e.preventDefault();
            return;
        }
        
        // Start cooldown (60 seconds)
        resendCooldown = 60;
        resendBtn.disabled = true;
        
        const originalText = resendBtn.innerHTML;
        
        const cooldownInterval = setInterval(() => {
            resendCooldown--;
            resendBtn.innerHTML = `<i class="fas fa-clock"></i> รอ ${resendCooldown} วินาที`;
            
            if (resendCooldown <= 0) {
                clearInterval(cooldownInterval);
                resendBtn.disabled = false;
                resendBtn.innerHTML = originalText;
            }
        }, 1000);
    });
});
</script>
</body>
</html>
