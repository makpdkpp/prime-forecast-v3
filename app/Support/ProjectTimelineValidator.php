<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class ProjectTimelineValidator
{
    public static function latestSelectedStepId(array $input): ?int
    {
        $selected = collect($input['step'] ?? [])
            ->filter(fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN))
            ->keys()
            ->map(fn ($levelId) => (int) $levelId)
            ->values();

        if ($selected->isEmpty()) {
            return null;
        }

        $levelId = DB::table('step')
            ->whereIn('level_id', $selected)
            ->orderByDesc('orderlv')
            ->value('level_id');

        return $levelId === null ? null : (int) $levelId;
    }

    public static function attach(Validator $validator, array $input): void
    {
        $validator->after(function (Validator $validator) use ($input) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = self::date($input['contact_start_date'] ?? null);
            $bidding = self::date($input['date_of_closing_of_sale'] ?? null);
            $expectedClose = self::date($input['sales_can_be_close'] ?? null);

            if ($start && $bidding && $bidding->lt($start)) {
                $validator->errors()->add(
                    'date_of_closing_of_sale',
                    'วัน Bidding ต้องไม่อยู่ก่อนวันเริ่มติดต่อ'
                );
            }

            if ($start && $expectedClose && $expectedClose->lt($start)) {
                $validator->errors()->add(
                    'sales_can_be_close',
                    'วันที่คาดว่าจะปิดการขายต้องไม่อยู่ก่อนวันเริ่มติดต่อ'
                );
            }

            if ($bidding && $expectedClose && $expectedClose->lt($bidding)) {
                $validator->errors()->add(
                    'sales_can_be_close',
                    'วันที่คาดว่าจะปิดการขายต้องไม่อยู่ก่อนวัน Bidding'
                );
            }

            $selected = collect($input['step'] ?? [])
                ->filter(fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN))
                ->keys()
                ->map(fn ($levelId) => (int) $levelId)
                ->values();

            if ($selected->isEmpty()) {
                return;
            }

            $steps = DB::table('step')
                ->whereIn('level_id', $selected)
                ->orderBy('orderlv')
                ->get(['level_id', 'level', 'orderlv']);

            $datedSteps = $steps->map(function ($step) use ($input) {
                return (object) [
                    'level_id' => (int) $step->level_id,
                    'level' => (string) $step->level,
                    'orderlv' => (int) $step->orderlv,
                    'date' => self::date($input['step_date'][$step->level_id] ?? null),
                ];
            })->filter(fn ($step) => $step->date !== null)->values();

            foreach ($datedSteps as $step) {
                if ($start && $step->date->lt($start)) {
                    $validator->errors()->add(
                        "step_date.{$step->level_id}",
                        "วันที่ {$step->level} ต้องไม่อยู่ก่อนวันเริ่มติดต่อ"
                    );
                }
            }

            $pipeline = $datedSteps->where('orderlv', '<=', 4)->values();
            for ($index = 1; $index < $pipeline->count(); $index++) {
                $previous = $pipeline[$index - 1];
                $current = $pipeline[$index];

                if ($current->date->lt($previous->date)) {
                    $validator->errors()->add(
                        "step_date.{$current->level_id}",
                        "วันที่ {$current->level} ต้องไม่อยู่ก่อน {$previous->level}"
                    );
                }
            }

            $latestPipelineDate = $pipeline->max(fn ($step) => $step->date?->getTimestamp());
            foreach ($datedSteps->where('orderlv', '>', 4) as $terminal) {
                if ($latestPipelineDate && $terminal->date->getTimestamp() < $latestPipelineDate) {
                    $validator->errors()->add(
                        "step_date.{$terminal->level_id}",
                        "วันที่ {$terminal->level} ต้องไม่อยู่ก่อนขั้นตอน Pipeline ก่อนหน้า"
                    );
                }
            }

            if ($datedSteps->where('orderlv', 5)->isNotEmpty() && $datedSteps->where('orderlv', 6)->isNotEmpty()) {
                $validator->errors()->add('step', 'โครงการหนึ่งรายการไม่สามารถเป็นทั้ง Win และ Lost พร้อมกันได้');
            }
        });
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', trim($value));
        } catch (\Throwable) {
            return null;
        }
    }
}
