<?php
namespace App\Services;

use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Facades\Http;

class ChatGptService
{
    public function ask(string $question, $history): array
    {
        $respuesta = [
            'respuesta' => 'Lo siento, no pude procesar la solicitud correctamente.',
            'productos' => []
        ];
        if (!collect($history)->contains('role', 'system')) {
            array_unshift($history, [
                'role' => 'system',
                'content' => 'PROMPT
Eres un asesor de ventas de la tienda Grupo Infinitum (https://grupoinfinitum.hn). Tu tarea es ayudar al cliente a encontrar productos adecuados según su necesidad. 
Tienes acceso al inventario de la tienda a través de una API.

Antes de buscar, asegúrate de entender claramente lo que el cliente quiere. No consultes la API hasta que tengas:
- Tipo de producto (camisa, pantalón, celular, etc.)
- Color (usa solo nombres como "blanco", "negro", evita adjetivos como "blanca")
- Talla u otras características
Cuando consultes la API, usa como maximo hasta 3 palabras clave.
Analiza los productos devueltos por la API, y selecciona solo los que coincidan con lo que el cliente desea.
NUNCA DEVUELVAS UNA REPUESTA QUE NO SEA EN EL FORMATO JSON ANTERIOR.
NUNCA SUGIERAS PRODUCTOS DE INTERNET O DE OTRAS TIENEDAS.
NUNCA INVENTES PRODUCTOS, SIEMPRE USA tools o funtion para buscar los productos y ENVIA LOS PRODUCTOS QUE SE TE PROPORCIONAN DE LA API
- Si no hay coincidencias exactas, reformula palabras clave relacionadas y muestra productos similares.
- Nunca digas que no hay productos. Siempre ofrece una alternativa.
- Si el cliente pide más opciones, muestra los siguientes productos paginados.
PROMPT']);
        }
        
        $tools = [
            
            [
                'type' => 'function',
                'function' => [
                    'name' => 'buscarProductos',
                    'description' => 'Busca productos usando palabras clave, como "pantalon azul", y considera la paginación si se solicita más productos.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => [
                                'type' => 'string',
                                'description' => 'Palabras clave del producto como "camisa blanco", máximo tres palabras.'
                            ],
                            'page' => [
                                'type' => 'integer',
                                'description' => 'Número de página si se requieren más productos'
                            ]
                        ],
                        'required' => ['query','page']
                    ]
                ]
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'responder_con_productos',
                    'description' => 'Devuelve la respuesta final al cliente y una lista de productos encontrados, en formato JSON estructurado. en el campo respuesta siempre devuelve un respuesta amable y corta, nunca uses un lenguaje tecnico de programación usa una respuesta amable de un asesor de ventas que muestra los productos encontrados',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'respuesta' => [
                                'type' => 'string',
                                'description' => 'Frase natural para guiar al cliente'
                            ],
                            'productos' => [
                                'type' => 'array',
                                'description' => 'Lista de productos sugeridos',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'nombre' => [
                                            'type' => 'string',
                                            'description' => 'Nombre o descripción del producto'
                                        ],
                                        'precio' => [
                                            'type' => 'string',
                                            'description' => 'Precio con símbolo L, ejemplo: L 1,594.00'
                                        ],
                                        'imagen' => [
                                            'type' => 'string',
                                            'description' => 'URL de la imagen del producto'
                                        ],
                                        'descripcion' => [
                                            'type' => 'string',
                                            'description' => 'en este campo agrega todas las descripciones adicionales de ese producto como color o colores,talla o tallas y disponibilidad'
                                        ]
                                    ],
                                    'required' => ['nombre', 'precio', 'imagen', 'descripcion']
                                ]
                            ]
                        ],
                        'required' => ['respuesta', 'productos']
                    ]
                ]
            ]
        ];
        
        $history[] = ['role' => 'user', 'content' => $question];
        //logger($history);

        $response = Http::withToken(env('OPENAI_API_KEY'))
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1-nano',
                'messages' => $history,
                'tools' => $tools,
                'tool_choice' => 'auto'
            ]);

            logger("Respuesta 1".$response);
        
            $choice = $response['choices'][0] ?? null;

            if ($choice &&  $choice['finish_reason'] === 'tool_calls') {
                $functionCall = $choice['message']['tool_calls'][0]['function'];
                if ($functionCall['name'] === 'buscarProductos') {
                    $args = json_decode($functionCall['arguments'], true);
                    $query = $args['query'] ?? '';
                    $toolCallId = $choice['message']['tool_calls'][0]['id'];
        
                    // Llamada a tu API
                    //logger("LLAMO A LA API");
                    logger($args);
                    $productResponse = Http::withHeaders([
                        'Accept'=>'application/json',
                        'content-type'=>'application/json',
                        'x-store' => 1, // cambia esto según lo que necesites
                    ])->get('https://grupoinfinitum.hn/server/api/e-comerce/searchproducto', [
                        'q' => $query, 
                        'page' => $args['page'] ?? 1
                    ]);
                    //logger("Query: ".$query);
                    
        
                    $products = $productResponse->json()['data']['data'] ?? [];
                    
                    //$history[] = $choice['message'];
                    $history= [
                        [
                            'role' => 'system',
                            'content' => 'Actua como un simplificador, estudia el json de productos y devulvelelo en el formato correcto'
                        ],
                        [
                            'role' => 'assistant',
                            'content' => null,
                            'tool_calls' => [
                                [
                                    'id' => $toolCallId,
                                    'type' => 'function',
                                    'function' => [
                                        'name' => 'buscarProductos',
                                        'arguments' => $query,
                                    ]
                                ]
                            ]
                        ],
                        [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => json_encode(['productos' => $products]),
                    ]];
    
                    //logger($history);
                    $finalResponse = Http::withToken(env('OPENAI_API_KEY'))
                        ->post('https://api.openai.com/v1/chat/completions', [
                            'model' => 'gpt-4.1-nano',
                            'messages' => $history,
                            'tools' => $tools,
                            'tool_choice' => [ // fuerza que se use esta tool
                                'type' => 'function',
                                'function' => [
                                    'name' => 'responder_con_productos'
                                ]
                            ],
                        ]);

                        //logger("RESPUESTA 2: ".$finalResponse);
                        //$choice['message']['tool_calls'][0]['function']['arguments'], true)
                    return json_decode($finalResponse['choices'][0]['message']['tool_calls'][0]['function']['arguments'],true) ?? $respuesta;
                }
            }

        return ['respuesta'=> $response['choices'][0]['message']['content'], 'productos'=>[]]?? $respuesta;
        }
}

