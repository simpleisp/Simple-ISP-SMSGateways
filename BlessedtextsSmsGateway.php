<?php

namespace App\Gateways\UserDefined;

use Exception;
use Log;

class BlessedtextsSmsGateway
{
    /**
     * Get information about the Blessed Texts SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Blessed Texts SMS Gateway',
            'description' => 'A gateway for sending SMS via Blessed Texts API.',
            'author' => 'SimpleISP',
        ];
    }

    /**
     * Get configuration parameters for Blessed Texts.
     *
     * @return array
     */
    public static function getConfigParameters()
    {
        return [
            'apiKey' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'blessedtexts_api_key',
                'value' => setting("blessedtexts_api_key"),
            ],
            'senderId' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'blessedtexts_sender_id',
                'value' => setting("blessedtexts_sender_id"),
            ],
        ];
    }

    /**
     * Sends an SMS using the Blessed Texts API.
     *
     * @param string $phone The phone number to send the SMS to.
     * @param string $message The message to send.
     * @return array
     */
    public function sendSms($phone, $message)
    {
        // Get API settings
        $apiKey = setting("blessedtexts_api_key");
        $senderId = setting("blessedtexts_sender_id");

        // Prepare the API endpoint and data
        $endpoint = "https://blessedtexts.com/api/sms/v1/sendsms";
        $postData = [
            "api_key" => $apiKey,
            "sender_id" => $senderId,
            "message" => $message,
            "phone" => $phone,
        ];

        try {
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            // Execute the request and capture the response
            $response = curl_exec($ch);

            // Check for cURL errors
            if (curl_errno($ch)) {
                throw new Exception('cURL Error: ' . curl_error($ch));
            }

            curl_close($ch);

            // Log the API response
            Log::info('Blessed Texts API response: ' . $response);

            // Decode the response
            $responseArray = json_decode($response, true);

            // Check if the response indicates success
            if (isset($responseArray[0]['status_code']) && $responseArray[0]['status_code'] == '1000') {
                return ['status' => 'success', 'message' => 'Message sent successfully!'];
            } else {
                return ['status' => 'error', 'message' => 'Failed to send message: ' . $response];
            }

        } catch (Exception $e) {
            // Log the error
            Log::error('Error sending SMS via Blessed Texts: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetches the SMS balance for the Blessed Texts account.
     *
     * @return array|null
     */
    public function checkBalance()
    {
        // Get API key
        $apiKey = setting("blessedtexts_api_key");

        // Prepare the API endpoint and data
        $endpoint = "https://blessedtexts.com/api/sms/v1/credit-balance";
        $postData = [
            "api_key" => $apiKey,
        ];

        try {
            // Initialize cURL
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            // Execute the request and capture the response
            $response = curl_exec($ch);

            // Check for cURL errors
            if (curl_errno($ch)) {
                throw new Exception('cURL Error: ' . curl_error($ch));
            }

            curl_close($ch);

            // Log the API response
            Log::info('Blessed Texts Balance API response: ' . $response);

            // Decode the response
            $responseArray = json_decode($response, true);

            // Check if the response indicates success
            if (isset($responseArray['status_code']) && $responseArray['status_code'] == '1000') {
                return ['status' => 'success', 'balance' => $responseArray['balance']];
            } else {
                return ['status' => 'error', 'message' => 'Failed to fetch balance: ' . $response];
            }

        } catch (Exception $e) {
            // Log the error
            Log::error('Error fetching balance via Blessed Texts: ' . $e->getMessage());
            return null;
        }
    }
}
