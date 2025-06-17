<?php

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

        $reply = $chatGpt->ask($text);
        $whatsapp->sendMessage($from, $reply);
    }

    return response()->json(['status' => 'ok']);
});

Route::get('/webhook/whatsapp', function (Request $request) {
    if ($request->input('hub.verify_token') === env('WHATSAPP_VERIFY_TOKEN')) {
        return response($request->input('hub.challenge'), 200);
    }
    return response('Invalid verify token', 403);
});
