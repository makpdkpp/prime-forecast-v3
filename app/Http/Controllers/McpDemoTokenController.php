<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class McpDemoTokenController extends Controller
{
    private const TOKEN_NAME = 'prime-forecast-mcp-demo';
    private const TOKEN_ABILITY = 'mcp:read';
    private const TOKEN_TTL_MINUTES = 60;

    public function show(Request $request)
    {
        $this->ensureDemoEnabled($request);

        return view('mcp.demo-token', [
            'hasToken' => $request->user()->tokens()->where('name', self::TOKEN_NAME)->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureDemoEnabled($request);

        $user = $request->user();
        abort_unless($user->hasTwoFactorEnabled(), 403, 'ต้องเปิดใช้งาน 2FA ก่อนออก Demo MCP Token');

        // A user can have only one active demo token. Never log or persist the
        // plain-text value; Sanctum stores only its hash in personal_access_tokens.
        $user->tokens()->where('name', self::TOKEN_NAME)->delete();

        $expiresAt = now()->addMinutes(self::TOKEN_TTL_MINUTES);
        $token = $user->createToken(
            self::TOKEN_NAME,
            [self::TOKEN_ABILITY],
            $expiresAt,
        );

        return response()
            ->view('mcp.demo-token-created', [
                'plainTextToken' => $token->plainTextToken,
                'expiresAt' => $expiresAt,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    public function destroy(Request $request)
    {
        $this->ensureDemoEnabled($request);

        $request->user()->tokens()->where('name', self::TOKEN_NAME)->delete();

        return redirect()
            ->route('mcp-demo-token.show')
            ->with('success', 'ยกเลิก Demo MCP Token แล้ว');
    }

    private function ensureDemoEnabled(Request $request): void
    {
        abort_unless(
            app()->environment('staging')
                && (bool) config('services.prime_mcp.demo_token_issuer_enabled'),
            404,
        );
    }
}
