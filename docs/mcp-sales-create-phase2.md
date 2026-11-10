# Phase 2: Sales creation pilot

Status: Laravel and MCP implemented and tested locally. Demo acceptance is still
required. Write flags default off. No remote business records were changed.

## Workflow

Look up existing catalog IDs, prepare a draft using a UUID idempotency key, then give
the confirmation URL to the user. The user logs into Laravel, reviews every field
and submits the CSRF-protected confirmation form. MCP checks draft status afterwards.
Only status `created` with `project_id` proves a project exists. The assistant must
not open or submit the approval page on the user's behalf.

Sales and Team Admin may create only their own projects in assigned teams. Admin has
no write permission in this pilot. Existing project edits, deletion, transfers and
catalog/customer creation are excluded. The pilot tools are get_sales_create_options,
prepare_sales_project and get_sales_project_draft.

## Gates and transaction

Explicit mcp:read and mcp:sales:create token abilities, active account, per-user Create permission,
role, staging environment, feature flag and audit enabled are all required. Wildcard
tokens do not authorize creation. OAuth write scope requires browser consent.

Drafts expire after 15 minutes and are encrypted with Laravel's app key. Confirmation
rechecks the originating token expiry/revocation, team membership, catalogs and timeline.
Owner cannot be supplied by the client. Project, business-ordered steps, draft result
and audit commit atomically under a draft row lock. Audit failure rolls back creation.
Repeat confirmation returns the same project. Reuse the same idempotency key for the
same payload; changed payload returns 409. Different keys may create different projects.

Uses ProjectTimelineValidator from the existing web form. Gregorian dates and fiscal
years are required. Amounts are nonnegative THB with at most two decimals. The legacy
schema requires priority and limits project titles to 100 characters; this pilot
enforces both. Audits contain IDs/hash, not raw contact details.

## Deployment and Demo acceptance

Laravel Demo configuration:

```dotenv
APP_ENV=staging
APP_DEBUG=false
PRIME_MCP_ENABLED=true
PRIME_MCP_AUDIT_ENABLED=true
PRIME_MCP_SALES_CREATE_ENABLED=false
```

Manage Create permission at `/admin/users` in the create/edit form. New users default
to no Create permission. Read access continues to follow the existing role/team rules.
The migration `2026_11_09_000001_add_mcp_sales_create_enabled_to_user` imports existing
Sales/Team Admin IDs from `PRIME_MCP_SALES_CREATE_USER_IDS` once. Keep that legacy
configuration available until the migration finishes; afterwards it is ignored at runtime
and can be removed. Disabling Create also blocks existing write tokens, OAuth write
refreshes and pending draft confirmation; read access remains available. A read-only
token needs a new write authorization after enabling Create.
Production environment is rejected regardless of flag or per-user permission.
Node uses MCP_SALES_CREATE_ENABLED=false by default and restricts
enablement to HTTPS Demo hosts with Laravel audit enabled.

1. Deploy tested commits in both repos with write flags off; back up Demo DB.
2. Plesk Run a PHP script: demo.primes.co.th/artisan, arguments migrate --force.
   This creates mcp_sales_drafts and the per-user Create permission column, importing
   legacy grants. Do not repeat artisan in arguments.
3. Run config:clear using the same script. Restart PHP as required.
4. NPM Install / restart Node after MCP deploy; verify readiness and read tools.
5. Enable Create for selected Sales/Team Admin accounts at `/admin/users`, enable both flags on Demo, clear config and restart
   Node. Reauthorize OAuth with write consent, or opt in on the Demo token page.
6. Test own creation, cross-team/owner rejection, revoked/expired tokens, confirmation,
   retry and audit. Verify exactly one project on the normal dashboard.
7. Rollback: disable both flags and revoke pilot tokens. Retain drafts and audit.
   Do not automatically delete created projects or run migration down.

Automated tests use isolated SQLite. MySQL/InnoDB concurrent confirmation needs Demo
acceptance. Define retention for expired encrypted drafts before wider rollout.
