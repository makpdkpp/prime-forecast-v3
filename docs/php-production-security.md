# PHP 8.4 production security — 2026-10-26

## Scope and limits

PHP 8.4.27 fixes CVE-2026-109618 and CVE-2026-109619 (PHP2Shell).
These are engine/request-initialization flaws: Laravel middleware, authentication,
Composer updates and WAF rules are NOT substitutes for the patched runtime.
The separate CVE-2026-115560 advisory recommends disabling native PHP session
upload progress pending the next regular fix. Keep monitoring PHP/vendor updates.
This repository does not use native session upload-progress tracking or change
`session.serialize_handler` in application code. Disabling tracking does not disable file uploads.

## Plesk deployment (Demo first, then Production)

1. Back up the deployment/settings. Choose PHP **8.4.27 or a later patched 8.4 release**
   for the Laravel domain (not the Node MCP domain). Ask the host to update packages
   if unavailable. Ensure scheduled tasks use the updated version too.
2. Document root must be the Laravel `public/` directory, not the repository root.
3. In domain **PHP Settings**, apply these additional directives before serving requests:

   ```ini
   session.upload_progress.enabled = 0
   display_errors = Off
   display_startup_errors = Off
   log_errors = On
   allow_url_include = Off
   expose_php = Off
   ```

   If these controls are unavailable, request the provider to apply them to the
   domain's effective PHP/FPM configuration. Do not implement them with `ini_set()`.
   `public/.user.ini` is a fallback for CGI/FastCGI, not mod_php, and may be disabled
   or overridden by host settings. It does not cover other applications/domains.
4. Set Laravel environment values `APP_ENV=production`, `APP_DEBUG=false`,
   `LOG_LEVEL=info`, `SESSION_SECURE_COOKIE=true` on HTTPS Production.
   Keep Demo `APP_ENV=staging`. Do not rotate APP_KEY as part of this update.
5. Apply settings and ask the provider to restart/recycle the affected PHP workers.
   Restarting Node or running Artisan is NOT a PHP-FPM restart. Old workers and
   `.user.ini` caching can retain old settings.
6. Verify the **web handler** version and Local Values using Plesk's authenticated
   PHP information view/provider: PHP >=8.4.27 on 8.4, upload progress Off,
   error display Off. Do not deploy a public `phpinfo.php` endpoint.
7. Ensure nginx denies `.user.ini` and other dotfiles (except necessary `.well-known`
   routes), and denies script execution in `/uploads/` and `/storage/`. Apache rules
   do not protect requests handled directly by nginx. Verify `/.user.ini` returns
   403/404, never its content. Have the provider configure nginx if shared-host UI
   does not permit it.
8. Plesk scheduled task: Run a PHP script, Script path `<domain>/artisan`, arguments
   `config:clear`, then optionally `config:cache`. Use a one-time Run Now and disable
   the task afterward. No database migration is needed for this change.
9. Run `security:php-check` the same way for a **CLI-only** diagnostic. A nonzero
   result identifies baseline failures, not proof of exploitation. CLI does not
   read `.user.ini`; a CLI pass does not certify the web handler.
10. Demo smoke test: login/logout, avatar upload, Excel import/export, PDF export,
    MCP health/readiness, OAuth login, and Sales/Team Admin permission tests.
    Do not enable MCP write tools on Production as part of this security patch.

For local verification: `php artisan security:php-check` and
`php vendor/bin/phpunit tests/Unit/PhpSecurityConfigurationTest.php`.
Run the test suite and `composer check-platform-reqs --no-dev` under the actual
8.4.27 runtime before Production rollout. Config-file tests do not prove Plesk
loaded the settings. Audit dependencies with `composer audit --locked --no-dev`.

If previously exposed, review provider/FPM/access logs and unexpected file changes.
Patching prevents the documented attack paths; it does not remove an existing compromise.

## Primary references

- https://www.php.net/archive/2026.php#2026-10-21-5
- https://www.php.net/archive/2026.php#2026-10-23-1
- https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w
- https://www.php.net/manual/en/session.configuration.php
- https://www.php.net/manual/en/configuration.file.per-user.php
