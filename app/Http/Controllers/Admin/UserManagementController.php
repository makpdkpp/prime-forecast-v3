<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'position'])
            ->orderBy('nname')
            ->get();
        
        return view('admin.master.users.index', compact('users'));
    }

    public function create()
    {
        $roles = DB::table('role_catalog')->orderBy('role')->get();
        $positions = DB::table('position')->orderBy('position')->get();
        $teams = DB::table('team_catalog')->orderBy('team')->get();
        
        return view('admin.master.users.create', compact('roles', 'positions', 'teams'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:user,email',
            'nname' => 'required|string|max:255',
            'surename' => 'required|string|max:255',
            'role_id' => 'required|integer',
            'position_id' => 'required|integer',
            'targets' => 'nullable|array',
            'targets.*.year' => 'required_with:targets|string|max:10',
            'targets.*.value' => 'required_with:targets|numeric|min:0',
        ]);

        // Generate invitation token
        $token = bin2hex(random_bytes(32));
        $tokenExpiry = now()->addDays(7);

        $user = User::create([
            'email' => $request->email,
            'password' => Hash::make(bin2hex(random_bytes(16))), // Temporary random password
            'nname' => $request->nname,
            'surename' => $request->surename,
            'role_id' => $request->role_id,
            'position_id' => $request->position_id,
            'is_active' => 0, // Inactive until user sets password
            'reset_token' => hash('sha256', $token),
            'token_expiry' => $tokenExpiry,
            'avatar_path' => null,
            'two_factor_enabled' => 0,
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        // Assign teams if provided
        if ($request->has('teams') && is_array($request->teams)) {
            foreach ($request->teams as $teamId) {
                DB::table('transactional_team')->insert([
                    'team_id' => $teamId,
                    'user_id' => $user->user_id,
                ]);
            }
        }
        
        // Save forecast targets for multiple years
        if ($request->has('targets') && is_array($request->targets)) {
            foreach ($request->targets as $target) {
                if (!empty($target['year']) && isset($target['value'])) {
                    \App\Models\UserForecastTarget::create([
                        'user_id' => $user->user_id,
                        'fiscal_year' => $target['year'],
                        'target_value' => $target['value'],
                    ]);
                }
            }
        }

        // Send invitation email
        $invitationUrl = url('/register/' . $token);
        \Mail::to($user->email)->send(new \App\Mail\UserInvitation($user, $invitationUrl));

        return redirect()->route('admin.users.index')->with('success', 'เพิ่มผู้ใช้งานและส่งอีเมลเชิญเรียบร้อยแล้ว');
    }

    public function edit($id)
    {
        $user = User::findOrFail($id);
        $roles = DB::table('role_catalog')->orderBy('role')->get();
        $positions = DB::table('position')->orderBy('position')->get();
        $teams = DB::table('team_catalog')->orderBy('team')->get();
        $userTeams = DB::table('transactional_team')
            ->where('user_id', $id)
            ->pluck('team_id')
            ->toArray();
        
        // Load forecast targets for all years
        $userTargets = \App\Models\UserForecastTarget::where('user_id', $id)
            ->orderBy('fiscal_year', 'desc')
            ->get();
        
        return view('admin.master.users.edit', compact('user', 'roles', 'positions', 'teams', 'userTeams', 'userTargets'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $request->validate([
            'email' => 'required|email|unique:user,email,' . $id . ',user_id',
            'password' => 'nullable|string|min:12',
            'nname' => 'required|string|max:255',
            'surename' => 'required|string|max:255',
            'role_id' => 'required|integer',
            'position_id' => 'required|integer',
            'targets' => 'nullable|array',
            'targets.*.year' => 'required_with:targets|string|max:10',
            'targets.*.value' => 'required_with:targets|numeric|min:0',
        ]);

        $updateData = [
            'email' => $request->email,
            'nname' => $request->nname,
            'surename' => $request->surename,
            'role_id' => $request->role_id,
            'position_id' => $request->position_id,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        // Update teams
        DB::table('transactional_team')->where('user_id', $id)->delete();
        if ($request->has('teams') && is_array($request->teams)) {
            foreach ($request->teams as $teamId) {
                DB::table('transactional_team')->insert([
                    'team_id' => $teamId,
                    'user_id' => $id,
                ]);
            }
        }
        
        // Update forecast targets
        // Delete existing targets and recreate
        \App\Models\UserForecastTarget::where('user_id', $id)->delete();
        if ($request->has('targets') && is_array($request->targets)) {
            foreach ($request->targets as $target) {
                if (!empty($target['year']) && isset($target['value'])) {
                    \App\Models\UserForecastTarget::create([
                        'user_id' => $id,
                        'fiscal_year' => $target['year'],
                        'target_value' => $target['value'],
                    ]);
                }
            }
        }

        return redirect()->route('admin.users.index')->with('success', 'อัพเดทข้อมูลผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        
        // Delete user teams
        DB::table('transactional_team')->where('user_id', $id)->delete();
        
        // Delete user
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'ลบผู้ใช้งานเรียบร้อยแล้ว');
    }

    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        
        // Prevent admin from disabling themselves
        if ($user->user_id === auth()->id()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'ไม่สามารถปิดใช้งานบัญชีของตัวเองได้');
        }
        
        $user->is_active = !$user->is_active;
        $user->save();
        
        $status = $user->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
        return redirect()->route('admin.users.index')
            ->with('success', "เปลี่ยนสถานะผู้ใช้ {$user->nname} เป็น {$status} แล้ว");
    }
}
