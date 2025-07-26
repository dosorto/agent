<?php

use App\Models\Mensaje;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Services\ChatGptService;
use App\Services\WhatsAppService;
use Illuminate\Support\Carbon;

/*
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');*/

Route::post('/webhook/whatsapp', function (Request $request, ChatGptService $chatGpt, WhatsAppService $whatsapp) {
    //logger($request);
    $entry = $request->input('entry')[0];
    $changes = $entry['changes'][0]['value'] ?? null;
    $message = $changes['messages'][0] ?? null;
    logger($message);
    if ($message && $message['type'] === 'text') {
        $from = $message['from'];
        $text = $message['text']['body'];
        $id= $message['id'];
        

        //$history = Mensaje::find($from);
        $exist = Mensaje::where('mensaje_id', $id)->get()??null;
        logger($exist);
        if(count($exist)>=1){
            logger("Mensaje ya existia");

        }else{
            $history = Mensaje::where('telefono', $from)
            ->where('created_at', '>=', Carbon::now()->subHours(12))
            ->orderBy('created_at', 'asc')
            ->get()->toArray();
        
        $reply = $chatGpt->ask($text,$history);
        Mensaje::create([
            'telefono' => $from,
            'role' => 'user',
            'mensaje_id'=>$id,
            'content' => $text,
        ]);
        Mensaje::create([
            'telefono' => $from,
            'role' => 'assistant',
            'mensaje_id'=>$id,
            'content' => json_encode($reply, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ]);
        //logger($history);
        logger($reply);

        $whatsapp->sendMessage($from, $reply['respuesta']);
        if($reply['productos']){
            foreach($reply['productos'] as $p){
                $caption = $p['nombre']."\n". $p['precio']."\n". $p['descripcion'];
            $whatsapp->sendImage($from,$caption,$p['imagen']);
            }
            /*$whatsapp->sendMessage($from, "¿Desea ver más productos?");
            Mensaje::create([
                'telefono' => $from,
                'role' => 'system',
                'mensaje_id'=>$id,
                'content' => "¿Desea ver más productos?"
            ]);*/
        }

        }
        
    }
    return response()->json(['status' => 'ok']);
});

Route::get('/webhook/whatsapp', function (Request $request, WhatsAppService $whatsapp) {
    logger($request);
    //$whatsapp->sendMessage('50488493215', "hola");
    if (
        $request->query('hub_mode') === 'subscribe' &&
        $request->query('hub_verify_token') === env('WHATSAPP_VERIFY_TOKEN')
    ) {
        return response($request->query('hub_challenge'), 200)
        ->header('Content-Type', 'text/plain');;
    }
    return response('Invalid verify token', 403);
});
