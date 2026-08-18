<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>กำลังเตรียม Dashboard | Prime Forecast V3</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            font-family: 'Inter', sans-serif;
        }
        .loading-card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
            padding: 28px 24px;
            text-align: center;
        }
        .brand {
            color: #d32f2f;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .progress {
            height: 8px;
            border-radius: 999px;
            background-color: #e2e8f0;
            overflow: hidden;
        }
        .progress-bar {
            width: 0;
            animation: fillProgress 1.2s ease forwards;
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }
        @keyframes fillProgress {
            from { width: 0; }
            to { width: 100%; }
        }
        .status {
            margin-top: 14px;
            font-size: 14px;
            color: #334155;
        }
    </style>
</head>
<body>
    <div class="loading-card">
        <div class="brand">Prime Forecast V3</div>
        <h5 class="mb-2">กำลังเตรียม Dashboard</h5>
        <p class="subtitle mb-3">กำลังโหลดข้อมูลเริ่มต้นของคุณ...</p>
        <div class="progress">
            <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="status" id="statusText">กำลังตรวจสอบสิทธิ์และเตรียมข้อมูล</div>
    </div>

    <script>
        const targetUrl = @json($targetUrl);
        const statusText = document.getElementById('statusText');

        setTimeout(() => {
            statusText.textContent = 'กำลังเข้าสู่หน้า Dashboard...';
        }, 500);

        setTimeout(() => {
            window.location.replace(targetUrl);
        }, 900);
    </script>
</body>
</html>
