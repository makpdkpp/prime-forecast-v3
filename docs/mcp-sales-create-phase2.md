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

Explicit mcp:read and mcp:sales:create token abilities, active account, tester allowlist,
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
PRIME_MCP_SALES_CREATE_USER_IDS=
```

Empty allowlist allows nobody. Production environment is rejected regardless of flag.
Node uses MCP_SALES_CREATE_ENABLED=false by default and restricts
enablement to HTTPS Demo hosts with Laravel audit enabled.

1. Deploy tested commits in both repos with write flags off; back up Demo DB.
2. Plesk Run a PHP script: demo.primes.co.th/artisan, arguments migrate --force.
   This creates mcp_sales_drafts. Do not repeat artisan in arguments.
3. Run config:clear using the same script. Restart PHP as required.
4. NPM Install / restart Node after MCP deploy; verify readiness and read tools.
5. Populate selected tester IDs, enable both flags on Demo, clear config and restart
   Node. Reauthorize OAuth with write consent, or opt in on the Demo token page.
6. Test own creation, cross-team/owner rejection, revoked/expired tokens, confirmation,
   retry and audit. Verify exactly one project on the normal dashboard.
7. Rollback: disable both flags and revoke pilot tokens. Retain drafts and audit.
   Do not automatically delete created projects or run migration down.

Automated tests use isolated SQLite. MySQL/InnoDB concurrent confirmation needs Demo
acceptance. Define retention for expired encrypted drafts before wider rollout.
