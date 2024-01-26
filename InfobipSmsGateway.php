<?php

namespace App\Gateways\UserDefined;

class InfobipSmsGateway
{
    /**
     * Set information about the infobip SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'infobip SMS Gateway',
            'description' => 'A gateway for sending SMS via infobip API.',
            'author' => 'SimpleISP',
            'website' => 'https://simplux.africa',
        ];
    }

    public static function getConfigParameters()
    {
        return [
            'gateway' => [
                'label' => 'Gateway',
                'type' => 'hidden',
                'name' => 'infobip_gateway', 
                'value' => setting("infobip_gateway"),
            ],
            'sender_id' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'infobip_sender_id',
                'value' => setting('infobip_sender_id'),
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'infobip_api_key', 
                'value' => setting('infobip_api_key'),
            ],
            'base_url' => [
                'label' => 'Base Url',
                'type' => 'text',
                'name' => 'infobip_baseurl', 
                'value' => setting('infobip_baseurl'),
            ],
        ];
    }
    

    /**
     * Send SMS using the infobip API.
     *
     * @return array An array containing the parameters to send sms.
     */
    public function sendSms($phone, $message)
    {
        // Get the necessary settings for the Infobip API
        $sender_id = setting("infobip_sender_id");
        $baseUrl = 'https://'.setting("infobip_baseurl");
        $apiKey = setting("infobip_api_key");

        // Prepare the headers
        $headers = [
            'Authorization: App ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        // Prepare the payload
        $payload = [
            'messages' => [
                [
                    'from' => $sender_id,
                    'destinations' => [
                        ['to' => $phone]
                    ],
                    'text' => $message
                ]
            ]
        ];

        // Initialize cURL
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . '/sms/2/text/advanced',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        // Execute the request and fetch the response
        $response = curl_exec($curl);
        $err = curl_error($curl);

        // Close cURL
        curl_close($curl);

        // Check if there was an error
        if ($err) {
            return ['status' => 'error', 'message' => $err];
        } else {
            $responseArray = json_decode($response, true);

            // Check the response and return a user-friendly message
            if (isset($responseArray['requestError'])) {
                return ['status' => 'error', 'message' => $responseArray['requestError']['serviceException']['text']];
            } else {
                return ['status' => 'success', 'message' => 'Message sent successfully!'];
            }
        }
    }

    /**
     * Fetches account balance using the infobip API.
     *
     * @return array An array containing the balance information.
     */

    public function infobipSmsBalance()
    {
        // Get the API key and base URL from the settings
        $apiKey = setting("infobip_api_key");
        $baseUrl = 'https://' . setting("infobip_baseurl");

        // Set the headers for the API request
        $headers = [
            'Authorization: App ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        // Initialize a new cURL session
        $curl = curl_init();

        // Set the cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => $baseUrl . '/account/1/balance', // API endpoint to retrieve balance
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => $headers,
        ));

        // Send the cURL request and store the response
        $response = curl_exec($curl);

        // Check for any cURL errors
        if (curl_errno($curl)) {
            curl_close($curl);
            return null;
        }

        // Close the cURL session
        curl_close($curl);

        // Convert the JSON response to an associative array
        $balance = json_decode($response, true);

        // Check if the balance is present in the response
        if (isset($balance['balance'])) {
            // Extract the balance value from the response
            $value = floatval($balance['balance']);

            // Set the currency (e.g., "USD" for US Dollars)
            $currency = isset($balance['currency']) ? $balance['currency'] : "USD";

            return ['value' => $value, 'units' => $currency];
        } else {
            return null;
        }
    }
}