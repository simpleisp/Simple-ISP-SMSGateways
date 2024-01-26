<?php

namespace App\Gateways\UserDefined;

class TextSmsGateway
{
    /**
     * Get information about the Text SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Text SMS Gateway',
            'description' => 'A gateway for sending SMS via Text API.',
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
                'name' => 'text_gateway', 
                'value' => setting("text_gateway"),
            ],
            'sender_id' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'text_shortcode', 
                'value' => setting('text_shortcode'),
            ],
            'partner_id' => [
                'label' => 'Partner ID',
                'type' => 'text',
                'name' => 'text_partner_id', 
                'value' => setting('text_partner_id'),
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'text_api_key', 
                'value' => setting('text_api_key'),
            ],
        ];
    }
    

    /**
     * Send SMS using the text API.
     *
     * @return array An array containing the parameters to send sms.
     */
    public function sendSms($phone, $message)
    {
        $apiKey = setting('text_api_key');
        $partnerID = setting('text_partner_id');
        $shortcode = setting('text_shortcode');

        // Build the URL for the API endpoint
        $url = 'https://sms.textsms.co.ke/api/services/sendsms/';

        // Validate required parameters
        if (empty($apiKey) || !is_numeric($partnerID) || empty($phone) || empty($message)) {
            return ['status' => 'error', 'message' => 'Missing or invalid parameters'];
        }

        // Create the request body
        $requestBody = [
            'apikey' => $apiKey,
            'partnerID' => $partnerID,
            'message' => rawurlencode($message), // URL encode the message
            'shortcode' => $shortcode, // Replace with the appropriate sender ID
            'mobile' => $phone,
        ];

        // Initialize cURL session
        $curl = curl_init($url . '?' . http_build_query($requestBody)); // Use GET method

        // Set cURL options for GET request
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // Execute the cURL request
        $response = curl_exec($curl);

        // Close the cURL session
        curl_close($curl);

        echo $response;

        // Check for errors
        if ($response === false) {
            return ['status' => 'error', 'message' => 'cURL error: ' . curl_error($curl)];
        } else {
            $responseArray = json_decode($response, true);

            if ($responseArray === null) {
                return ['status' => 'error', 'message' => 'Could not parse API response'];
            } else {
                $responseCode = $responseArray['responses'][0]['response-code'];

                if ($responseCode === 200) {
                    // Message sent successfully
                    return [
                        'status' => 'success',
                        'message' => 'Message sent successfully!',
                        'messageId' => $responseArray['responses'][0]['messageid']
                    ];
                } else {
                    // Handle the API error using the provided response description
                    return [
                        'status' => 'error',
                        'message' => $responseArray['responses'][0]['response-description'] ?? 'Unknown error'
                    ];
                }
            }
        }
    }

    /**
     * Fetches account balance using the text API.
     *
     * @return array An array containing the balance information.
     */

    public function textSmsBalance()
    {
        $apikey = setting('text_api_key');
        $partnerID = setting('text_partner_id');

        // Initialize a new cURL session
        $curl = curl_init();

        // Prepare the data to be sent
        $curl_post_data = [
            "partnerID" => $partnerID,
            "apikey" => $apikey,
        ];

        // Set the cURL options
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://sms.textsms.co.ke/api/services/getbalance/', // API URL for getting balance
            CURLOPT_RETURNTRANSFER => true, // Return the result on success, FALSE on failure
            CURLOPT_ENCODING => '', // No encoding
            CURLOPT_MAXREDIRS => 10, // Maximum amount of HTTP redirections to follow
            CURLOPT_TIMEOUT => 0, // No timeout
            CURLOPT_FOLLOWLOCATION => true, // Follow any Location: headers sent by the server
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // Force HTTP 1.1
            CURLOPT_CUSTOMREQUEST => 'POST', // Custom request method to use
            CURLOPT_POSTFIELDS => json_encode($curl_post_data), // Data to post in HTTP "POST" operation
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json', // Set the content type of the request to JSON
            ],
        ]);

        // Execute the cURL request and get the response
        $response = curl_exec($curl);

        // Check for any cURL errors
        if (curl_errno($curl)) {
            echo 'Error: ' . curl_error($curl);
        }

        // Close the cURL session
        curl_close($curl);

        // Convert the JSON response to an associative array
        $balance = json_decode($response, true);

        // Check if the balance is present in the response
        if (isset($balance['credit'])) {
            $credit_balance = $balance['credit'];

            // Extract the balance value from the credit balance string
            $value = floatval($credit_balance);

            $currency = "KES"; // Currency code
            return ['value' => $value, 'units' => $currency];
        } else {
            return null;
        }
    }
}