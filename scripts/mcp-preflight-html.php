<?php

function renderMcpPreflightHtml(array $report): string
{
    $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    $passed = ($report['automated_status'] ?? '') === 'passed';
    try {
        $date = (new DateTimeImmutable($report['finished_at']))->setTimezone(new DateTimeZone('Asia/Bangkok'))->format('d/m/Y H:i:s');
    } catch (Throwable $e) {
        $date = 'ไม่ทราบเวลา';
    }
    $uat = [
        'Health และ readiness', 'OAuth และ tools ตามสิทธิ์', 'อ่าน forecast และตรวจสิทธิ์ข้ามทีม',
        'เตรียม draft โดยยังไม่สร้างโครงการ', 'เจ้าของยืนยันและตรวจโครงการ', 'ส่งซ้ำแล้วไม่สร้างข้อมูลซ้ำ',
        'ปฏิเสธข้อมูลหรือสิทธิ์ที่ไม่ถูกต้อง', 'ป้องกันผู้ใช้คนอื่นเข้าถึง draft',
        'หมดอายุ ถอน token และออกจากทีม', 'ยืนยันพร้อมกันบน MySQL',
        'ตรวจ audit และข้อมูลอ่อนไหว', 'ปิด write flags และทดสอบ rollback',
    ];
    ob_start();
    ?>
<!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MCP Preflight Report</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#152438;font:16px/1.6 system-ui,'Segoe UI',sans-serif}.wrap{max-width:1120px;margin:auto;padding:42px 24px}header{margin-bottom:28px}.eyebrow{color:#3661a4;font-weight:700;letter-spacing:.09em;font-size:12px}h1{font-size:clamp(28px,4vw,40px);margin:5px 0}.muted{color:#64748b}.banner,.panel,.metric{background:white;border:1px solid #e0e7ef;border-radius:14px;padding:22px;margin-bottom:18px}.banner{border-left:5px solid #1b9466;display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}.banner.fail{border-color:#d04a49}.status{font-weight:700;color:#08734c}.fail .status{color:#b93131}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:14px}.metric{margin:0 0 18px}.number{font-size:32px;font-weight:750;line-height:1.2}.label{color:#64748b;font-size:14px}.warning{background:#fff8e8;border-color:#f2d89d}.tag{font-size:12px;color:#8d5b00;background:#fff0c2;padding:4px 9px;border-radius:20px;font-weight:700}h2{font-size:20px;margin:0 0 10px}p{margin:8px 0}table{border-collapse:collapse;width:100%}th,td{text-align:left;padding:10px;border-bottom:1px solid #eef1f5}th{font-size:13px;color:#64748b}td:first-child{white-space:nowrap;font-weight:700;color:#3661a4}.scroll{overflow-x:auto}code{overflow-wrap:anywhere}.note{font-size:14px;color:#64748b}footer{font-size:13px;color:#64748b} @media print{body{background:white}.wrap{padding:0}.panel,.banner,.metric{break-inside:avoid}}
</style></head><body><main class="wrap">
<header><div class="eyebrow">PRIME FORECAST · TEST REPORT</div><h1>MCP Preflight Report</h1><p class="muted">สรุปผลทดสอบบนเครื่องก่อนเตรียมใช้งาน production</p></header>
<section class="banner <?= $passed ? '' : 'fail' ?>"><div><div class="label">ผลตรวจอัตโนมัติ</div><div class="status"><?= $passed ? '● ผ่าน' : '● ไม่ผ่าน' ?></div></div><div><div class="label">รอบการทดสอบ</div><code><?= $escape($report['run_id'] ?? '—') ?></code></div><div><div class="label">ทดสอบเสร็จ · เวลาไทย</div><?= $escape($date) ?> น.</div></section>
<div class="grid"><?php foreach (['tests'=>'การทดสอบ','assertions'=>'Assertions','failures'=>'ล้มเหลว','errors'=>'ข้อผิดพลาด','skipped'=>'ข้าม'] as $key => $label): ?><div class="metric"><div class="label"><?= $escape($label) ?></div><div class="number"><?= isset($summary[$key]) && is_numeric($summary[$key]) ? number_format((int) $summary[$key]) : '—' ?></div></div><?php endforeach ?></div>
<?php if (! empty($report['error'])): ?><section class="panel"><strong>ข้อผิดพลาด:</strong> <?= $escape($report['error']) ?></section><?php endif ?>
<section class="panel warning"><h2>ความพร้อมก่อน production <span class="tag">ยังไม่อนุมัติ</span></h2><p>ผลข้างต้นทดสอบ Laravel ด้วย SQLite ในหน่วยความจำ ยังต้องตรวจ MCP จริงและ MySQL บน Demo</p><p>การสร้างข้อมูลฝ่ายขายบน production ยังถูกปิดตามนโยบายปัจจุบัน ต้องทบทวนนโยบายก่อนเปิดใช้งาน</p></section>
<section class="panel"><h2>รายการตรวจบน Demo</h2><p class="note">รายการต่อไปนี้ยังไม่ได้บันทึกผลในรายงานนี้ ดูขั้นตอนและเกณฑ์ผ่านฉบับเต็มใน docs/mcp-preflight.md</p><div class="scroll"><table><thead><tr><th>รหัส</th><th>รายการ</th><th>สถานะ</th></tr></thead><tbody><?php foreach ($uat as $index => $label): ?><tr><td>UAT-<?= sprintf('%02d', $index + 1) ?></td><td><?= $escape($label) ?></td><td>ต้องตรวจ</td></tr><?php endforeach ?></tbody></table></div></section>
<footer>รายงานรอบเดียว ณ เวลาที่ระบุ · รายละเอียดผลทดสอบอยู่ใน report.json และ junit.xml ในโฟลเดอร์เดียวกัน</footer>
</main></body></html>
    <?php
    return (string) ob_get_clean();
}
