<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class RegistrationController extends Controller
{
    public function showRegistrationForm($token)
    {
        $user = User::where('reset_token', hash('sha256', $token))
            ->where('token_expiry', '>', now())
            ->first();

        if (!$user) {
            return redirect()->route('login')->with('error', 'ลิงก์เชิญหมดอายุหรือไม่ถูกต้อง');
        }

        return view('auth.register', compact('user', 'token'));
    }

    public function register(Request $request, $token)
    {
        $user = User::where('reset_token', hash('sha256', $token))
            ->where('token_expiry', '>', now())
            ->first();

        if (!$user) {
            return redirect()->route('login')->with('error', 'ลิงก์เชิญหมดอายุหรือไม่ถูกต้อง');
        }

        $request->validate([
            'password' => 'required|string|min:12|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
            'is_active' => 1,
            'reset_token' => null,
            'token_expiry' => null,
        ]);

        return redirect()->route('login')->with('success', 'ตั้งรหัสผ่านเรียบร้อยแล้ว กรุณาเข้าสู่ระบบ');
    }
}
