<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline migration for legacy tables that were created outside of Laravel migrations.
 * Uses Schema::hasTable() to skip creation if tables already exist.
 * This serves as documentation of the original schema in version control.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. industry_group
        if (!Schema::hasTable('industry_group')) {
            Schema::create('industry_group', function (Blueprint $table) {
                $table->increments('Industry_id');
                $table->string('Industry', 200);
            });
        }

        // 2. role_catalog
        if (!Schema::hasTable('role_catalog')) {
            Schema::create('role_catalog', function (Blueprint $table) {
                $table->increments('role_id');
                $table->string('role', 50);
            });
        }

        // 3. position
        if (!Schema::hasTable('position')) {
            Schema::create('position', function (Blueprint $table) {
                $table->increments('position_id');
                $table->string('position', 200);
            });
        }

        // 4. team_catalog
        if (!Schema::hasTable('team_catalog')) {
            Schema::create('team_catalog', function (Blueprint $table) {
                $table->increments('team_id');
                $table->string('team', 100);
            });
        }

        // 5. company_catalog
        if (!Schema::hasTable('company_catalog')) {
            Schema::create('company_catalog', function (Blueprint $table) {
                $table->increments('company_id');
                $table->string('company', 255);
                $table->unsignedInteger('Industry_id');

                $table->foreign('Industry_id')->references('Industry_id')->on('industry_group');
            });
        }

        // 6. product_group
        if (!Schema::hasTable('product_group')) {
            Schema::create('product_group', function (Blueprint $table) {
                $table->increments('product_id');
                $table->string('product', 255);
            });
        }

        // 7. priority_level
        if (!Schema::hasTable('priority_level')) {
            Schema::create('priority_level', function (Blueprint $table) {
                $table->increments('priority_id');
                $table->string('priority', 100);
            });
        }

        // 8. source_of_the_budget
        if (!Schema::hasTable('source_of_the_budget')) {
            Schema::create('source_of_the_budget', function (Blueprint $table) {
                $table->increments('Source_budget_id');
                $table->string('Source_budge', 255);
            });
        }

        // 9. step
        if (!Schema::hasTable('step')) {
            Schema::create('step', function (Blueprint $table) {
                $table->increments('level_id');
                $table->string('level', 200);
                $table->integer('orderlv');
            });
        }

        // 10. user
        if (!Schema::hasTable('user')) {
            Schema::create('user', function (Blueprint $table) {
                $table->increments('user_id');
                $table->string('email', 50);
                $table->string('password', 50);
                $table->unsignedInteger('role_id');
                $table->string('nname', 200);
                $table->string('surename', 200);
                $table->string('avatar_path', 255);
                $table->integer('position_id')->nullable();
                $table->integer('forecast');
                $table->boolean('is_active')->default(false);
                $table->string('reset_token', 64);
                $table->datetime('token_expiry')->nullable();

                $table->foreign('role_id')->references('role_id')->on('role_catalog');
            });
        }

        // 11. transactional
        if (!Schema::hasTable('transactional')) {
            Schema::create('transactional', function (Blueprint $table) {
                $table->increments('transac_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('company_id');
                $table->unsignedInteger('Product_id');
                $table->string('Product_detail', 100);
                $table->integer('Step_id');
                $table->integer('Source_budget_id');
                $table->integer('fiscalyear');
                $table->integer('present');
                $table->date('present_date')->nullable();
                $table->integer('budgeted');
                $table->date('budgeted_date')->nullable();
                $table->integer('tor');
                $table->date('tor_date')->nullable();
                $table->integer('bidding');
                $table->date('bidding_date')->nullable();
                $table->integer('win');
                $table->date('win_date')->nullable();
                $table->integer('lost');
                $table->date('lost_date')->nullable();
                $table->unsignedInteger('team_id');
                $table->date('contact_start_date')->nullable();
                $table->date('date_of_closing_of_sale')->nullable();
                $table->date('sales_can_be_close')->nullable();
                $table->unsignedInteger('priority_id');
                $table->double('product_value');
                $table->string('remark', 255);
                $table->timestamp('timestamp')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('user_id')->references('user_id')->on('user');
                $table->foreign('company_id')->references('company_id')->on('company_catalog');
                $table->foreign('Product_id')->references('product_id')->on('product_group');
                $table->foreign('team_id')->references('team_id')->on('team_catalog');
                $table->foreign('priority_id')->references('priority_id')->on('priority_level');
            });
        }

        // 12. transactional_step
        if (!Schema::hasTable('transactional_step')) {
            Schema::create('transactional_step', function (Blueprint $table) {
                $table->increments('transacstep_id');
                $table->unsignedInteger('transac_id');
                $table->unsignedInteger('level_id');
                $table->date('date')->nullable();
                $table->timestamp('checkdate')->useCurrent()->useCurrentOnUpdate();

                $table->foreign('transac_id')->references('transac_id')->on('transactional');
                $table->foreign('level_id')->references('level_id')->on('step');
            });
        }

        // 13. transactional_team
        if (!Schema::hasTable('transactional_team')) {
            Schema::create('transactional_team', function (Blueprint $table) {
                $table->increments('transacteam_id');
                $table->unsignedInteger('user_id');
                $table->unsignedInteger('team_id');

                $table->foreign('team_id')->references('team_id')->on('team_catalog');
            });
        }

        // 14. company_requests
        if (!Schema::hasTable('company_requests')) {
            Schema::create('company_requests', function (Blueprint $table) {
                $table->increments('request_id');
                $table->string('company_name', 255);
                $table->text('notes')->nullable();
                $table->integer('user_id');
                $table->datetime('request_date')->useCurrent();
                $table->string('status', 20)->default('pending');

                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        // Drop in reverse order to respect FK constraints
        Schema::dropIfExists('company_requests');
        Schema::dropIfExists('transactional_team');
        Schema::dropIfExists('transactional_step');
        Schema::dropIfExists('transactional');
        Schema::dropIfExists('user');
        Schema::dropIfExists('step');
        Schema::dropIfExists('source_of_the_budget');
        Schema::dropIfExists('priority_level');
        Schema::dropIfExists('product_group');
        Schema::dropIfExists('company_catalog');
        Schema::dropIfExists('team_catalog');
        Schema::dropIfExists('position');
        Schema::dropIfExists('role_catalog');
        Schema::dropIfExists('industry_group');
    }
};
