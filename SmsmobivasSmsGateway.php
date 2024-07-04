<?php

namespace App\Gateways\UserDefined;

use Exception;
use Log;
use Illuminate\Support\Facades\Http;

class SmsmobivasSmsGateway
{
    /**
     * Set information about the Smsmobivas SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Smsmobivas SMS Gateway',
            'description' => 'A gateway for sending SMS via Smsmobivas API.',
            'author' => 'SimpleISP',
            'website' => 'https://simplux.africa',
        ];
    }

    /**
     * Set an array of configuration parameters for the form.
     *
     * @return array
     */
    public static function getConfigParameters()
    {
        return [
            'gateway' => [
                'label' => 'Gateway',
                'type' => 'hidden',
                'name' => 'smsmobivas_gateway',
                'value' => setting("smsmobivas_gateway"),
            ],
            'senderId' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'smsmobivas_sender_id',
                'value' => setting("smsmobivas_sender_id"),
            ],
            'clientId' => [
                'label' => 'Client ID',
                'type' => 'text',
                'name' => 'smsmobivas_client_id',
                'value' => setting("smsmobivas_client_id"),
            ],
            'apiKey' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'smsmobivas_api_key',
                'value' => setting("smsmobivas_api_key"),
            ],
        ];
    }

    /**
     * Sends an SMS using the Smsmobivas API.
     *
     * @param string $phone The phone number to send the SMS to.
     * @param string $message The message to send.
     * @return array
     */
    public function sendSms($phone, $message)
    {
        // Get the necessary settings for the Smsmobivas API
        $senderId = setting("smsmobivas_sender_id");
        $clientId = setting("smsmobivas_client_id");
        $apiKey = setting("smsmobivas_api_key");

        // Build the API endpoint
        $url = 'https://user.smsmobivas.co.ke/api/v2/SendSMS';
        
        // Send the SMS
        try {
            $response = Http::get($url, [
                'ApiKey' => $apiKey,
                'ClientId' => $clientId,
                'SenderId' => $senderId,
                'Message' => $message,
                'MobileNumbers' => $phone,
            ]);

            if ($response->successful()) {
                return ['status' => 'success', 'message' => 'Message sent successfully!', 'response' => $response->json()];
            } else {
                return ['status' => 'error', 'message' => 'Failed to send message', 'response' => $response->json()];
            }
        } catch (Exception $e) {
            Log::error('SMS sending failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetches the SMS balance for the Smsmobivas account.
     *
     * @return array|null An array with "currency" and "value" keys representing the currency code and balance value respectively, or null on error.
     */
    public function smsmobivasSmsBalance()
    {
        // Set your Smsmobivas credentials
        $clientId = setting("smsmobivas_client_id");
        $apiKey = setting("smsmobivas_api_key");

        // Build the API endpoint
        $url = 'https://user.smsmobivas.co.ke/api/v2/Balance';

        try {
            // Send the request to fetch the balance
            $response = Http::get($url, [
                'ApiKey' => $apiKey,
                'ClientId' => $clientId,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if ($data['ErrorCode'] === 0) {
                    $balanceString = $data['Data'][0]['Credits'];

                    // Extract numerical balance from the string
                    preg_match("/[\d\.]+/", $balanceString, $matches);
                    $balance = $matches[0];

                    return [
                        'currency' => 'Units', // Assuming Units as currency, adjust if needed
                        'value' => $balance,
                    ];
                } else {
                    Log::error('Error fetching balance: ' . $data['ErrorDescription']);
                    return null;
                }
            } else {
                Log::error('Failed to fetch balance: ' . $response->body());
                return null;
            }
        } catch (Exception $e) {
            // Log the error and return null
            Log::error('Fetching SMS balance failed: ' . $e->getMessage());
            return null;
        }
    }
}
