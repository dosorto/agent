<?php
namespace App\Services;

use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

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
}
