<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class McpOAuthController extends Controller
{
    private const SCOPE = 'mcp:read';

    public function protectedResource(): JsonResponse
    {
        return response()->json([
            'resource' => $this->resource(),
            'authorization_servers' => [$this->issuer()],
            'scopes_supported' => [self::SCOPE],
        ]);
    }

    public function authorizationServer(): JsonResponse
    {
        $issuer = $this->issuer();

        return response()->json([
            'issuer' => $issuer,
            'authorization_response_iss_parameter_supported' => true,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'registration_endpoint' => $issuer.'/oauth/register',
            'client_id_metadata_document_supported' => false,
            'token_endpoint_auth_methods_supported' => ['none'],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => [self::SCOPE],
        ]);
    }

    public function register(Request $request): JsonResponse
    {
        abort_unless((bool) config('services.prime_mcp.oauth_enabled'), 404);
        $validator = Validator::make($request->all(), [
            'client_name' => ['required', 'string', 'max:150'],
            'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
            'redirect_uris.*' => ['required', 'url', 'max:2048'],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'invalid_client_metadata', 'details' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        foreach ($data['redirect_uris'] as $redirectUri) {
            abort_unless($this->isAllowedRedirectUri($redirectUri), 400, 'Redirect URI is not allowed.');
        }
        $clientId = 'mcp_'.Str::lower(Str::random(40));
        DB::table('mcp_oauth_clients')->insert([
            'client_id' => $clientId,
            'client_name' => $data['client_name'],
            'redirect_uris' => json_encode(array_values($data['redirect_uris']), JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json([
            'client_id' => $clientId,
            'client_id_issued_at' => now()->timestamp,
            'redirect_uris' => array_values($data['redirect_uris']),
            'token_endpoint_auth_method' => 'none',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
        ], 201);
    }

    public function authorizeRequest(Request $request): RedirectResponse
    {
        abort_unless((bool) config('services.prime_mcp.oauth_enabled'), 404);
        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'url', 'max:2048'],
            'response_type' => ['required', 'in:code'],
            'code_challenge' => ['required', 'string', 'max:128'],
            'code_challenge_method' => ['required', 'in:S256'],
            'scope' => ['nullable', 'string', 'max:255'],
            'resource' => ['nullable', 'url', 'max:2048'],
            'state' => ['nullable', 'string', 'max:2048'],
        ]);
        $client = DB::table('mcp_oauth_clients')->where('client_id', $data['client_id'])->first();
        abort_unless($client, 400, 'Unknown OAuth client.');
        $redirectUris = json_decode((string) $client->redirect_uris, true, 512, JSON_THROW_ON_ERROR);
        abort_unless(in_array($data['redirect_uri'], $redirectUris, true), 400, 'Redirect URI mismatch.');
        $requestedScopes = preg_split('/\s+/', trim((string) ($data['scope'] ?? self::SCOPE)), -1, PREG_SPLIT_NO_EMPTY);
        abort_unless(in_array(self::SCOPE, $requestedScopes, true), 400, 'The mcp:read scope is required.');
        $requestedResource = rtrim((string) ($data['resource'] ?? $this->resource()), '/');
        abort_unless($requestedResource === $this->resource() || $requestedResource === $this->resource().'/mcp', 400, 'Resource mismatch.');
        if (! $request->user()) {
            $request->session()->put('mcp_oauth_return_to', $request->fullUrl());

            return redirect()->route('login');
        }
        $plainCode = Str::random(96);
        DB::table('mcp_oauth_authorization_codes')->insert([
            'code_hash' => hash('sha256', $plainCode), 'client_id' => $data['client_id'],
            'user_id' => $request->user()->getAuthIdentifier(), 'redirect_uri' => $data['redirect_uri'],
            'code_challenge' => $data['code_challenge'], 'scope' => self::SCOPE,
            'resource' => $this->resource(), 'expires_at' => now()->addMinutes(5),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return redirect()->away($this->withQuery($data['redirect_uri'], [
            'code' => $plainCode,
            'state' => $data['state'] ?? null,
            'iss' => $this->issuer(),
        ]));
    }

    public function token(Request $request): JsonResponse
    {
        abort_unless((bool) config('services.prime_mcp.oauth_enabled'), 404);

        return match ((string) $request->input('grant_type')) {
            'authorization_code' => $this->authorizationCode($request),
            'refresh_token' => $this->refreshToken($request),
            default => response()->json(['error' => 'unsupported_grant_type'], 400),
        };
    }

    private function authorizationCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string'], 'client_id' => ['required', 'string', 'max:255'],
            'redirect_uri' => ['required', 'url', 'max:2048'], 'code_verifier' => ['required', 'string', 'max:128'],
            'resource' => ['required', 'url', 'max:2048'],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'invalid_request', 'details' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        if ($this->normalizeResource($data['resource']) !== $this->resource()) {
            return response()->json(['error' => 'invalid_target'], 400);
        }
        $record = DB::table('mcp_oauth_authorization_codes')->where('code_hash', hash('sha256', $data['code']))->where('client_id', $data['client_id'])->first();
        if (! $record || $record->used_at || now()->greaterThan($record->expires_at) || $record->redirect_uri !== $data['redirect_uri']) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }
        $expected = rtrim(strtr(base64_encode(hash('sha256', $data['code_verifier'], true)), '+/', '-_'), '=');
        if (! hash_equals((string) $record->code_challenge, $expected)) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }
        DB::table('mcp_oauth_authorization_codes')->where('code_hash', $record->code_hash)->update(['used_at' => now(), 'updated_at' => now()]);

        if ($this->normalizeResource((string) $record->resource) !== $this->resource()) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }

        return $this->issueTokens((int) $record->user_id, (string) $record->client_id, (string) $record->scope, $this->resource());
    }

    private function refreshToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => ['required', 'string'], 'client_id' => ['required', 'string', 'max:255'],
            'resource' => ['required', 'url', 'max:2048'],
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => 'invalid_request', 'details' => $validator->errors()], 422);
        }
        $data = $validator->validated();
        if ($this->normalizeResource($data['resource']) !== $this->resource()) {
            return response()->json(['error' => 'invalid_target'], 400);
        }
        $record = DB::table('mcp_oauth_refresh_tokens')->where('token_hash', hash('sha256', $data['refresh_token']))->where('client_id', $data['client_id'])->whereNull('revoked_at')->first();
        if (! $record || now()->greaterThan($record->expires_at)) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }
        DB::table('mcp_oauth_refresh_tokens')->where('token_hash', $record->token_hash)->update(['revoked_at' => now(), 'updated_at' => now()]);

        return $this->issueTokens((int) $record->user_id, (string) $record->client_id, (string) $record->scope, $this->resource());
    }

    private function issueTokens(int $userId, string $clientId, string $scope, string $resource): JsonResponse
    {
        $user = User::find($userId);
        if (! $user || ! $user->is_active) {
            return response()->json(['error' => 'invalid_grant'], 400);
        }
        $expiresAt = now()->addHour();
        $access = $user->createToken('prime-forecast-chatgpt', [$scope], $expiresAt);
        $refresh = Str::random(96);
        DB::table('mcp_oauth_refresh_tokens')->insert([
            'token_hash' => hash('sha256', $refresh), 'client_id' => $clientId, 'user_id' => $userId,
            'scope' => $scope, 'expires_at' => now()->addDays(30), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json([
            'access_token' => $access->plainTextToken, 'token_type' => 'Bearer',
            'expires_in' => now()->diffInSeconds($expiresAt), 'refresh_token' => $refresh, 'scope' => $scope,
            'resource' => $resource,
        ])->header('Cache-Control', 'no-store');
    }

    private function isAllowedRedirectUri(string $uri): bool
    {
        $parts = parse_url($uri);
        $host = strtolower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? '') === 'https' && ($host === 'chatgpt.com' || str_ends_with($host, '.chatgpt.com') || $host === 'openai.com' || str_ends_with($host, '.openai.com'));
    }

    private function withQuery(string $uri, array $params): string
    {
        return $uri.(str_contains($uri, '?') ? '&' : '?').http_build_query(array_filter($params, static fn ($value) => $value !== null));
    }

    private function resource(): string
    {
        return rtrim((string) config('services.prime_mcp.oauth_resource'), '/');
    }

    private function normalizeResource(string $resource): string
    {
        return rtrim($resource, '/');
    }

    private function issuer(): string
    {
        return rtrim((string) config('services.prime_mcp.oauth_issuer'), '/');
    }
}
