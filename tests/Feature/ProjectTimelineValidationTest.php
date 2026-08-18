<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\ProjectTimelineValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ProjectTimelineValidationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_rejects_project_dates_before_contact_start(): void
    {
        $validator = $this->validator([
            'contact_start_date' => '2026-02-01',
            'date_of_closing_of_sale' => '2025-12-30',
            'sales_can_be_close' => '2026-03-01',
            'step' => [4 => '1'],
            'step_date' => [4 => '2025-10-31'],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('date_of_closing_of_sale', $validator->errors()->toArray());
        $this->assertArrayHasKey('step_date.4', $validator->errors()->toArray());
    }

    public function test_rejects_reversed_pipeline_and_terminal_dates(): void
    {
        $validator = $this->validator([
            'contact_start_date' => '2026-01-01',
            'date_of_closing_of_sale' => '2026-04-01',
            'sales_can_be_close' => '2026-05-01',
            'step' => [1 => '1', 4 => '1', 3 => '1', 5 => '1'],
            'step_date' => [
                1 => '2026-02-01',
                4 => '2026-03-01',
                3 => '2026-02-15',
                5 => '2026-02-20',
            ],
        ]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('step_date.3', $validator->errors()->toArray());
        $this->assertArrayHasKey('step_date.5', $validator->errors()->toArray());
    }

    public function test_accepts_a_chronological_project_timeline(): void
    {
        $validator = $this->validator([
            'contact_start_date' => '2026-01-01',
            'date_of_closing_of_sale' => '2026-04-01',
            'sales_can_be_close' => '2026-05-01',
            'step' => [1 => '1', 4 => '1', 3 => '1', 5 => '1'],
            'step_date' => [
                1 => '2026-01-15',
                4 => '2026-02-01',
                3 => '2026-04-01',
                5 => '2026-05-01',
            ],
        ]);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    public function test_latest_selected_step_uses_business_order_not_level_id(): void
    {
        $this->assertSame(3, ProjectTimelineValidator::latestSelectedStepId([
            'step' => [4 => '1', 3 => '1'],
        ]));
    }

    public function test_sales_create_endpoint_does_not_persist_an_invalid_timeline(): void
    {
        $before = DB::table('transactional')->count();
        $response = $this->actingAs(User::query()->where('user_id', 3)->firstOrFail())
            ->post(route('user.sales.store'), [
                'Product_detail' => 'Invalid timeline should not persist',
                'company_id' => 1,
                'product_value' => '100,000',
                'Source_budget_id' => 1,
                'fiscalyear' => 2026,
                'Product_id' => 1,
                'team_id' => 1,
                'priority_id' => 1,
                'contact_start_date' => '2026-02-01',
                'date_of_closing_of_sale' => '2025-12-30',
                'sales_can_be_close' => '2026-03-01',
                'step' => [4 => '1'],
                'step_date' => [4 => '2025-10-31'],
            ]);

        $response->assertSessionHasErrors(['date_of_closing_of_sale', 'step_date.4']);
        $this->assertSame($before, DB::table('transactional')->count());
    }

    private function validator(array $input)
    {
        $validator = Validator::make($input, [
            'contact_start_date' => 'required|date',
            'date_of_closing_of_sale' => 'nullable|date',
            'sales_can_be_close' => 'nullable|date',
            'step_date' => 'nullable|array',
            'step_date.*' => 'nullable|date',
        ]);

        ProjectTimelineValidator::attach($validator, $input);

        return $validator;
    }
}
