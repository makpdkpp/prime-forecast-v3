<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รีเซ็ตรหัสผ่าน | Prime Forecast V3</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
        }

        .wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card-box {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 32px;
        }

        .title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 20px;
        }

        .btn-primary-custom {
            background-color: #d32f2f;
            border: none;
            color: #fff;
            font-weight: 600;
        }

        .btn-primary-custom:hover {
            background-color: #b71c1c;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card-box">
        <div class="title">รีเซ็ตรหัสผ่าน</div>
        <div class="subtitle">ตั้งรหัสผ่านใหม่สำหรับบัญชี {{ $email }}</div>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('password.update', $token) }}">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">

            <div class="form-group">
                <label for="password">รหัสผ่านใหม่</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    class="form-control @error('password') is-invalid @enderror"
                    placeholder="อย่างน้อย 12 ตัวอักษร"
                    minlength="12"
                    required
                >
                @error('password')
                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group">
                <label for="password_confirmation">ยืนยันรหัสผ่านใหม่</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    class="form-control"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary-custom btn-block">บันทึกรหัสผ่านใหม่</button>
        </form>
    </div>
</div>
</body>
</html>
