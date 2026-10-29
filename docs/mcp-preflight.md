# MCP preflight ก่อนขึ้น production

## วิธีใช้

รันจาก root โปรเจกต์ด้วย PHP CLI ที่ติดตั้ง pdo_sqlite และ dom พร้อม Composer dependencies:

```powershell
php scripts/mcp-preflight.php
```

เครื่องมือรัน PHPUnit เฉพาะ MCP gateway, OAuth และ sales drafts ใช้ SQLite `:memory:`
และข้าม config cache เดิม ไม่ใช้คำสั่ง migrate:fresh หรือเชื่อมฐานข้อมูล Demo/production
รายงานอยู่ใน `outputs/mcp-preflight/<run-id>/` ซึ่งถูก ignore จาก Git แล้ว

เปิด `report.html` ในโฟลเดอร์ผลทดสอบด้วย browser ได้โดยตรง ไม่ต้องเปิดเว็บแอป
รายการ UAT เป็นรายการที่ต้องตรวจและไม่ได้บันทึกผลในรายงาน

- `report.json`: เวลา UTC, Run ID, จำนวน test/assertion และผลผ่าน/ไม่ผ่าน
- `junit.xml`: สำหรับ CI และรายละเอียด testcase
- `test-output.txt`, `test-errors.txt`: diagnostic ภายในเครื่อง ให้ตรวจและลบข้อมูลลับก่อนส่งต่อ

Exit code 0 หมายถึง automated checks ผ่านเท่านั้น; 1 หมายถึงไม่ผ่านหรือรันไม่ได้
Skipped, incomplete, risky และ warning ทำให้ไม่ผ่าน ไม่ใช้ผลนี้อนุมัติ release อัตโนมัติ
ตัวทดสอบไม่เรียก MCP server ภายนอกและไม่ทดสอบ Node transport จึงต้องทำ Demo acceptance ต่อ

## สิ่งที่ตรวจอัตโนมัติ

| กลุ่ม | สิ่งที่ตรวจจากชุดทดสอบปัจจุบัน |
|---|---|
| Gateway | Service key, health/readiness, token scope/expiry, ขอบเขต Sales/Team Admin/Admin, ช่วงวันที่ |
| OAuth | Metadata, redirect URI, PKCE metadata, token response, browser consent สำหรับ write scope |
| Sales | Draft ยังไม่สร้าง project, ยืนยันผ่านเว็บ, ส่งซ้ำได้ project เดิม, เปลี่ยน payload ด้วย key เดิมถูกปฏิเสธ |
| Access | ข้ามทีม/ปลอม owner/อ่าน draft ของคนอื่นไม่ได้, read-only และ wildcard token เขียนไม่ได้ |
| Validation | จำนวนเงิน, catalog, timeline, draft หมดอายุ, revoke token และถูกถอดจากทีม |
| Audit | ไม่มี contact email ใน payload ที่เก็บแบบ plaintext/audit ของกรณีทดสอบ, audit ล้มเหลวต้อง rollback |
| Browser | ต้อง login ผ่านเว็บ, checkbox ยืนยัน และ CSRF |

## Demo acceptance ผ่าน MCP จริง

ทำเฉพาะระบบ Demo ที่ได้รับอนุญาต ใช้ข้อมูลสังเคราะห์ เช่น `MCP-UAT-<run-id>` และ tester ที่อยู่ allowlist
ต้องใช้บัญชีอย่างน้อย Sales A/B ต่างทีม, Team Admin และ Admin
บันทึกแต่ละข้อเป็น `PASS/FAIL/PENDING` พร้อมเวลา ผู้ทดสอบ build/commit ของ Laravel และ Node
และหลักฐานที่ไม่เปิดเผย secret ผล local ไม่ทดแทนหลักฐานเหล่านี้

| ID | วิธีทดสอบ | เกณฑ์ผ่าน / หลักฐาน |
|---|---|---|
| UAT-01 | เปิด /healthz และ /readyz ของ MCP Demo | healthy/ready; บันทึกเวลาและ build |
| UAT-02 | เชื่อม OAuth จาก client จริง แล้ว tools/list ในแต่ละ role | รายการ tools ตรงสิทธิ์และ flags ของ build; ไม่ยึดจำนวน 4 จาก checklist เก่า |
| UAT-03 | อ่าน forecast ตนเอง/ทีม/บริษัทและลองข้ามทีม | ยอดตรง Dashboard ในช่วงวันที่เดียวกัน; ข้ามสิทธิ์ถูกปฏิเสธ |
| UAT-04 | lookup catalog แล้ว prepare draft ด้วย Sales A | pending_confirmation และยังไม่มี project ใน Dashboard |
| UAT-05 | เจ้าของเปิด confirmation URL และกดยืนยันด้วยตนเอง | created พร้อม project_id; Dashboard มีหนึ่งรายการ ค่า/owner/ทีม/ขั้นตอนตรง draft |
| UAT-06 | ส่ง prepare เดิมด้วย key เดิมและยืนยันซ้ำ | draft/project เดิม จำนวน project ไม่เพิ่ม |
| UAT-07 | key เดิมแต่เปลี่ยน payload; token อ่านอย่างเดียว; ปลอม owner/ทีม | ถูกปฏิเสธ ไม่มี project เพิ่ม |
| UAT-08 | Sales B พยายามอ่าน/ยืนยัน draft ของ Sales A | ถูกปฏิเสธและไม่มีข้อมูล draft รั่วไหล |
| UAT-09 | draft หมดอายุ, revoke/expire token, ถอดสมาชิกทีมก่อนยืนยัน | ยืนยันไม่ได้ ไม่มี project เพิ่ม |
| UAT-10 | บน Demo MySQL/InnoDB ส่งการยืนยัน draft เดียวกันพร้อมกันสอง session ของเจ้าของที่มี CSRF ถูกต้อง | ทั้งสองคำขออ้าง project เดียวกัน; DB นับได้หนึ่ง project; แนบเวลาและ project_id |
| UAT-11 | ตรวจ audit allowed/denied/created และ export เฉพาะช่วง run | เชื่อมเหตุการณ์ด้วย IDs/เวลาได้ ไม่มี token, key, cookie หรือ contact data |
| UAT-12 | ปิด write flags ทั้ง Node/Laravel แล้วทดสอบ draft ใหม่และ pending draft | เขียนไม่ได้; read ยังทำงาน; บันทึกขั้นตอน rollback และ revoke pilot token |

ผู้ช่วยให้ confirmation URL แก่ผู้ใช้เท่านั้น ผู้ใช้เป็นคนตรวจและกดยืนยันเอง
ไม่ลบโครงการทดสอบอัตโนมัติ ให้เจ้าของระบบกำหนดการจัดการข้อมูล UAT และ retention ของ draft/audit
การทดสอบพร้อมกันและการปิด flag ควรใช้ช่วงเวลาที่ตกลงกับทีม Demo

## Release gate

สถานะเริ่มต้นเป็น **NOT_APPROVED** แม้ automated checks ผ่าน
อนุมัติได้เมื่อทุกข้อ UAT ผ่านบน build ที่จะ deploy, มีหลักฐาน MySQL concurrency,
audit/rollback และผู้รับผิดชอบลงนาม พร้อมตรวจ backup, migrations และ configuration ของ deployment
กรณีเปลี่ยน build/config ให้ประเมินและรันทดสอบที่เกี่ยวข้องซ้ำ

**ข้อจำกัดปัจจุบัน:** `McpSalesCreateAccess::enabled()` อนุญาตเฉพาะ staging/testing
production ถูกปิดแม้เปิด flag แล้ว ฝั่ง Node ก็มีข้อจำกัด Demo ตามเอกสาร phase 2
ก่อนเปิด sales write ใน production ต้องออกแบบและ review policy ทั้งสองฝั่งแยกต่างหาก
เครื่องมือนี้ไม่แก้ policy ไม่ deploy และไม่เปลี่ยน flags ให้

แบบบันทึกหลักฐานต่อข้อ:

```text
Run ID / Laravel commit / Node commit:
Demo URL / เวลาและ timezone:
Test ID / Role / ผู้ทดสอบ:
ผล: PASS | FAIL | PENDING
สิ่งที่คาดหวัง / สิ่งที่เกิดขึ้นจริง:
draft_id / project_id / audit IDs (ถ้ามี):
ที่เก็บหลักฐานที่ลบข้อมูลลับแล้ว:
Release owner / การตัดสินใจ / เวลา:
```
