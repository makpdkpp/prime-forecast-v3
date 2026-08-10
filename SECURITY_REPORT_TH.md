# รายงานตรวจสอบความปลอดภัย Prime Forecast V2

วันที่ตรวจ: 10 สิงหาคม 2026  
ขอบเขต: Source code และ configuration ภายใน repository `Prime-Forecast-V2`  
ลักษณะการตรวจ: Static application security review, dependency audit และคำสั่งตรวจสอบแบบไม่เปลี่ยนแปลงข้อมูล

## สรุปสำหรับผู้บริหาร

ระดับความเสี่ยง ณ เวลาพบครั้งแรก: **สูง (High)**  
ความเสี่ยงคงเหลือหลังแก้ไขใน repository: **ปานกลาง (Medium)** โดยยังต้องยืนยัน production infrastructure และจัดการ avatar เดิมใน Git

การตรวจครั้งแรกพบ Stored XSS, ข้อบกพร่องด้าน authorization และ dependency advisories จำนวนมาก ประเด็นระดับ Critical/High ที่ยืนยันได้ใน source code ได้รับการแก้ไขแล้ว รวมถึงอัปเกรด Laravel และ dependency lockfiles จน audit เหลือ 0 รายการ ส่วนค่า `.env` ปัจจุบันยังเป็นของเครื่องพัฒนา (`APP_ENV=local`, `APP_DEBUG=true`, HTTP) และห้ามนำไฟล์นี้ขึ้น production

| ระดับ | จำนวน | ประเด็น |
|---|---:|---|
| Critical | 1 | Stored XSS ไปยังหน้าของ Admin/Team Admin |
| High | 3 | ข้ามขอบเขตทีม/เจ้าของ, password-reset host poisoning, dependencies มีช่องโหว่ |
| Medium | 4 | รหัสผ่าน legacy/นโยบายอ่อน, production hardening, ปิด 2FA โดยไม่ยืนยันตัวตนซ้ำ, ข้อมูลผู้ใช้ใน public Git |
| Low | 1 | Logout ผ่าน GET |

> คะแนนนี้เป็นผลจากการอ่านโค้ดและ configuration ไม่ใช่ penetration test ต่อ production ระบบจริง จึงยังไม่ครอบคลุม web server, WAF, TLS, database permission และ infrastructure ภายนอก repository

## สถานะการแก้ไขล่าสุด (10 สิงหาคม 2026)

| รายการ | สถานะ | การดำเนินการ |
|---|---|---|
| SEC-01 Stored XSS | แก้ไขแล้ว | DataTables ใช้ text renderer และ dashboard modal escape ค่าจาก JSON; มี regression tests |
| SEC-02 ข้ามทีม/เจ้าของ | แก้ไขเส้นทางหลักแล้ว | User ถูกตรวจ membership ที่ server; Team Admin/Admin ถูกตรวจ role และ membership ของ owner/team |
| SEC-03 Host header poisoning | แก้ไขแล้ว | เปิด trusted-host middleware, reject Host ที่ไม่อยู่ใน allowlist และ force canonical production URL |
| SEC-04 Frontend dependencies | แก้ไขแล้ว | Axios 1.18.x, Vite 8.2.1, Laravel Vite Plugin 3.1.3; `npm audit` เหลือ 0 และ production build ผ่าน |
| SEC-04 Backend dependencies | แก้ไขแล้ว | Laravel 12.65.0 และ dependency รุ่นแก้ช่องโหว่; `composer audit --locked` เหลือ 0 โดย production ต้องมี PHP 8.2+ และ `ext-zip` |
| SEC-05 Password policy | แก้ไขบางส่วน | รหัสผ่านใหม่ขั้นต่ำ 12 ตัวอักษร; legacy MD5 ยังรองรับและ upgrade เมื่อยืนยันสำเร็จ |
| SEC-06 Hardening | แก้ไขใน application แล้วบางส่วน | เพิ่ม security headers, ซ่อน exception detail, production canonical URL และตัด migration routes ออกจาก production |
| SEC-07 ปิด 2FA | แก้ไขแล้ว | ต้องส่ง desired state และยืนยัน current password ก่อนปิด; รองรับ upgrade legacy password |
| SEC-08 Public uploads | แก้ไขบางส่วน | ignore upload ใหม่และเพิ่ม Apache rule ปิด directory listing/script execution; avatar เดิมที่ tracked ยังไม่ถูกลบ |
| SEC-09 GET logout | แก้ไขแล้ว | เหลือเฉพาะ POST route พร้อม CSRF protection |

ผลทดสอบหลังแก้ไข: PHP lint 0 failures, Blade compile ผ่าน, PHPUnit 11.5.56 จำนวน 10 tests/35 assertions ผ่านทั้งหมด, Vite 8.2.1 production build ผ่าน, `composer audit --locked` และ `npm audit` รายงาน 0 vulnerabilities

## เงื่อนไขก่อน deploy ไป Linux shared hosting

1. Hosting ต้องใช้ PHP 8.2 ขึ้นไปและเปิด extensions อย่างน้อย `zip`, `dom`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `xml` และ `xmlreader`; ตรวจด้วย `composer check-platform-reqs --no-dev`
2. ตั้ง document root ไปที่โฟลเดอร์ `public/` เท่านั้น และห้ามเปิด repository root, `.env`, `storage/logs` หรือ `vendor` ผ่าน URL
3. ตั้ง production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://โดเมนจริง`, `SESSION_SECURE_COOKIE=true` และใช้ HTTPS เท่านั้น โดยไม่คัดลอก `.env` จากเครื่องพัฒนา
4. ติดตั้งด้วย `composer install --no-dev --optimize-autoloader` จาก lockfile; build frontend ใน CI/เครื่องพัฒนาแล้ว deploy `public/build` ไม่ต้องเปิด Vite dev server บน production
5. สำรองฐานข้อมูลก่อน `php artisan migrate --force`, รัน smoke test ด้วยบัญชีแต่ละ role แล้วจึงสลับ traffic; เตรียม rollback เป็น release เดิมพร้อม database backup
6. ให้เขียนได้เฉพาะ `storage/`, `bootstrap/cache/` และ upload directory ตามจำเป็น ห้ามใช้ permission `777`; ยืนยันว่า Apache/LiteSpeed เคารพ `.htaccess` ใน `public/uploads`
7. รัน `php artisan optimize` หลังตั้งค่าเสร็จ และตรวจ login, 2FA/อีเมล, export Excel/PDF, authorization ข้ามทีม, security headers และ error page บน staging ที่มี configuration เหมือน production

## รายละเอียดช่องโหว่

### SEC-01 — Critical — Stored XSS ใน Dashboard และ Reports ของผู้มีสิทธิ์สูง

**หลักฐาน**

- User สามารถกำหนด `Product_detail` และ `remark` ได้ที่ `app/Http/Controllers/UserController.php:435-480`
- API ส่งค่าจากฐานข้อมูลกลับโดยไม่ encode ที่ `app/Http/Controllers/AdminController.php:741-784` และ `app/Http/Controllers/TeamAdminController.php:733-776`
- Admin DataTable แสดง `project`/`remark` โดยไม่มี text renderer ที่ `resources/views/admin/dashboard_table.blade.php:345-358`
- Team Admin มีรูปแบบเดียวกันที่ `resources/views/teamadmin/dashboard_table.blade.php:342-355`
- Dashboard สร้าง HTML template จาก `p.Product_detail` โดยตรงที่ `resources/views/admin/dashboard.blade.php:623,676` และ `resources/views/teamadmin/dashboard.blade.php:366`
- หน้ารายงานนำ `project_name`/`company_name` ใส่ DataTables โดยไม่ใช้ `render.text()` เช่น `resources/views/admin/reports/bidding.blade.php:180-185` และ `resources/views/teamadmin/reports/windate.blade.php:148`
- ไม่พบ Content-Security-Policy ใน application middleware/configuration

**ผลกระทบ**

บัญชี User สามารถบันทึก HTML/JavaScript ในชื่อโครงการหรือข้อความอื่น เมื่อ Admin/Team Admin เปิด dashboard หรือ report โค้ดจะทำงานภายใต้ origin ของระบบ ผู้โจมตีจึงอาจอ่านข้อมูลในหน้า, ดึง CSRF token และสั่งการด้วยสิทธิ์ของผู้ดูแลได้

**แนวทางแก้**

1. ใช้ `$.fn.dataTable.render.text()` กับทุกคอลัมน์ที่มาจากฐานข้อมูลหรือผู้ใช้
2. เลิกต่อ HTML ด้วย template literal สำหรับข้อมูลภายนอก ให้สร้าง element และกำหนดผ่าน `textContent`/jQuery `.text()`
3. แยกคอลัมน์ `action` ที่ตั้งใจเป็น HTML ออกจาก data columns อย่างชัดเจน
4. เพิ่ม CSP ที่เหมาะสม โดยทยอยย้าย inline script ไปไฟล์ภายนอกและใช้ nonce/hash ระหว่างเปลี่ยนผ่าน
5. เพิ่ม regression test ด้วยค่าประเภท `<img src=x onerror=alert(1)>` และยืนยันว่าแสดงเป็นข้อความเท่านั้น

### SEC-02 — High — Broken object-level authorization และการย้ายข้อมูลข้ามทีม

**หลักฐาน**

- User เห็นเฉพาะทีมของตนในหน้า form แต่ server ตรวจ `team_id` แค่ว่าเป็น integer ที่ `app/Http/Controllers/UserController.php:434-448` แล้วบันทึกค่าจาก request ที่บรรทัด 463-480
- การแก้ผ่านทั้ง normal/AJAX มีปัญหาเดียวกันที่ `app/Http/Controllers/UserController.php:595-650` และ `:671-722`
- Team Admin ตรวจว่ารายการเดิมอยู่ในทีมของตนที่ `app/Http/Controllers/TeamAdminController.php:1035-1043` แต่ยอมรับ `user_id` และ `team_id` เป็น integer เท่านั้นที่ `:1045-1061` และเขียนค่าตรงจาก request ที่ `:1074-1093`

**ผลกระทบ**

- User สามารถแก้ request แล้วสร้าง/ย้ายรายการไปทีมที่ตนไม่ได้เป็นสมาชิก ทำให้ข้อมูลทางธุรกิจและข้อมูลผู้ติดต่อปรากฏแก่ Team Admin ของทีมอื่น
- Team Admin สามารถกำหนดเจ้าของหรือทีมปลายทางใดก็ได้ รวมถึงค่าที่อยู่นอกขอบเขตบริหาร และทำให้ ownership/audit trail ไม่ถูกต้อง

**แนวทางแก้**

1. สร้าง Policy/Authorization service กลางสำหรับ Transactional แทนการตรวจแบบกระจายใน controller
2. ใช้ validation แบบ `Rule::exists(...)->where(...)` ผูก `team_id` กับ `transactional_team` ของผู้เรียก
3. Team Admin ต้องเลือกได้เฉพาะ user ที่เป็น role User และเป็นสมาชิกทีมที่ผู้ดูแลรับผิดชอบ
4. ไม่ให้แก้ owner/team ผ่าน endpoint update ปกติ ให้ใช้ transfer endpoint เฉพาะที่ตรวจสิทธิ์และบันทึก audit log
5. ครอบการแก้ Transactional และ TransactionalStep ด้วย database transaction

### SEC-03 — High — Password reset link อาจถูกทำ Host Header Poisoning

**หลักฐาน**

- Global `TrustHosts` middleware ถูกปิดที่ `app/Http/Kernel.php:17`
- URL reset ถูกสร้างด้วย `route()` ระหว่าง request ที่ `app/Mail/PasswordResetLink.php:22`
- Endpoint forgot-password รับ request จากภายนอกและส่ง URL ที่สร้างขึ้นทางอีเมลที่ `app/Http/Controllers/AuthController.php:26-45`

**ผลกระทบ**

หาก reverse proxy/web server ไม่จำกัด Host header ผู้โจมตีอาจส่ง forgot-password ด้วย Host ที่ควบคุม ทำให้อีเมลของเหยื่อมีลิงก์ไปโดเมนผู้โจมตี เมื่อเหยื่อกด token อาจรั่วและนำไปยึดบัญชีได้

**แนวทางแก้**

1. เปิด `TrustHosts` และ allowlist เฉพาะ hostname จริง
2. ตั้ง canonical `APP_URL` เป็น HTTPS และบังคับ root URL/scheme สำหรับ URL ในอีเมล
3. จำกัด Host ที่ web server/load balancer อีกชั้น
4. เพิ่ม integration test ส่ง Host ที่ไม่อนุญาตแล้วต้องถูก reject และตรวจ host ของ reset URL

### SEC-04 — High — Dependencies ที่ล็อกไว้มี security advisories จำนวนมาก

ผล `composer audit --locked` ณ วันที่ตรวจพบ **49 advisories ใน 12 packages**: Critical 2, High 12, Medium 28, Low 6 และอีก 1 รายการไม่มี severity

**สถานะหลังแก้ไข:** อัปเกรดเป็น `laravel/framework 12.65.0`, `laravel/sanctum 4.3.3`, `dompdf/dompdf 3.1.6`, `guzzlehttp/guzzle 7.15.3` และ `phpoffice/phpspreadsheet 1.30.6` แล้ว ผล `composer audit --locked` ล่าสุดเหลือ **0 advisories** การ deploy ต้องตรวจว่า shared hosting ใช้ PHP 8.2 ขึ้นไปและเปิด `ext-zip`; หากไม่ผ่านต้องเปลี่ยน hosting/runtime ไม่ควร downgrade กลับไปยัง Laravel 10 ที่หมดระยะ security support

เวอร์ชันสำคัญที่พบ: `laravel/framework v10.50.0`, `dompdf/dompdf v3.1.5`, `guzzlehttp/guzzle 7.10.0`, `guzzlehttp/psr7 2.8.0`, `phpoffice/phpspreadsheet 1.30.2`, `symfony/http-foundation 6.4.33`, `symfony/mailer 6.4.31`, `symfony/mime 6.4.32`, `symfony/routing 6.4.32`

ตัวอย่าง advisory สำคัญ:

- PhpSpreadsheet Critical: CVE-2026-34084 และ CVE-2026-45034 (SSRF/RCE เมื่อโหลด filename ที่ผู้ใช้ควบคุม)
- Symfony Mime High: CVE-2026-45067 (email header/SMTP command injection)
- Guzzle High: CVE-2026-69246 (host-based check bypass)
- Laravel Framework High: GHSA-5vg9-5847-vvmq (CRLF injection ใน default email rule)
- Dompdf 3.1.5 มี local-file information leak/resource exhaustion advisories; advisory ระบุแก้ใน 3.1.6

**การประเมิน reachability**

ในโค้ดที่ตรวจพบการใช้ PhpSpreadsheet เพื่อ **export** เท่านั้น ยังไม่พบ endpoint import/load ไฟล์ผู้ใช้ ดังนั้น Critical RCE ของ reader ยังไม่มีเส้นทางเรียกใช้ที่ยืนยันได้ในปัจจุบัน อย่างไรก็ตาม dependency ที่มีช่องโหว่ยังควรถูกอัปเดต เพราะมีทั้ง advisory ที่เกี่ยวกับ mail/URL และโค้ดอาจเปลี่ยนในอนาคต

**แนวทางแก้**

อัปเดต dependency บน branch แยก, รัน `composer update` แบบจำกัด package, ตรวจ breaking changes, รัน test ทั้งระบบ แล้วกำหนด CI ให้ `composer audit --locked` ล้ม build เมื่อพบ Critical/High ที่ไม่อยู่ใน exception พร้อมเหตุผลและวันหมดอายุ

**Frontend/npm audit**

หลังเปิดใช้งาน Node.js v22.22.0 และ npm 10.9.4 แล้ว ผล `npm audit --omit=dev` รายงาน 0 รายการ เนื่องจาก dependencies ทั้งหมดใน `package.json` ถูกประกาศไว้ใต้ `devDependencies` อย่างไรก็ตาม Axios ถูก bundle เข้า JavaScript ที่ deploy จริง ดังนั้นผล production=0 ตาม metadata ไม่ควรถูกตีความว่า frontend ไม่มีความเสี่ยง

ผล `npm audit` เมื่อรวม development/build dependencies พบ **10 packages**: High 7, Moderate 2, Low 1, Critical 0 ได้แก่:

- High: `axios 1.13.4`, `form-data 4.0.5`, `nanoid 3.3.11`, `picomatch 2.3.1`, `postcss 8.5.6`, `rollup 3.29.5`, `vite 4.5.14`
- Moderate: `esbuild 0.18.20`, `follow-redirects 1.15.11`

**สถานะหลังแก้ไข:** อัปเดต Axios เป็น 1.18.x, Vite 8.2.1 และ Laravel Vite Plugin 3.1.3 แล้ว ทั้ง `npm audit` และ production build ผ่านโดยไม่พบ vulnerability
- Low: `laravel-vite-plugin 0.7.8`

Direct dependencies ที่ต้องจัดการคือ Axios, Vite และ Laravel Vite Plugin โดย npm ระบุว่ามี fix แต่ Vite แนะนำ `8.2.1` และ Laravel Vite Plugin แนะนำ `3.1.3` ซึ่งเป็น major-version upgrade ต้องทดสอบ compatibility และ build behavior ก่อน merge ส่วน transitive dependencies ควรถูกแก้ผ่านการอัปเดต direct dependency/lockfile ไม่ควร pin แบบสุ่ม

ตัวอย่างผลกระทบที่เกี่ยวข้อง: Axios มี advisories ด้าน prototype-pollution gadgets, request/header manipulation, SSRF/NO_PROXY bypass และ DoS; Vite/esbuild มีปัญหาการเปิดเผยไฟล์หรือ dev-server access โดยเฉพาะบน Windows จึงต้องไม่เปิด Vite development server ให้ network ที่ไม่เชื่อถือระหว่างรออัปเดต

### SEC-05 — Medium — รองรับ MD5 password และกำหนดขั้นต่ำเพียง 6 ตัวอักษร

**หลักฐาน**

- Login ยังตรวจ password legacy ด้วย MD5 ที่ `app/Http/Controllers/AuthController.php:109-130`
- Reset/registration/admin password ใช้ขั้นต่ำ 6 ตัวอักษรที่ `app/Http/Controllers/AuthController.php:67-70`, `app/Http/Controllers/RegistrationController.php:34-36`, `app/Http/Controllers/Admin/UserManagementController.php:117-139`

**ผลกระทบ**

หากฐานข้อมูลรั่ว MD5 สามารถเดาแบบ offline ได้เร็วมาก และรหัสผ่าน 6 ตัวอักษรเพิ่มโอกาส credential stuffing/password guessing

**แนวทางแก้**

บังคับ reset บัญชีที่ยังเป็น MD5 แทนการรอ login, ใช้ `Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised()` ตามระดับความเหมาะสม และป้องกันการใช้รหัสผ่านซ้ำ

### SEC-06 — Medium — Production hardening และ error disclosure ไม่พร้อม

Configuration ปัจจุบันมี `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL` เป็น HTTP, ไม่ได้กำหนด `SESSION_SECURE_COOKIE`, session encryption ปิด และไม่พบ middleware สำหรับ CSP/HSTS/X-Content-Type-Options/Referrer-Policy นอกจากนี้หลาย controller ส่ง `$e->getMessage()` กลับผู้ใช้ เช่น `app/Http/Controllers/UserController.php:666-667,739-740,827-828` และ `app/Http/Controllers/AdminController.php:954-955,1015-1017,1098-1099`

หาก configuration นี้ถูกนำไป deploy จะเปิดเผย stack trace, SQL/schema/path หรือรายละเอียดระบบ และ cookie อาจถูกส่งผ่าน HTTP ส่วนหน้า web migration จะอนุญาต run/rollback เมื่อ environment ไม่ใช่ production ที่ `app/Http/Controllers/Admin/MigrationController.php:38-119`

**แนวทางแก้**

ตั้ง production environment แยกที่ตรวจสอบได้, `APP_DEBUG=false`, HTTPS เท่านั้น, `SESSION_SECURE_COOKIE=true`, เพิ่ม security headers, แสดง error ID ทั่วไปแก่ผู้ใช้และ log รายละเอียดฝั่ง server และปิด migration UI จาก build production โดยตรงแทนการพึ่ง environment check เพียงอย่างเดียว

### SEC-07 — Medium — เปิด/ปิด 2FA โดยไม่ยืนยัน password หรือ OTP ซ้ำ

Endpoints ทุก role ใช้เพียง authenticated session และ CSRF token เช่น `app/Http/Controllers/UserController.php:754-769`, `app/Http/Controllers/TeamAdminController.php:953-967`, `app/Http/Controllers/AdminController.php:1103-1117`

ผู้ที่ได้ session ที่ยังใช้งานอยู่สามารถปิด 2FA เพื่อรักษาการเข้าถึงบัญชีได้ แนะนำให้ require password confirmation หรือ OTP ล่าสุดก่อนปิด 2FA และแจ้งเตือนทางอีเมลเมื่อสถานะเปลี่ยน

### SEC-08 — Medium — ไฟล์ avatar ของผู้ใช้ถูก commit และเสิร์ฟจาก public path

Git ติดตาม `public/uploads/avatars/user_7_1770173244.jpg` ซึ่งเป็นไฟล์เฉพาะผู้ใช้ และไฟล์ upload อยู่ใต้ document root โดยตรง การ commit ทำให้ข้อมูลอยู่ใน Git history แม้ลบภายหลัง และ URL ถูกเข้าถึงได้โดยไม่ผ่าน authorization

ควรนำ user uploads ออกจาก source control, เพิ่ม `/public/uploads/` ใน `.gitignore`, เก็บใน private/object storage ตาม classification ของข้อมูล และให้ download ผ่าน authorization หรือ signed URL หากไม่ควรเป็นข้อมูลสาธารณะ รวมถึงกำหนด lifecycle ลบ avatar เก่า

### SEC-09 — Low — Logout ผ่าน GET ทำให้เกิด logout CSRF

`routes/web.php:33` เปิด `GET /logout` ซึ่งเว็บไซต์ภายนอกสามารถเรียกด้วย image/link แล้วทำให้ผู้ใช้หลุดจากระบบได้ แม้ผลกระทบหลักเป็น availability/ความรำคาญ ควรเหลือเฉพาะ POST ที่มี CSRF protection

## สิ่งที่ทำได้ดี

- Protected routes ใช้ `auth` และ role middleware
- User edit endpoints ตรวจ ownership ของ Transactional เดิม
- CSRF middleware เปิดใน web group
- Login/forgot-password/OTP มี rate limit
- Reset/invitation token ใช้ random token, เก็บเป็น SHA-256 และมี expiry
- OTP สร้างด้วย `random_int`, เก็บเป็น password hash และมี expiry
- Regenerate session หลัง login/2FA และ invalidate เมื่อ logout
- Avatar จำกัดชนิด/ขนาด, derive extension จาก MIME และสุ่มชื่อไฟล์
- Blade templates ส่วนใหญ่ใช้ `{{ }}` ซึ่ง escape output
- PHP lint ผ่านทุกไฟล์ใน `app`, `routes` และ `database/migrations` (0 failures)

## ลำดับการแก้ไขที่แนะนำ

**ภายใน 24–72 ชั่วโมง**

1. แก้ Stored XSS ทุก privileged dashboard/report และเพิ่ม regression test
2. ปิดช่องโหว่ข้ามทีม/เจ้าของที่ server-side
3. เปิด trusted-host allowlist และยืนยัน canonical reset URL
4. หาก instance ใดเข้าถึงจากเครือข่ายได้ ให้ปิด debug, บังคับ HTTPS และปิด migration UI ทันที

**ภายใน 1 สัปดาห์**

1. บังคับ reset รหัสผ่าน MD5 ที่ยังเหลืออยู่ตามรอบการเปลี่ยนรหัสผ่าน
2. นำ avatar เดิมออกจาก Git history/working tree หลังได้รับอนุมัติเรื่องข้อมูลผู้ใช้
3. ทดสอบ staging บน PHP/Linux และ web server ชนิดเดียวกับ shared hosting
4. กำหนด storage/retention policy สำหรับ user uploads

**ภายใน 2–4 สัปดาห์**

1. เพิ่ม CSP/security headers
2. เพิ่ม authorization matrix tests สำหรับ Admin/Team Admin/User
3. เพิ่ม CI checks: Composer audit, frontend audit, secret scanning และ SAST
4. ทำ authenticated dynamic test บน staging ที่ใช้ configuration ใกล้ production

## การตรวจที่ดำเนินการและข้อจำกัด

- ตรวจ route production ทั้งหมด 134 routes และยืนยันว่าไม่มี migration routes
- PHP syntax lint: ผ่านทั้งหมด
- `composer audit --locked`: 0 advisories หลังอัปเกรด Laravel/dependencies
- `php artisan test`: ผ่าน 10 tests/35 assertions บน Laravel 12.65.0 และ PHPUnit 11.5.56
- เปิดใช้งาน npm 10.9.4 (Node.js v22.22.0) จาก Laragon และเพิ่มใน User PATH แล้ว
- `npm ls --all --json`: dependency tree อ่านได้ครบและไม่รายงาน dependency ที่ขาด
- `npm run build`: สำเร็จด้วย Vite 8.2.1 (56 modules)
- `npm audit`: 0 vulnerabilities รวม development/build dependencies
- ไม่ได้ยิง exploit ต่อระบบจริง, ไม่ได้แก้ข้อมูลในฐานข้อมูล และไม่ได้ทดสอบ web server/TLS/headers จาก production URL
