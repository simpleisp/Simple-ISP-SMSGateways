<?php

namespace App\Gateways\UserDefined;

class VasproSmsGateway
{
    /**
     * Set information about the Your Gateway SMS gateway.
     *
     * @return array An associative array containing information about Your Gateway SMS gateway.
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Vaspro Sms Gateway',
            'description' => 'A gateway for sending SMS via Your Vaspro API.',
            'author' => 'SimpleISP',
            'website' => 'https://simplux.africa',
        ];
    }

    /**
     * Set configuration parameters for the SMS gateway.
     *
     * @return array An associative array of configuration parameters for the gateway.
     */
    public static function getConfigParameters()
    {
        return [
            'gateway' => [
                'label' => 'Gateway',
                'type' => 'hidden',
                'name' => 'vaspro_gateway',
                'value' => setting("vaspro_gateway"),
            ],
            'apiKey' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'vaspro_api_key',
                'value' => setting("vaspro_api_key"),
            ],
            'senderId' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'vaspro_sender_id',
                'value' => setting("vaspro_sender_id"),
            ],
        ];
    }

    /**
     * Send SMS using the Your Gateway API.
     *
     * @param string $phone
     * @param string $message
     * @return array An array containing the parameters to send SMS.
     */
    public function sendSms($phone, $message)
    {
        $endpoint = "https://api.vaspro.co.ke/v3/BulkSMS/api/create";

        $apiKey = setting("vaspro_api_key");
        $shortcode = setting("vaspro_sender_id");

        $str = rand(1, 99999);
        $resulting = ($str);

        $postData = array(
            "apiKey" => $apiKey,
            "shortCode" => $shortcode,
            "recipient" => $phone,
            "enqueue" => 1,
            "message" => $message,
            "callbackURL" => ""
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            // Log cURL error
            \Log::error('cURL Error: ' . curl_error($ch));
            curl_close($ch);
            return ['status' => 'error', 'message' => 'Failed to send message due to cURL error.'];
        }

        curl_close($ch);

        $responseArray = json_decode($response, true);

        if ($responseArray === null) {
            // Log or handle the JSON decoding error
            \Log::error('Failed to decode JSON response: ' . $response);
            return ['status' => 'error', 'message' => 'Failed to decode JSON response.'];
        }

        \Log::info('API Response: ' . json_encode($responseArray)); // Log the response structure

        if (isset($responseArray['code']) && $responseArray['code'] == 'Success') {
            // Message sent successfully
            $messageDesc = $responseArray['data']['message'];
            return ['status' => 'success', 'message' => $messageDesc];
        } else {
            // Handle the error
            \Log::error('Failed to send message. ' . json_encode($responseArray));
            return ['status' => 'error', 'message' => 'Failed to send message. ' . json_encode($responseArray)];
        }
    }

    /**
     * Fetches account balance using the Your Gateway API.
     *
     * @return array An array containing the balance information.
     */
    public function vasproSmsBalance()
    {
        // Get the API key from the settings
        $apiKey = setting("vaspro_api_key");

        // Set the endpoint URL
        $endpoint = 'https://sms.vaspro.co/v2/users/profile';

        // Prepare the request body
        $postData = [
            'apiKey' => $apiKey
        ];

        // Initialize cURL
        $curl = curl_init();

        // Set the cURL options for the GET request
        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        // Execute the cURL request and store the response
        $response = curl_exec($curl);

        // Check for cURL errors
        if (curl_errno($curl)) {
            // Log cURL error
            \Log::error('cURL Error: ' . curl_error($curl));
            curl_close($curl);
            return null;
        }

        // Close the cURL session
        curl_close($curl);

        // Convert the JSON response to an associative array
        $balanceData = json_decode($response, true);

        // Check if the response code is 200 and 'balance' is present
        if (isset($balanceData['data']['code']) && $balanceData['data']['code'] == 200 && isset($balanceData['data']['data']['balance'])) {
            // Extract the balance value from the response
            $value = floatval($balanceData['data']['data']['balance']);

            $units = "";

            return ['value' => $value, 'units' => $units];
        } else {
            // Log error
            \Log::error('Failed to fetch SMS balance. ' . json_encode($balanceData));
            return null;
        }
    }
}
