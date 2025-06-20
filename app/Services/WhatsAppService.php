<?php
namespace App\Services;

use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;
use Netflie\WhatsAppCloudApi\Message\Media\MediaObjectID;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\Button;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\ButtonAction;


class WhatsAppService
{
    protected WhatsAppCloudApi $whatsapp;

    public function __construct()
    {
        $this->whatsapp = new WhatsAppCloudApi([
            'from_phone_number_id' => env('WHATSAPP_PHONE_ID'),
            'access_token' => env('WHATSAPP_TOKEN'),
        ]);
    }

    public function sendMessage(string $to, string $message): void
    {
        $this->whatsapp->sendTextMessage($to, $message);
    }

    public function sendImage(string $to, string $caption, $url): void
    {
        // Descargar imagen
        //$resizedUrl = 'https://cdn-grupoinfinitum.ingeniacode.com/cdn-cgi/image/width=300' . parse_url($url, PHP_URL_PATH);
        //$imagenContenido = file_get_contents($resizedUrl);
        $imagenContenido = file_get_contents($url);
        $tempPath = storage_path('app/public/temp.jpg');
        file_put_contents($tempPath, $imagenContenido);

        $response = $this->whatsapp->uploadMedia($tempPath);
        $media_id = new MediaObjectID($response->decodedBody()['id']);
        try {
            $this->whatsapp->sendImage($to, $media_id,$caption);
        } catch (\Throwable $e) {
            logger("No se pudo enviar la imagen a WhatsApp: {$caption} - Error: ");
            // Puedes continuar sin interrumpir el flujo
        }
         
        /*
        $rows = [
            new Button('button-1', 'Yes'),
            new Button('button-2', 'No'),
            new Button('button-3', 'Not Now'),
        ];
        $action = new ButtonAction($rows);
        $this->whatsapp->sendButton(
            $to,
            'Would you like to rate us on Trustpilot?',
            $action,
            'RATE US', // Optional: Specify a header (type "text")
            'Please choose an option' // Optional: Specify a footer 
        );*/


        // Eliminar archivo temporal
        unlink($tempPath);
        
    }
    
}
