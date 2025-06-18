<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class ChatGptService
{
    public function ask(string $question): string
    {
        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1-nano',
                'messages' => [
                    ['role' => 'system', 'content' => 'Eres un asesor de ventas que recomienda productos.'],
                    ['role' => 'user', 'content' => $question],
                ],
            ]);

            logger($response);

        return $response['choices'][0]['message']['content'] ?? 'No entendí tu pregunta.';
    }
}
