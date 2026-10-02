<?php

namespace App\Services;

use App\Models\Transactional;
use App\Models\TransactionalStep;
use App\Models\User;
use App\Support\ProjectTimelineValidator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class McpSalesDraftService
{
    public function __construct(private McpSalesCreateAccess $access) {}

    public function authorize(User $user, $token): void
    {
        abort_unless($this->access->eligible($user) && $this->access->tokenAllowed($token), 403, 'Sales creation is not permitted.');
    }

    public function validatePayload(array $input, User $user): array
    {
        $rules = [
            // The deployed legacy schema limits this column to 100 characters.
            'Product_detail' => ['required', 'string', 'max:100'],
            'company_id' => ['required', 'integer', 'exists:company_catalog,company_id'],
            'Product_id' => ['required', 'integer', 'exists:product_group,product_id'],
            'Source_budget_id' => ['required', 'integer', 'exists:source_of_the_budget,Source_budget_id'],
            'priority_id' => ['required', 'integer', 'exists:priority_level,priority_id'],
            'team_id' => ['required', 'integer', 'exists:team_catalog,team_id'],
            'product_value' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'fiscalyear' => ['required', 'integer', 'between:2000,2100'],
            'contact_start_date' => ['required', 'date_format:Y-m-d'],
            'date_of_closing_of_sale' => ['nullable', 'date_format:Y-m-d'],
            'sales_can_be_close' => ['nullable', 'date_format:Y-m-d'],
            'remark' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_note' => ['nullable', 'string', 'max:2000'],
            'steps' => ['sometimes', 'array', 'max:6'],
            'steps.*' => ['array:level_id,date'],
            'steps.*.level_id' => ['required', 'integer', 'distinct', 'exists:step,level_id'],
            'steps.*.date' => ['required', 'date_format:Y-m-d'],
        ];
        $validator = Validator::make($input, $rules);
        $validator->after(function ($validator) use ($input, $rules) {
            if (array_diff(array_keys($input), array_keys($rules))) {
                $validator->errors()->add('project', 'Unknown fields are not accepted; owner is the authenticated user.');
            }
        });
        $data = $validator->validate();
        abort_unless(DB::table('transactional_team')->where('user_id', $user->user_id)->where('team_id', $data['team_id'])->exists(), 403, 'Team is outside your scope.');
        $timeline = $this->timeline($data);
        $timelineValidator = Validator::make($timeline, []);
        ProjectTimelineValidator::attach($timelineValidator, $timeline);
        $timelineValidator->validate();
        $data['product_value'] = number_format((float) $data['product_value'], 2, '.', '');
        foreach (['company_id', 'Product_id', 'Source_budget_id', 'priority_id', 'team_id', 'fiscalyear'] as $key) {
            $data[$key] = (int) $data[$key];
        }
        $data['steps'] = collect($data['steps'] ?? [])->map(fn ($step) => ['level_id' => (int) $step['level_id'], 'date' => $step['date']])->sortBy('level_id')->values()->all();
        ksort($data);

        return $data;
    }

    public function prepare(User $user, $token, string $key, array $input): object
    {
        $this->authorize($user, $token);
        $data = $this->validatePayload($input, $user);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $json);

        return DB::transaction(function () use ($user, $token, $key, $json, $hash) {
            // Serialize preparation for a user, including simultaneous retries.
            DB::table('user')->where('user_id', $user->user_id)->lockForUpdate()->first();
            $existing = DB::table('mcp_sales_drafts')->where('user_id', $user->user_id)->where('idempotency_key', $key)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'Idempotency key was used with different project data.');

                return $existing;
            }
            $id = (string) Str::uuid();
            DB::table('mcp_sales_drafts')->insert([
                'id' => $id, 'user_id' => $user->user_id, 'token_id' => $token->id,
                'idempotency_key' => $key, 'payload' => Crypt::encryptString($json), 'payload_hash' => $hash,
                'expires_at' => now()->addMinutes(15), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $draft = DB::table('mcp_sales_drafts')->where('id', $id)->first();
            $this->audit($user, $draft, 'draft_prepared');

            return $draft;
        });
    }

    public function owned(User $user, string $id): object
    {
        abort_unless(Str::isUuid($id), 404);
        $draft = DB::table('mcp_sales_drafts')->where('id', $id)->where('user_id', $user->user_id)->first();
        abort_unless($draft, 404);

        return $draft;
    }

    public function payload(object $draft): array
    {
        return json_decode(Crypt::decryptString($draft->payload), true, 512, JSON_THROW_ON_ERROR);
    }

    public function result(object $draft): array
    {
        return [
            'draft_id' => $draft->id,
            'status' => $draft->project_id ? 'created' : (now()->greaterThanOrEqualTo($draft->expires_at) ? 'expired' : 'pending_confirmation'),
            'project_id' => $draft->project_id ? (int) $draft->project_id : null,
            'expires_at' => $draft->expires_at,
            'confirmation_url' => route('mcp.sales-drafts.show', $draft->id),
        ];
    }

    public function confirm(User $user, string $id): object
    {
        abort_unless($this->access->eligible($user), 403);

        return DB::transaction(function () use ($user, $id) {
            $draft = DB::table('mcp_sales_drafts')->where('id', $id)->where('user_id', $user->user_id)->lockForUpdate()->first();
            abort_unless($draft, 404);
            if ($draft->project_id) {
                return $draft;
            }
            // A revoked/expired originating token cannot authorize an old draft.
            $this->authorize($user, $user->tokens()->whereKey($draft->token_id)->first());
            abort_if(now()->greaterThanOrEqualTo($draft->expires_at), 410, 'Draft expired. Prepare a new draft.');
            $data = $this->validatePayload($this->payload($draft), $user);
            $stepId = ProjectTimelineValidator::latestSelectedStepId($this->timeline($data));
            $stepId ??= DB::table('step')->orderBy('orderlv')->value('level_id');
            abort_unless($stepId, 422, 'A default sales step must be configured.');
            $steps = $data['steps'];
            unset($data['steps']);
            $data['remark'] ??= '';
            $project = Transactional::create(array_merge($data, [
                'user_id' => $user->user_id, 'Step_id' => $stepId,
                'present' => 0, 'budgeted' => 0, 'tor' => 0, 'bidding' => 0, 'win' => 0, 'lost' => 0,
            ]));
            $orders = DB::table('step')->pluck('orderlv', 'level_id');
            foreach (collect($steps)->sortBy(fn ($step) => $orders[$step['level_id']]) as $step) {
                TransactionalStep::create(['transac_id' => $project->transac_id] + $step);
            }
            DB::table('mcp_sales_drafts')->where('id', $id)->update([
                'project_id' => $project->transac_id, 'confirmed_at' => now(), 'updated_at' => now(),
            ]);
            $draft->project_id = $project->transac_id;
            $this->audit($user, $draft, 'project_created'); // Fail closed: audit and project commit together.

            return $draft;
        });
    }

    private function timeline(array $data): array
    {
        $data['step'] = [];
        $data['step_date'] = [];
        foreach ($data['steps'] ?? [] as $step) {
            $data['step'][$step['level_id']] = true;
            $data['step_date'][$step['level_id']] = $step['date'];
        }

        return $data;
    }

    private function audit(User $user, object $draft, string $event): void
    {
        DB::table('mcp_audit_logs')->insert([
            'request_id' => (string) Str::uuid(), 'environment' => app()->environment(),
            'user_id' => $user->user_id, 'role' => (int) $user->role_id === 3 ? 'sales' : 'team_admin',
            'token_id' => $draft->token_id, 'tool_name' => 'prepare_sales_project',
            'endpoint' => '/mcp/sales-drafts/'.$event,
            'arguments' => json_encode(['draft_id' => $draft->id, 'payload_hash' => $draft->payload_hash]),
            'scope' => json_encode(['event' => $event, 'before' => null, 'after' => ['project_id' => $draft->project_id, 'sales_id' => $user->user_id]]),
            'decision' => 'allowed', 'http_status' => 201, 'duration_ms' => 0, 'created_at' => now(),
        ]);
    }
}
