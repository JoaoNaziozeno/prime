<?php

namespace App\Http\Controllers\API\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    /**
     * Placeholder returned instead of secret values.
     */
    private const SECRET_MASK = '********';

    /**
     * Whether a setting holds a credential that must never be returned by the API.
     */
    private function isSecretKey(string $key): bool
    {
        return (bool) preg_match('/(secret|password|token|api_key|private_key|webhook_secret|credentials|cert)/i', $key);
    }

    /**
     * Display a listing of the settings.
     */
    public function index(Request $request): JsonResponse
    {
        $settings = Setting::all();
        
        $formatted = [];
        foreach ($settings as $setting) {
            if ($this->isSecretKey($setting->key)) {
                $hasValue = !empty($setting->value);
                $formatted[$setting->key] = [
                    'value' => $hasValue ? self::SECRET_MASK : null,
                    'type' => $setting->type,
                    'group' => $setting->group,
                    'secret' => true,
                ];
                continue;
            }

            $formatted[$setting->key] = [
                'value' => Setting::get($setting->key),
                'type' => $setting->type,
                'group' => $setting->group,
            ];
        }

        return response()->json($formatted);
    }

    /**
     * Update the settings in bulk.
     */
    public function update(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
            'settings.*.type' => 'nullable|string|in:string,boolean,integer,float,json',
            'settings.*.group' => 'nullable|string',
        ]);

        foreach ($request->settings as $item) {
            // A masked secret sent back by the client means "unchanged".
            if ($this->isSecretKey($item['key']) && ($item['value'] ?? null) === self::SECRET_MASK) {
                continue;
            }

            Setting::set(
                $item['key'],
                $item['value'],
                $item['type'] ?? null,
                $item['group'] ?? 'general'
            );
        }

        return response()->json(['message' => 'Configurações atualizadas com sucesso.']);
    }

    /**
     * Upload company logo.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Não autorizado.'], 403);
        }

        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $tenantId = tenant('id') ?? 'default';
        $path = $request->file('logo')->store("tenants/{$tenantId}/logo", 'public');

        if (!$path) {
            throw new \RuntimeException('Falha ao salvar o logotipo.');
        }

        // Save path in settings
        Setting::set('company_logo', Storage::url($path), 'string', 'general');

        return response()->json([
            'message' => 'Logotipo enviado com sucesso.',
            'logo_url' => Storage::url($path)
        ]);
    }

    /**
     * Get logo image with CDN cache headers.
     */
    public function getLogo(): \Symfony\Component\HttpFoundation\Response
    {
        $logoUrl = Setting::get('company_logo');

        if (!$logoUrl) {
            return response()->json(['message' => 'Nenhum logotipo cadastrado.'], 404);
        }

        // Parse path from storage url
        $path = str_replace('/storage/', '', $logoUrl);

        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Logotipo não encontrado.'], 404);
        }

        $file = Storage::disk('public')->get($path);
        $mime = Storage::disk('public')->mimeType($path);

        return response($file, 200)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'public, max-age=31536000, immutable');
    }
}
