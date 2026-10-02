<!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>ยืนยันโครงการขาย | Prime Forecast Demo</title>
<style>body{font-family:system-ui,sans-serif;max-width:800px;margin:32px auto;padding:20px;color:#17283a;background:#f5f7fa}main{background:white;padding:24px;border-radius:12px}table{border-collapse:collapse;width:100%}td,th{text-align:left;padding:10px;border-bottom:1px solid #ddd;overflow-wrap:anywhere}button{padding:12px 20px;background:#1558a5;color:white;border:0;border-radius:6px;cursor:pointer}.notice{padding:16px;background:#e9f2ff}form{margin-top:24px}label{display:block;margin-bottom:16px}</style></head>
<body><main><h1>ตรวจสอบโครงการขายใหม่</h1><p>Demo • เจ้าของโครงการ: {{ $user->nname }} {{ $user->surename }} ({{ $user->user_id }})</p>
@if($draft['status'] === 'created')
<p class="notice">บันทึกสำเร็จแล้ว เลขโครงการ {{ $draft['project_id'] }} กลับไปแจ้งผู้ช่วยให้ตรวจสถานะร่างได้เลย</p>
@elseif($draft['status'] === 'expired')
<p class="notice">ร่างหมดอายุแล้ว กรุณาให้ผู้ช่วยสร้างร่างใหม่</p>
@else
<p class="notice">ยังไม่ได้เพิ่มโครงการ กรุณาตรวจสอบข้อมูลด้านล่างก่อนกดบันทึก หากต้องการแก้ไข ให้กลับไปขอร่างใหม่จากผู้ช่วย</p>
@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
<table>
@foreach(['Product_detail'=>'ชื่อโครงการ','company_id'=>'ลูกค้า','Product_id'=>'กลุ่มสินค้า','product_value'=>'มูลค่า (บาท)','Source_budget_id'=>'แหล่งงบประมาณ','fiscalyear'=>'ปีงบประมาณ (ค.ศ.)','team_id'=>'ทีม','priority_id'=>'ความสำคัญ','contact_start_date'=>'วันเริ่มติดต่อ','date_of_closing_of_sale'=>'วัน Bidding','sales_can_be_close'=>'วันที่คาดว่าจะปิดการขาย','remark'=>'หมายเหตุ','contact_person'=>'ผู้ติดต่อ','contact_phone'=>'โทรศัพท์','contact_email'=>'อีเมล','contact_note'=>'หมายเหตุผู้ติดต่อ'] as $key => $title)
<tr><th>{{ $title }}</th><td>{{ $labels[$key] ?? ($payload[$key] ?? '—') }} @if(isset($labels[$key])) (ID {{ $payload[$key] }}) @endif</td></tr>
@endforeach
@foreach($payload['steps'] as $step)<tr><th>{{ $steps[$step['level_id']] ?? $step['level_id'] }}</th><td>{{ $step['date'] }}</td></tr>@endforeach
</table>
@if($draft['status'] === 'pending_confirmation')
<form method="post" action="{{ route('mcp.sales-drafts.store', $draft['draft_id']) }}">@csrf
<label><input type="checkbox" name="confirm" value="1" required> ฉันตรวจสอบและยืนยันให้เพิ่มโครงการนี้ใน Demo</label>
<button type="submit">ยืนยันบันทึกโครงการ</button></form>
@endif
</main></body></html>
