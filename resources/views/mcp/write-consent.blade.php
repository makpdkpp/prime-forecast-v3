<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>อนุญาตเพิ่มโครงการ Demo</title></head>
<body><main style="font-family:system-ui;max-width:640px;margin:40px auto;padding:24px">
<h1>อนุญาตให้ {{ $clientName }} เตรียมโครงการขาย</h1>
<p>บัญชีนี้จะให้สิทธิ์อ่านข้อมูลตามบทบาท และสร้างร่างโครงการของตัวเองใน Demo แต่ละโครงการต้องเปิดหน้าตรวจสอบและกดยืนยันบนเว็บก่อนบันทึก</p>
<form method="post" action="{{ route('mcp.oauth.write-consent') }}">@csrf
<input type="hidden" name="nonce" value="{{ $nonce }}">
<label><input type="checkbox" name="confirm" value="1" required> ยินยอมให้สิทธิ์อ่านและเตรียมโครงการขาย</label>
<p><button type="submit">อนุญาตและเชื่อมต่อ</button></p></form>
<p>หากไม่ต้องการให้สิทธิ์ ให้ปิดหน้านี้</p></main></body></html>
