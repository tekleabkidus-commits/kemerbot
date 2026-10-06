<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConversionController extends Controller
{
    public function __invoke(Request $request)
    {
        $key = config('telegram.conversion_api_key');
        abort_unless(is_string($key) && strlen($key) >= 32 && hash_equals($key, (string) $request->bearerToken()), 401);
        $data = $request->validate(['external_id' => 'required|string|max:200', 'tg_chat_id' => 'required|integer', 'event' => 'required|in:registration,deposit,purchase', 'value' => 'nullable|numeric|min:0', 'currency' => 'nullable|in:ETB,USD,EUR', 'occurred_at' => 'required|date|before_or_equal:now', 'broadcast_id' => 'nullable|integer|exists:broadcasts,id']);
        $user = User::query()->where('tg_chat_id', $data['tg_chat_id'])->firstOrFail();
        if (! empty($data['broadcast_id'])) {
            abort_unless(DB::table('broadcast_recipients')->where('broadcast_id', $data['broadcast_id'])->where('user_id', $user->id)->exists(), 422);
        }
        $created = DB::transaction(function () use ($data, $user) {
            $created = DB::table('conversion_events')->insertOrIgnore(['external_id' => $data['external_id'], 'user_id' => $user->id, 'broadcast_id' => $data['broadcast_id'] ?? null, 'event' => $data['event'], 'value' => $data['value'] ?? 0, 'currency' => $data['currency'] ?? 'ETB', 'occurred_at' => Carbon::parse($data['occurred_at'])->utc(), 'created_at' => now(), 'updated_at' => now()]);
            if (! $created) {
                $existing = DB::table('conversion_events')->where('external_id', $data['external_id'])->first();
                abort_unless($existing->user_id === $user->id && $existing->event === $data['event'] && (int) $existing->broadcast_id === (int) ($data['broadcast_id'] ?? 0) && (float) $existing->value === (float) ($data['value'] ?? 0) && $existing->currency === ($data['currency'] ?? 'ETB'), 409, 'This external ID already belongs to a different event');
            }
            if ($created) {
                $user->update(['converted_at' => now()]);
            }

            return $created;
        });

        return response()->json(['ok' => true, 'created' => (bool) $created]);
    }
}
