<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MigrationController extends Controller
{
    public function index()
    {
        $status = $this->getMigrationStatus();
        
        $totalMigrations = count($status);
        $completedMigrations = collect($status)->where('status', 'completed')->count();
        $pendingMigrations = collect($status)->where('status', 'pending')->count();
        
        try {
            DB::connection()->getPdo();
            $dbConnected = true;
        } catch (\Exception $e) {
            $dbConnected = false;
        }
        
        return view('admin.migration.index', compact(
            'status',
            'totalMigrations',
            'completedMigrations',
            'pendingMigrations',
            'dbConnected'
        ));
    }

    public function status()
    {
        $status = $this->getMigrationStatus();
        
        return response()->json([
            'success' => true,
            'migrations' => $status
        ]);
    }

    public function run(Request $request)
    {
        if (app()->isProduction()) {
            return response()->json(['success' => false, 'message' => 'ไม่อนุญาตให้รัน Migration ผ่านเว็บในสภาพแวดล้อม Production'], 403);
        }

        try {
            ob_start();

            Artisan::call('migrate', ['--force' => true]);
            
            $output = Artisan::output();
            ob_end_clean();
            
            $this->logMigration('run_all', null, $output, 'success');
            
            return response()->json([
                'success' => true,
                'message' => 'Migrations completed successfully',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            report($e);
            
            $this->logMigration('run_all', null, $e->getMessage(), 'failed');
            
            return response()->json([
                'success' => false,
                'message' => 'Migration failed. Check the server log for details.'
            ], 500);
        }
    }

    public function runSingle(Request $request, $migration)
    {
        if (app()->isProduction()) {
            return response()->json(['success' => false, 'message' => 'ไม่อนุญาตให้รัน Migration ผ่านเว็บในสภาพแวดล้อม Production'], 403);
        }

        $allowedMigrations = $this->getMigrationFiles();
        if (!in_array($migration, $allowedMigrations, true)) {
            return response()->json(['success' => false, 'message' => 'Migration ไม่ถูกต้อง'], 422);
        }

        try {
            ob_start();

            Artisan::call('migrate', [
                '--path' => 'database/migrations/' . $migration . '.php',
                '--force' => true
            ]);
            
            $output = Artisan::output();
            ob_end_clean();
            
            $this->logMigration('run_single', $migration, $output, 'success');
            
            return response()->json([
                'success' => true,
                'message' => 'Migration completed successfully',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            report($e);
            
            $this->logMigration('run_single', $migration, $e->getMessage(), 'failed');
            
            return response()->json([
                'success' => false,
                'message' => 'Migration failed. Check the server log for details.'
            ], 500);
        }
    }

    public function rollback(Request $request)
    {
        if (app()->isProduction()) {
            return response()->json(['success' => false, 'message' => 'ไม่อนุญาตให้ Rollback ผ่านเว็บในสภาพแวดล้อม Production'], 403);
        }

        $request->validate([
            'confirm' => 'required|accepted'
        ]);
        
        try {
            ob_start();
            
            Artisan::call('migrate:rollback', ['--force' => true]);
            
            $output = Artisan::output();
            ob_end_clean();
            
            $this->logMigration('rollback', null, $output, 'success');
            
            return response()->json([
                'success' => true,
                'message' => 'Rollback completed successfully',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            ob_end_clean();
            report($e);
            
            $this->logMigration('rollback', null, $e->getMessage(), 'failed');
            
            return response()->json([
                'success' => false,
                'message' => 'Rollback failed. Check the server log for details.'
            ], 500);
        }
    }

    public function schema()
    {
        if (app()->isProduction()) {
            return response()->json(['success' => false, 'message' => 'ไม่อนุญาตให้ดู Schema ผ่านเว็บในสภาพแวดล้อม Production'], 403);
        }

        try {
            $tables = DB::select('SHOW TABLES');
            $databaseName = DB::getDatabaseName();
            $tableKey = 'Tables_in_' . $databaseName;

            $schema = [];
            foreach ($tables as $table) {
                $tableName = $table->$tableKey;
                $escapedName = '`' . str_replace('`', '``', $tableName) . '`';
                $columns = DB::select("DESCRIBE {$escapedName}");
                $indexes = DB::select("SHOW INDEX FROM {$escapedName}");
                $rowCount = DB::table($tableName)->count();
                
                $schema[] = [
                    'name' => $tableName,
                    'columns' => $columns,
                    'indexes' => $indexes,
                    'row_count' => $rowCount
                ];
            }
            
            return response()->json([
                'success' => true,
                'schema' => $schema
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve schema. Check the server log for details.'
            ], 500);
        }
    }

    public function logs()
    {
        try {
            $logs = DB::table('migration_logs')
                ->join('user', 'migration_logs.user_id', '=', 'user.user_id')
                ->select('migration_logs.*', 'user.nname', 'user.surename')
                ->orderBy('migration_logs.created_at', 'desc')
                ->limit(50)
                ->get();
            
            return response()->json([
                'success' => true,
                'logs' => $logs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'logs' => [],
                'message' => 'Migration logs table not yet created'
            ]);
        }
    }

    private function getMigrationStatus()
    {
        try {
            $ran = DB::table('migrations')->orderBy('batch')->get();
        } catch (\Exception $e) {
            $ran = collect();
        }
        
        $files = $this->getMigrationFiles();
        
        $status = [];
        foreach ($files as $file) {
            $migration = $ran->firstWhere('migration', $file);
            $status[] = [
                'file' => $file,
                'batch' => $migration->batch ?? null,
                'ran_at' => $migration->created_at ?? null,
                'status' => $migration ? 'completed' : 'pending'
            ];
        }
        
        return $status;
    }

    private function getMigrationFiles()
    {
        $path = database_path('migrations');
        $files = File::files($path);
        
        $migrations = [];
        foreach ($files as $file) {
            $migrations[] = str_replace('.php', '', $file->getFilename());
        }
        
        sort($migrations);
        
        return $migrations;
    }

    private function logMigration($action, $migrationName, $output, $status)
    {
        try {
            DB::table('migration_logs')->insert([
                'user_id' => auth()->user()->user_id,
                'action' => $action,
                'migration_name' => $migrationName,
                'output' => $output,
                'status' => $status,
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            // Silently fail if migration_logs table doesn't exist yet
        }
    }
}
