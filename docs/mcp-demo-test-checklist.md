# Prime Forecast MCP Demo Test Checklist

ทดสอบที่ `https://mcp-demo.primes.co.th` เท่านั้น และบันทึกวันเวลา บัญชี/Role และ Test ID ทุกครั้ง ห้ามส่ง Token, Service Key, Cookie หรือรหัสผ่านมากับผลทดสอบ

## ก่อนเริ่ม

- ยืนยัน `/healthz` เป็น `ok`
- ยืนยัน `/readyz` เป็น `ready`
- เปิดหน้า `https://demo.primes.co.th/admin/mcp-audit-logs` ด้วยบัญชี Admin
- จดเวลาเริ่มและเวลาสิ้นสุดการทดสอบ

## Test Cases

| ID | บัญชี | ขั้นตอน | ผลที่คาดหวัง |
|---|---|---|---|
| MCP-01 | ไม่ต้อง Login | เปิด `/healthz` และ `/readyz` | `ok` และ `ready` |
| MCP-02 | ทุก Role | เชื่อม ChatGPT Developer Mode และ OAuth | Authorize สำเร็จ |
| MCP-03 | Admin | เรียก `tools/list` | เห็น 4 tools |
| MCP-04 | Admin | เรียก `get_company_forecast` | สำเร็จและยอดตรง Dashboard |
| MCP-05 | Team Admin | อ่าน Team/Sales ในทีม | สำเร็จ |
| MCP-06 | Team Admin | อ่าน Team/Sales นอกทีม | ถูกปฏิเสธ `403` |
| MCP-07 | Sales | เรียก `get_my_forecast` | เห็นเฉพาะข้อมูลตนเอง |
| MCP-08 | Sales | เรียก Team/Company forecast | ไม่เห็น tool หรือถูกปฏิเสธ `403` |
| MCP-09 | ทุก Role | ทดสอบช่วงวันที่และ pagination | จำนวนและยอดตรง Dashboard |
| MCP-10 | ทุก Role | Revoke Token แล้วเรียกซ้ำ | ถูกปฏิเสธทันที |
| MCP-11 | Admin | ตรวจ Audit Log | มี allowed/denied และไม่มี secret |
| MCP-12 | Admin | Export JSON ตามช่วงเวลาทดสอบ | ดาวน์โหลดสำเร็จและปลอดข้อมูลลับ |

## ข้อมูลที่ส่งกลับ

1. Test ID และผล `ผ่าน/ไม่ผ่าน`
2. เวลาที่ทดสอบและ Role ที่ใช้
3. ข้อความ error หรือ screenshot หากไม่ผ่าน
4. ไฟล์ JSON จากหน้า MCP Audit Logs โดยกรองเฉพาะช่วงเวลาทดสอบ

ห้ามคัดลอก Bearer Token, `X-Prime-MCP-Key`, session cookie หรือค่า Environment Variables ลงในรายงาน
