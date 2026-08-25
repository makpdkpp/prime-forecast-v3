<?php

namespace App\Http\Controllers\Api\Mcp;

use App\Http\Controllers\Controller;
use App\Repositories\McpForecastReadRepository;
use App\Services\McpPrincipalResolver;
use App\Support\McpPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ForecastController extends Controller
{
    public function __construct(
        private McpPrincipalResolver $resolver,
        private McpForecastReadRepository $repository,
    ) {}

    public function me(Request $request): JsonResponse
    {
        $principal = $this->resolver->resolve();

        return $this->read($request, ['type' => 'user', 'id' => $principal->userId], $principal);
    }

    public function team(Request $request, int $teamId): JsonResponse
    {
        $principal = $this->resolver->resolve();
        if ($principal->role === 'sales' || ($principal->role === 'team_admin' && ! in_array($teamId, $principal->teamIds, true))) {
            return $this->forbidden();
        }
        if ($teamId <= 0 || ! DB::table('team_catalog')->where('team_id', $teamId)->exists()) {
            return $this->notFound();
        }

        return $this->read($request, ['type' => 'team', 'ids' => [$teamId]], $principal);
    }

    public function sales(Request $request, int $salesId): JsonResponse
    {
        $principal = $this->resolver->resolve();
        if ($principal->role === 'sales') {
            return $this->forbidden();
        }
        if (! DB::table('user')->where('user_id', $salesId)->where('role_id', 3)->where('is_active', true)->exists()) {
            return $this->notFound();
        }
        if ($principal->role === 'team_admin' && ! DB::table('transactional_team')->where('user_id', $salesId)->whereIn('team_id', $principal->teamIds)->exists()) {
            return $this->forbidden();
        }

        return $this->read($request, ['type' => 'user', 'id' => $salesId], $principal);
    }

    public function company(Request $request): JsonResponse
    {
        $principal = $this->resolver->resolve();
        if ($principal->role !== 'admin') {
            return $this->forbidden();
        }

        return $this->read($request, ['type' => 'all'], $principal);
    }

    private function read(Request $request, array $scope, McpPrincipal $principal): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:3000'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.max(1, (int) config('services.prime_mcp.max_page_size', 100))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => ['code' => 'validation_error', 'message' => 'Invalid forecast filters.', 'details' => $validator->errors()]], 422);
        }

        $year = $request->integer('year') ?: null;
        $quarter = $request->integer('quarter') ?: null;
        $dateFrom = $request->filled('date_from') ? $request->string('date_from')->toString() : null;
        $dateTo = $request->filled('date_to') ? $request->string('date_to')->toString() : null;
        $perPage = min($request->integer('per_page') ?: 25, (int) config('services.prime_mcp.max_page_size', 100));
        $paginator = $this->repository->paginate($scope, $year, $quarter, $dateFrom, $dateTo, $perPage);

        $data = $paginator->getCollection()->map(fn ($project) => [
            'id' => (int) $project->transac_id,
            'project' => $project->Product_detail,
            'company' => $project->company_name,
            'value' => (float) $project->product_value,
            'fiscal_year' => $project->fiscalyear ? (int) $project->fiscalyear : null,
            'sales_id' => (int) $project->user_id,
            'sales_name' => $this->salesName($project->sales_first_name, $project->sales_last_name),
            'team_id' => (int) $project->team_id,
            'step_order' => $project->step_order === null ? null : (int) $project->step_order,
            'step' => $project->step_name,
            'step_date' => $project->step_date,
            'contact_start_date' => $project->contact_start_date,
        ])->values();

        return response()->json([
            'data' => $data,
            'summary' => $this->repository->summary($scope, $year, $quarter, $dateFrom, $dateTo),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'role' => $principal->role,
            ],
        ]);
    }

    private function salesName(?string $firstName, ?string $lastName): ?string
    {
        $name = trim(implode(' ', array_filter([
            trim((string) $firstName),
            trim((string) $lastName),
        ], static fn (string $part) => $part !== '')));

        return $name === '' ? null : $name;
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'forbidden', 'message' => 'You do not have permission for this forecast scope.']], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => ['code' => 'not_found', 'message' => 'Forecast scope was not found.']], 404);
    }
}
