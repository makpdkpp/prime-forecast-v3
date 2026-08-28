# Prime Forecast MCP Integration Plan

เอกสารนี้เป็นแผนพัฒนาและนำ Prime Forecast MCP ระยะที่ 1 ไปทดสอบบน Demo ก่อนใช้งานจริงบน Production โดยระยะนี้อนุญาตเฉพาะการอ่านข้อมูล (Read-only) เท่านั้น

## 1. เป้าหมายและข้อจำกัด

- เชื่อม Prime Forecast V3 กับ MCP โดยให้ Laravel เป็น API Gateway และเป็นผู้ตัดสินสิทธิ์ขั้นสุดท้าย
- ทดสอบระบบทั้งหมดบน Demo ก่อนนำขึ้น Production
- รองรับสิทธิ์ 3 ระดับ: Sales, Team Admin และ Admin
- บันทึก Audit Log ทุกครั้งที่เรียกเครื่องมือหรืออ่านข้อมูลผ่าน MCP
- ห้ามแก้ไขข้อมูลธุรกิจจริงผ่าน MCP ในระยะที่ 1
- ห้ามเพิ่มหรือเปิดใช้ write tools จนกว่าจะผ่านการทดสอบสิทธิ์ครบถ้วนและได้รับการยืนยันแผนเป็นลายลักษณ์อักษร
- ห้ามใช้ token, service key, database หรือ environment variables ชุดเดียวกันระหว่าง Demo และ Production

## 2. สภาพแวดล้อม

| Environment | Laravel | MCP Server | วัตถุประสงค์ |
|---|---|---|---|
| Demo | `https://demo.primes.co.th` | `https://mcp-demo.primes.co.th/mcp` | ทดสอบ Permission, API Gateway และ End-to-end |
| Production | `https://sale.primes.co.th` | `https://mcp.primes.co.th/mcp` | เปิดใช้งานหลังผ่าน Acceptance Gate เท่านั้น |

ทั้งสองสภาพแวดล้อมต้องแยกฐานข้อมูล, Sanctum token, MCP service key, OAuth client และ Audit Log ออกจากกัน

## 3. สถานะปัจจุบัน

- [x] พัฒนา Node.js MCP Server ระยะที่ 1 แบบ Read-only แล้ว
- [x] Push MCP Server ไปที่ `https://github.com/makpdkpp/prime-forecast-mcp`
- [x] มีเครื่องมือ `get_my_forecast`, `list_team_forecasts`, `get_sales_forecast` และ `get_company_forecast`
- [x] จำกัดรายการเครื่องมือตาม Sales / Team Admin / Admin
- [x] Unit/integration test ของ MCP ผ่าน 17 รายการ
- [x] Deploy MCP Demo และตรวจสอบ `/healthz` สำเร็จ
- [x] เพิ่มหน้าออก Sanctum demo token บน branch `codex/mcp-demo-token`
- [x] Review และ merge หน้าออก demo token เข้าสู่ branch ที่ใช้ Deploy Demo
- [x] Laravel API Gateway สำหรับ MCP (Phase A–C foundation)
- [x] Laravel endpoint `/api/mcp/v1/health`
- [x] Laravel Permission Guard และ Read Repository
- [x] Laravel Audit Log endpoint และตาราง `mcp_audit_logs`
- [x] Laravel MCP Feature Test ผ่าน 12 tests / 65 assertions บน SQLite แบบแยกอิสระ
- [x] เพิ่มหน้า Admin ดู/กรอง Audit Log และ Export JSON แบบไม่ส่ง token, service key, IP หรือ cookie
- [x] End-to-end Permission Matrix ผ่านบน Demo วันที่ 2026-08-25
- [x] Deploy Audit/response hardening revision และทำ smoke test ซ้ำ
- [ ] Deploy OAuth 2.1 bridge และทดสอบ ChatGPT Developer mode บน Demo
- [ ] Production OAuth และ Production deployment

สถานะล่าสุดที่ผู้ใช้ยืนยันของ `https://mcp-demo.primes.co.th/readyz` คือ `ready` และ Admin `get_company_forecast` ตอบโครงสร้าง `items/summary/meta` สำเร็จบน Demo

## 4. Permission Matrix ระยะที่ 1

| Tool | Sales | Team Admin | Admin |
|---|:---:|:---:|:---:|
| `get_my_forecast` | เฉพาะตนเอง | ตนเอง | ตนเอง |
| `list_team_forecasts` | ไม่อนุญาต | เฉพาะทีมที่ได้รับมอบหมาย | ทุกทีม |
| `get_sales_forecast` | ไม่อนุญาต | เฉพาะ Sales ในทีมที่ได้รับมอบหมาย | ทุก Sales |
| `get_company_forecast` | ไม่อนุญาต | ไม่อนุญาต | อนุญาต |

หลักการสำคัญ:

- การซ่อน tool ที่ MCP Server เป็นเพียงด่านแรก
- Laravel ต้องตรวจ Sanctum ability, role, user status และ team scope ซ้ำทุก request
- ห้ามเชื่อ `role` หรือ `team_id` ที่ส่งมาจาก client หรือ MCP arguments
- Laravel ต้องคำนวณ team membership จากข้อมูลจริงในระบบ
- เมื่อไม่มีสิทธิ์ให้ตอบ `403` และบันทึก Audit Log โดยไม่เปิดเผยข้อมูลของบุคคลอื่น

## 5. งานที่ต้องพัฒนาเพิ่ม

### Phase A — Laravel Gateway Foundation

- [x] เพิ่ม MCP configuration ใน Laravel โดยอ่านค่าจาก Environment Variables
- [x] สร้าง middleware ตรวจ `X-Prime-MCP-Key` ด้วยการเปรียบเทียบแบบ constant-time
- [x] สร้าง `GET /api/mcp/v1/health` สำหรับตรวจ Laravel Gateway
- [x] สร้าง `POST /api/mcp/v1/auth/context` สำหรับตรวจ Sanctum token และส่งคืน principal ที่จำเป็นต่อ MCP
- [x] กำหนดให้ token ต้องมี ability `mcp:read`
- [x] ปฏิเสธ token ที่หมดอายุ ไม่มีวันหมดอายุ ถูก revoke ไม่มี ability หรือเป็นผู้ใช้ที่ไม่ active
- [x] แปลง role และ explicit permissions ของระบบในจุดเดียว
- [x] โหลด team scope จาก `transactional_team` โดยไม่รับ team scope จาก request
- [x] กำหนด error contract มาตรฐานสำหรับ `401`, `403`, `404`, `422`, `429` และ `503`
- [x] เพิ่ม rate limiting สำหรับ MCP API แยกจาก API ปกติ

ข้อเสนอ route protection:

| Endpoint | Service Key | Sanctum | `mcp:read` | Role/Scope Guard |
|---|:---:|:---:|:---:|:---:|
| `GET /api/mcp/v1/health` | ใช่ | ไม่ | ไม่ | ไม่ |
| `POST /api/mcp/v1/auth/context` | ใช่ | ใช่ | ใช่ | ตรวจ user status |
| Forecast read endpoints | ใช่ | ใช่ | ใช่ | ใช่ |
| `POST /api/mcp/v1/audit-events` | ใช่ | ไม่ | ไม่ | ตรวจ actor กับข้อมูลผู้ใช้จริง |

### Phase B — Read-only Forecast API

- [x] สร้าง `GET /api/mcp/v1/forecast/me`
- [x] สร้าง `GET /api/mcp/v1/teams/{teamId}/forecasts`
- [x] สร้าง `GET /api/mcp/v1/sales/{salesId}/forecast`
- [x] สร้าง `GET /api/mcp/v1/forecast/company`
- [x] สร้าง `POST /api/mcp/v1/audit-events` สำหรับรับเหตุการณ์จาก MCP Server
- [x] ใช้ Read Repository แยกจาก Dashboard เพื่อให้ scope และ field allowlist ชัดเจน
- [x] กำหนด field allowlist ไม่ส่งข้อมูลส่วนบุคคลหรือ field ภายในที่ไม่จำเป็น
- [x] ตรวจ `date_from` / `date_to` รูปแบบ `YYYY-MM-DD`, ปี/ไตรมาส และจำกัด page size สูงสุด 100 รายการ
- [x] ป้องกัน N+1 queries ด้วย latest-step subquery และ query เดียวต่อ summary/page
- [x] ยืนยันว่า endpoints ทั้งหมดใช้ HTTP methods แบบอ่านข้อมูล ยกเว้น audit endpoint ที่เขียนเฉพาะ Audit Log

### Phase C — Audit Log

สถานะ: เสร็จใน Laravel Gateway; ต้องตรวจหลักฐานจริงบน Demo หลัง Deploy

Audit record ขั้นต่ำที่บันทึก:

- `request_id` และ `trace_id`
- เวลาและ environment
- user ID, role และ token ID แบบไม่เก็บ token จริง
- tool name และ endpoint
- arguments ที่ผ่านการลบข้อมูลอ่อนไหวแล้ว
- team/sales scope ที่ร้องขอ
- ผลการอนุญาตหรือปฏิเสธ
- HTTP status, duration และจำนวนรายการที่ส่งคืน
- IP/User-Agent ของ MCP Server ตามที่เชื่อถือได้

ห้ามบันทึก bearer token, service key, password, session cookie หรือข้อมูล forecast ทั้งชุดลง Log

### Phase D — Automated Tests

- [ ] Unit test สำหรับ role mapping และ permission policy
- [x] Feature test สำหรับ Laravel MCP health, auth context, forecast และ audit endpoints
- [x] ทดสอบด้วยผู้ใช้อย่างน้อย 4 บัญชี: Admin, Team Admin ทีม A, Sales ทีม A และ Sales ทีม B
- [ ] ทดสอบ token ที่ไม่มี, ผิด, หมดอายุ, ถูก revoke และไม่มี `mcp:read`
- [x] ทดสอบ service key ที่ไม่มีหรือไม่ถูกต้อง
- [x] ทดสอบ Sales อ่านข้อมูลของ Sales คนอื่นไม่ได้
- [x] ทดสอบ Team Admin ข้ามทีมไม่ได้
- [x] ทดสอบ Team Admin ระบุ `teamId` หรือ `salesId` ปลอมแล้วต้องถูกปฏิเสธ
- [x] ทดสอบ Admin อ่านภาพรวมบริษัทได้
- [ ] ทดสอบ inactive user ถูกปฏิเสธ
- [ ] ทดสอบทุก allowed และ denied request มี Audit Log
- [ ] เปรียบเทียบยอดจาก API กับ Dashboard เดิมในช่วงวันเดียวกัน
- [ ] รัน MCP tests เดิมและเพิ่ม integration tests สำหรับ Laravel response contract

การทดสอบต้องใช้ Demo/Test database เท่านั้น ห้ามใช้ test ที่สร้าง แก้ไข หรือลบข้อมูลใน Production

### Phase E — End-to-end Demo Test

ลำดับตรวจสอบหลัง Deploy Laravel Gateway:

1. ตรวจ `https://mcp-demo.primes.co.th/healthz` ต้องเป็น `status: ok`
2. ตรวจ Laravel MCP health ด้วย service key ต้องตอบสำเร็จ
3. ตรวจ `https://mcp-demo.primes.co.th/readyz` ต้องเป็น `status: ready`
4. ออก Sanctum demo token จากหน้า Token Issuer
5. เรียก MCP `initialize`
6. เรียก `tools/list` แล้วตรวจรายการ tool ตาม role
7. เรียก tool ที่ได้รับอนุญาตและตรวจผลลัพธ์กับ Dashboard
8. พยายามเรียก tool ที่ไม่มีสิทธิ์โดยตรงและต้องได้ `403` หรือ MCP permission error
9. ตรวจ Audit Log ของทั้งกรณีอนุญาตและปฏิเสธ
10. Revoke token แล้วเรียกซ้ำ ต้องถูกปฏิเสธทันที

## 6. โครงสร้างไฟล์ Laravel ที่เสนอ

ชื่อไฟล์อาจปรับให้เข้ากับ convention ของโครงการ แต่ควรแยกหน้าที่ประมาณนี้:

```text
app/
  Contracts/McpPrincipalResolver.php
  Contracts/ForecastReadRepository.php
  Data/McpPrincipal.php
  Http/Controllers/Api/Mcp/
    AuthContextController.php
    ForecastController.php
    HealthController.php
    AuditEventController.php
  Http/Middleware/RequireMcpServiceKey.php
  Http/Requests/Api/Mcp/
  Policies/McpForecastPolicy.php
  Repositories/ForecastReadRepository.php
  Services/McpPrincipalResolver.php
config/services.php
routes/api.php
tests/Feature/Mcp/
tests/Unit/Mcp/
```

ควรเก็บ query logic ของ forecast ไว้ใน service/repository ที่ Dashboard และ MCP สามารถใช้ร่วมกันได้ เพื่อหลีกเลี่ยงการคำนวณตัวเลขคนละแบบ

## 7. Environment Variables ที่ต้องเตรียม

### Laravel Demo

```dotenv
APP_ENV=staging
APP_URL=https://demo.primes.co.th
PRIME_MCP_ENABLED=true
PRIME_MCP_DEMO_TOKEN_ISSUER_ENABLED=true
PRIME_MCP_SERVICE_KEY=<demo-random-secret>
PRIME_MCP_AUDIT_ENABLED=true
PRIME_MCP_OAUTH_ENABLED=true
PRIME_MCP_OAUTH_RESOURCE=https://mcp-demo.primes.co.th
PRIME_MCP_OAUTH_ISSUER=https://demo.primes.co.th
```

### MCP Demo

```dotenv
NODE_ENV=production
PORT=<plesk-assigned-port>
LARAVEL_BASE_URL=https://demo.primes.co.th
LARAVEL_MCP_SERVICE_TOKEN=<same-demo-random-secret>
ALLOWED_HOSTS=mcp-demo.primes.co.th
```

### Production

ใช้ตัวแปรชื่อเดียวกัน แต่ต้องเปลี่ยน URL และสร้าง secret ใหม่ทั้งหมด ห้ามคัดลอก Demo token หรือ Demo service key ไปใช้

ชื่อ Environment Variables จริงต้องตรวจให้ตรงกับ implementation ของทั้งสอง repository ก่อน Deploy

## 8. Deploy ผ่าน Plesk โดยไม่มี Terminal

### Laravel Demo

- [ ] Push code ไปยัง GitHub branch ที่ใช้สำหรับ Demo
- [ ] Pull/Deploy branch ผ่าน Plesk Git
- [ ] ตั้ง Environment Variables ของ Demo
- [ ] ใช้ Plesk Composer action เพื่อติดตั้ง production dependencies หากมีการเปลี่ยน dependency
- [ ] ใช้ Laravel Toolkit หรือ scheduled one-time task ที่ได้รับอนุญาตเพื่อ clear/cache configuration หาก Plesk รองรับ
- [ ] ตรวจ permissions ของ `storage` และ `bootstrap/cache`
- [ ] Smoke test หน้าเว็บเดิมก่อนทดสอบ MCP

### MCP Demo

- [ ] ตั้ง Node.js version `24.19.0`
- [ ] ตั้ง Application Root ไปยัง repository MCP
- [ ] ตั้ง Startup File เป็น `app.js`
- [ ] กด `NPM Install`
- [ ] ตั้ง Environment Variables ของ Demo
- [ ] Restart Node.js application
- [ ] ตรวจ `/healthz` และ `/readyz`

หากเปลี่ยน Environment Variables ต้อง restart Node.js application และตรวจ readiness ใหม่ทุกครั้ง

## 9. Production Authentication และ Cutover

Sanctum demo token เหมาะสำหรับการทดสอบแบบควบคุมบน Demo แต่ก่อนเปิดใช้งานกับ client ภายนอกหรือ ChatGPT ใน Production ต้องออกแบบ OAuth 2.1 ให้ครบถ้วน โดยอย่างน้อยควรมี:

- Authorization Code flow พร้อม PKCE
- Protected Resource Metadata ของ MCP Server
- Authorization Server Metadata
- Client registration ตาม client ที่จะใช้งานจริง
- scopes ที่แยกสิทธิ์อ่าน เช่น `mcp:read`
- token expiry, refresh, revoke และ key rotation
- redirect URI allowlist
- HTTPS ทุก endpoint

### Production Acceptance Gate

จะ Deploy Production ได้เมื่อครบทุกข้อ:

- [ ] Laravel unit/feature tests ผ่านทั้งหมด
- [ ] MCP unit/integration tests ผ่านทั้งหมด
- [ ] Permission matrix ผ่านทั้ง allowed และ denied cases
- [ ] Team Admin ไม่สามารถข้ามทีมได้
- [ ] Sales ไม่สามารถอ่านข้อมูลของผู้อื่นได้
- [ ] Audit Log ครบและไม่เก็บ secrets
- [ ] ตัวเลขจาก MCP ตรงกับ Dashboard
- [ ] Demo soak test ผ่านโดยไม่มี error สำคัญตามระยะเวลาที่ตกลงกัน
- [ ] Security review ของ service key, OAuth, headers, CORS และ rate limit ผ่าน
- [ ] มีขั้นตอน rollback และผู้รับผิดชอบชัดเจน
- [ ] ผู้ดูแลระบบยืนยันอนุมัติ Production cutover

### Rollback

- ปิด `PRIME_MCP_ENABLED` ที่ Laravel หรือหยุด Node.js application ที่ Plesk
- Revoke Production OAuth clients/tokens ที่เกี่ยวข้องเมื่อจำเป็น
- เก็บ Audit Log และ error logs เพื่อวิเคราะห์
- การ rollback MCP ต้องไม่กระทบการใช้งานเว็บ Prime Forecast ปกติ

## 10. Write Tools ในระยะถัดไป

Write tools ไม่อยู่ในขอบเขตระยะที่ 1 และต้องไม่ถูก register แม้ endpoint ภายในจะมีอยู่แล้ว

ก่อนเริ่มระยะ Write ต้องมีเอกสารแผนใหม่และผ่านเงื่อนไขต่อไปนี้:

- Permission tests ของ Read-only ผ่านครบถ้วน
- มี action-level permissions แยกจาก `mcp:read`
- มี confirmation step สำหรับการเปลี่ยนข้อมูล
- มี idempotency key, validation, transaction และ rollback strategy
- มี before/after Audit Log โดยไม่เก็บข้อมูลอ่อนไหวเกินจำเป็น
- มี staging test และการอนุมัติจากผู้รับผิดชอบระบบ

## 11. ลำดับงานแนะนำถัดไป

1. Review และ merge branch หน้าออก Demo token
2. พัฒนา Laravel MCP health, service-key middleware และ auth context
3. พัฒนา Permission Guard และ Forecast Read Repository
4. เพิ่ม Read-only endpoints และ Audit Log
5. เพิ่ม Laravel automated tests ให้ครบ Permission Matrix
6. Deploy Laravel Gateway ไป Demo
7. ทดสอบ `/readyz` และ End-to-end ด้วย token ของแต่ละ role
8. แก้ไขผลทดสอบและทำ Demo acceptance report
9. ออกแบบ OAuth สำหรับ Production
10. ขออนุมัติ Production cutover

## 12. เอกสารอ้างอิง

- [OpenAI — Authentication for apps and MCP servers](https://developers.openai.com/plugins/build/auth)
- [OpenAI — MCP configuration and authentication](https://learn.chatgpt.com/docs/extend/mcp?surface=cli)
- [Laravel Sanctum](https://laravel.com/docs/12.x/sanctum)

---

อัปเดตสถานะในเอกสารนี้ทุกครั้งที่ปิดงานแต่ละข้อ และบันทึกหลักฐานการทดสอบไว้ใน Pull Request หรือ Demo acceptance report ก่อนเปลี่ยนสถานะเป็นเสร็จสมบูรณ์
