<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\PasswordResetLink;
use App\Mail\TwoFactorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetPasswordLink(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user) {
            $token = Str::random(64);

            $user->update([
                'reset_token' => hash('sha256', $token),
                'token_expiry' => now()->addHour(),
            ]);

            Mail::to($user->email)->send(new PasswordResetLink($user, $token));
        }

        return back()->with('success', 'หากอีเมลนี้มีอยู่ในระบบ เราได้ส่งลิงก์สำหรับรีเซ็ตรหัสผ่านให้แล้ว');
    }

    public function showResetPasswordForm(string $token, Request $request)
    {
        $email = (string) $request->query('email', '');

        $user = User::query()
            ->where('email', $email)
            ->where('reset_token', hash('sha256', $token))
            ->where('token_expiry', '>', now())
            ->first();

        if (!$user) {
            return redirect()->route('password.request')->with('error', 'ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุ');
        }

        return view('auth.reset-password', compact('token', 'email'));
    }

    public function resetPassword(string $token, Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $user = User::query()
            ->where('email', $data['email'])
            ->where('reset_token', hash('sha256', $token))
            ->where('token_expiry', '>', now())
            ->first();

        if (!$user) {
            return redirect()->route('password.request')->with('error', 'ลิงก์รีเซ็ตรหัสผ่านไม่ถูกต้องหรือหมดอายุ');
        }

        $user->update([
            'password' => Hash::make($data['password']),
            'reset_token' => '',
            'token_expiry' => null,
        ]);

        return redirect()->route('login')->with('success', 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว กรุณาเข้าสู่ระบบ');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if (!$user) {
            return back()->withErrors(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'])->onlyInput('email');
        }

        // Check if user is active
        if (!$user->is_active) {
            return back()->withErrors(['email' => 'บัญชีของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ'])->onlyInput('email');
        }

        $rawPassword = $data['password'];
        $stored = (string) ($user->password ?? '');

        $valid = false;

        $usingLegacyMd5 = false;

        if ($stored !== '' && str_starts_with($stored, '$2y$')) {
            $valid = Hash::check($rawPassword, $stored);
        } elseif ($stored !== '') {
            $valid = hash_equals(md5($rawPassword), $stored);
            $usingLegacyMd5 = $valid;
        }

        if (!$valid) {
            return back()->withErrors(['email' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'])->onlyInput('email');
        }

        // Silently upgrade MD5 hash to bcrypt on successful login
        if ($usingLegacyMd5) {
            $user->update(['password' => Hash::make($rawPassword)]);
        }

        // Check if 2FA is enabled
        if ($user->hasTwoFactorEnabled()) {
            // Check if OTP already exists and not expired
            if ($user->two_factor_code && $user->two_factor_expires_at && now()->isBefore($user->two_factor_expires_at)) {
                // OTP still valid, don't send new one
                $request->session()->put('2fa_user_id', $user->user_id);
                return redirect()->route('2fa.verify')->with('success', 'รหัส OTP ถูกส่งไปยัง email ของคุณแล้ว');
            }
            
            // Generate OTP and send email
            $code = $user->generateTwoFactorCode();
            Mail::to($user->email)->send(new TwoFactorCode($user, $code));
            
            // Store user_id in session (not logged in yet)
            $request->session()->put('2fa_user_id', $user->user_id);
            
            return redirect()->route('2fa.verify')->with('success', 'รหัส OTP ถูกส่งไปยัง email ของคุณแล้ว');
        }

        // If 2FA not enabled, login normally
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('postlogin.loading');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showTwoFactorVerify(Request $request)
    {
        if (!$request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }
        
        $userId = $request->session()->get('2fa_user_id');
        $user = User::find($userId);
        
        if (!$user) {
            $request->session()->forget('2fa_user_id');
            return redirect()->route('login')->with('error', 'Session หมดอายุ กรุณา login ใหม่');
        }
        
        return view('auth.two-factor-verify', compact('user'));
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);
        
        if (!$request->session()->has('2fa_user_id')) {
            return redirect()->route('login')->with('error', 'Session หมดอายุ กรุณา login ใหม่');
        }
        
        $userId = $request->session()->get('2fa_user_id');
        $user = User::find($userId);
        
        if (!$user) {
            $request->session()->forget('2fa_user_id');
            return redirect()->route('login')->with('error', 'Session หมดอายุ กรุณา login ใหม่');
        }
        
        if (!$user->verifyTwoFactorCode($request->code)) {
            return back()->withErrors(['code' => 'รหัส OTP ไม่ถูกต้องหรือหมดอายุ']);
        }
        
        // OTP correct - clear code and login
        $user->resetTwoFactorCode();
        $request->session()->forget('2fa_user_id');
        
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('postlogin.loading');
    }

    public function postLoginLoading(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('login');
        }

        $targetUrl = match ((int) $user->role_id) {
            1 => route('admin.dashboard'),
            2 => route('teamadmin.dashboard'),
            3 => route('user.dashboard'),
            default => route('login'),
        };

        return view('auth.post-login-loading', compact('targetUrl'));
    }

    public function resendTwoFactorCode(Request $request)
    {
        if (!$request->session()->has('2fa_user_id')) {
            return redirect()->route('login');
        }
        
        $userId = $request->session()->get('2fa_user_id');
        $user = User::find($userId);
        
        if (!$user) {
            $request->session()->forget('2fa_user_id');
            return redirect()->route('login')->with('error', 'Session หมดอายุ กรุณา login ใหม่');
        }
        
        // A code expires after five minutes. Allow a resend after the first minute.
        $resendAvailableAt = $user->two_factor_expires_at?->copy()->subMinutes(4);
        if ($resendAvailableAt && now()->isBefore($resendAvailableAt)) {
            return back()->with('error', 'กรุณารอสักครู่ก่อนขอรหัส OTP ใหม่');
        }
        
        // Generate new OTP and send email
        $code = $user->generateTwoFactorCode();
        Mail::to($user->email)->send(new TwoFactorCode($user, $code));
        
        return back()->with('success', 'ส่งรหัส OTP ใหม่ไปยัง email ของคุณแล้ว');
    }
}
