<?php
namespace App\Services;

use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Facades\Http;

class ChatGptService
{
    public function ask(string $question, $history): string
    {
        if (!collect($history)->contains('role', 'system')) {
            array_unshift($history, [
                'role' => 'system',
                'content' => 'Eres un asesor de ventas que recomienda productos solo de la tienda grupoinfinitum de la pagina https://grupoinfinitum.hn, 
                tienes la posibilidad de acceder al inventario de la tienda por medio de una api, analiza los productos que te devuelve la api y omite 
                aquellos que no es lo que el cliente esta solicitando y ademas ten en cuenta que la información viene con paginación y si el cliente 
                solicita que muestres mas producto, favor muestra los siguientes de la pagina. No consultes la api hasta que tengas definido con el cliente que 
                tipo de producto busca, por ejemplo debe definir tallas, color u otras caracteristicas, cuando definas la consulta a usar en la API 
                usa palabras clave por ejemplo: pantalon negro , pantalon, camisa blaco. siempre utiliza como maximo tres palabras claves. 
                Identifique es lo que el cliente quiere para que consultes la API con palabras claves mas acertadas evitando que se le diga al cliente que no hay producto 
                que buscas, nunca debes decir que no tiene en inventario lo que busca, debes sugerir otras opciones buscando con otras palabras claves realacionadas a las que 
                el cliente definió. en el caso de los colores cuando los identifiques como palabras claves evita usar adjetivo y simpre debes usar el nombre del color ej. no usar blanca debes usar blanco'
            ]);
        }
        $history[] = ['role' => 'user', 'content' => $question];

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1-nano',
                'messages' => $history,
                'functions' => [
                [
                    'name' => 'buscarProductos',
                    'description' => 'Busca productos según una palabra clave, omite aquellos productos que no es lo que el cliente esta solicitando y ademas ten en cuenta que la información viene con paginación y si el cliente solicita que muestres mas producto, favor muestra los siguientes de la pagina',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Palabra clave como "camisa", "pantalon", etc.'
                            ]
                        ],
                        'required' => ['query']
                    ]
                ]
            ],
            'function_call' => 'auto',
            ]);

            $choice = $response['choices'][0];
            if ($choice['finish_reason'] === 'function_call') {
                $functionCall = $choice['message']['function_call'];
                if ($functionCall['name'] === 'buscarProductos') {
                    $args = json_decode($functionCall['arguments'], true);
                    $query = $args['query'] ?? '';
        
                    // Llamada a tu API
                    logger("LLAMO A LA API");
                    $productResponse = Http::withHeaders([
                        'Accept'=>'application/json',
                        'content-type'=>'application/json',
                        'x-store' => 1, // cambia esto según lo que necesites
                    ])->get('https://grupoinfinitum.hn/server/api/e-comerce/searchproducto', [
                        'q' => $query
                    ]);
                    logger("Query: ".$query);
                    
        
                    $products = $productResponse->json()['data']['data'] ?? [];
                    logger($products);
        
                    // Devuelve la respuesta a ChatGPT como resultado de la función
                    $history[] = $choice['message'];
                    $history[] = [
                        'role' => 'function',
                        'name' => 'buscarProductos',
                        'content' => json_encode($products),
                    ];
        
                    $finalResponse = Http::withToken(env('OPENAI_API_KEY'))
                        ->post('https://api.openai.com/v1/chat/completions', [
                            'model' => 'gpt-4.1-nano',
                            'messages' => $history,
                        ]);
        
                    return $finalResponse['choices'][0]['message']['content'] ?? 'No encontré productos.';
                }
            }

        return $response['choices'][0]['message']['content'] ?? 'No entendí tu pregunta.';
    }
}
