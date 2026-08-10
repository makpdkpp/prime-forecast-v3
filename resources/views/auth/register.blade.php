<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ตั้งรหัสผ่าน | PrimeForecast</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .register-box {
            width: 400px;
            margin: 7% auto;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .card-header {
            background-color: #0056b3;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            text-align: center;
            padding: 20px;
        }
        .btn-primary {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .btn-primary:hover {
            background-color: #004494;
            border-color: #004494;
        }
        .user-info {
            background-color: #f0f8ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #0056b3;
        }
    </style>
</head>
<body class="hold-transition register-page">
<div class="register-box">
    <div class="card card-outline">
        <div class="card-header">
            <h1 class="h3 mb-0"><b>Prime</b>Forecast</h1>
            <p class="mb-0">ตั้งรหัสผ่านของคุณ</p>
        </div>
        <div class="card-body">
            <div class="user-info">
                <p class="mb-1"><strong>ยินดีต้อนรับ</strong></p>
                <p class="mb-1">{{ $user->nname }} {{ $user->surename }}</p>
                <p class="mb-0 text-muted"><small>{{ $user->email }}</small></p>
            </div>

            <p class="text-center">กรุณาตั้งรหัสผ่านเพื่อเข้าใช้งานระบบ</p>

            <form action="{{ route('register.submit', $token) }}" method="post">
                @csrf
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" placeholder="รหัสผ่าน (อย่างน้อย 12 ตัวอักษร)" minlength="12" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                    @error('password')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password_confirmation" class="form-control" placeholder="ยืนยันรหัสผ่าน" required>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">ตั้งรหัสผ่านและเข้าสู่ระบบ</button>
                    </div>
                </div>
            </form>

            <p class="mt-3 mb-1 text-center">
                <a href="{{ route('login') }}" class="text-muted">กลับไปหน้าเข้าสู่ระบบ</a>
            </p>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>
