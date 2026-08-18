<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ลืมรหัสผ่าน | Prime Forecast V3</title>
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

        .back-link {
            font-size: 14px;
            color: #d32f2f;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card-box">
        <div class="title">ลืมรหัสผ่าน</div>
        <div class="subtitle">กรอกอีเมลของคุณเพื่อรับลิงก์สำหรับรีเซ็ตรหัสผ่าน</div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="form-group">
                <label for="email">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    class="form-control @error('email') is-invalid @enderror"
                    value="{{ old('email') }}"
                    placeholder="JohnDoe@primes.co.th"
                    required
                    autofocus
                >
                @error('email')
                    <span class="invalid-feedback" role="alert">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary-custom btn-block">ส่งลิงก์รีเซ็ตรหัสผ่าน</button>
        </form>

        <div class="mt-3 text-center">
            <a href="{{ route('login') }}" class="back-link">กลับไปหน้าเข้าสู่ระบบ</a>
        </div>
    </div>
</div>
</body>
</html>
