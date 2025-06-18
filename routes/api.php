<?php

use App\Models\Mensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Services\ChatGptService;
use App\Services\WhatsAppService;

/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::post('/webhook/whatsapp', function (Request $request, ChatGptService $chatGpt, WhatsAppService $whatsapp) {
    $entry = $request->input('entry')[0];
    $changes = $entry['changes'][0]['value'] ?? null;
    $message = $changes['messages'][0] ?? null;
    if ($message && $message['type'] === 'text') {
        $from = $message['from'];
        $text = $message['text']['body'];
        Mensaje::create([
            'telefono' => $from,
            'role' => 'user',
            'content' => $text,
        ]);

        $history = Mensaje::find($from);
        $history = Mensaje::where('telefono', $from)
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->toArray();
        $reply = $chatGpt->ask($text,$history);
        Mensaje::create([
            'telefono' => $from,
            'role' => 'system',
            'content' => $reply,
        ]);
        //logger($history);
        //logger($reply);
        $whatsapp->sendMessage($from, $reply);
    }
    return response()->json(['status' => 'ok']);
});

Route::get('/webhook/whatsapp', function (Request $request) {
    if (
        $request->query('hub_mode') === 'subscribe' &&
        $request->query('hub_verify_token') === env('WHATSAPP_VERIFY_TOKEN')
    ) {
        return response($request->query('hub_challenge'), 200);
    }
    return response('Invalid verify token', 403);
});
