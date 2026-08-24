<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class McpForecastReadRepository
{
    public function paginate(array $scope, ?int $year, ?int $quarter, int $perPage): LengthAwarePaginator
    {
        $query = $this->baseQuery($scope, $year, $quarter)
            ->select([
                't.transac_id',
                't.Product_detail',
                't.product_value',
                't.fiscalyear',
                't.user_id',
                't.team_id',
                't.contact_start_date',
                'company.company as company_name',
                'current_step.orderlv as step_order',
                'current_step.level as step_name',
                'latest_step.date as step_date',
            ])
            ->orderByDesc('t.product_value')
            ->orderBy('t.transac_id');

        return $query->paginate($perPage);
    }

    public function summary(array $scope, ?int $year, ?int $quarter): array
    {
        $query = $this->baseQuery($scope, $year, $quarter);

        return [
            'project_count' => (clone $query)->count('t.transac_id'),
            'forecast_value' => (float) ((clone $query)->sum('t.product_value') ?: 0),
            'win_value' => (float) ((clone $query)->where('current_step.orderlv', 5)->sum('t.product_value') ?: 0),
        ];
    }

    private function baseQuery(array $scope, ?int $year, ?int $quarter)
    {
        $latestIds = DB::table('transactional_step')
            ->select('transac_id', DB::raw('MAX(transacstep_id) as max_step_id'))
            ->groupBy('transac_id');

        $latestSteps = DB::table('transactional_step as latest_history')
            ->joinSub($latestIds, 'latest_ids', function ($join) {
                $join->on('latest_ids.transac_id', '=', 'latest_history.transac_id')
                    ->on('latest_ids.max_step_id', '=', 'latest_history.transacstep_id');
            })
            ->select('latest_history.transac_id', 'latest_history.level_id', 'latest_history.date');

        $query = DB::table('transactional as t')
            ->leftJoinSub($latestSteps, 'latest_step', 'latest_step.transac_id', '=', 't.transac_id')
            ->leftJoin('step as current_step', 'current_step.level_id', '=', 'latest_step.level_id')
            ->leftJoin('company_catalog as company', 'company.company_id', '=', 't.company_id')
            ->whereNull('t.deleted_at');

        if (($scope['type'] ?? null) === 'user') {
            $query->where('t.user_id', (int) $scope['id']);
        } elseif (($scope['type'] ?? null) === 'team') {
            $query->whereIn('t.team_id', array_map('intval', $scope['ids'] ?? []));
        }

        if ($year !== null) {
            $query->where('t.fiscalyear', $year);
        }

        if ($quarter !== null) {
            $query->whereRaw('QUARTER(COALESCE(latest_step.date, t.contact_start_date)) = ?', [$quarter]);
        }

        return $query;
    }
}
