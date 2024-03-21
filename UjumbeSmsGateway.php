<?php

// app/Gateways/UserDefined/UjumbeSmsGateway.php
namespace App\Gateways\UserDefined;

class UjumbeSmsGateway
{
    /**
     * Get information about the Ujumbe SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Ujumbe SMS Gateway',
            'description' => 'A gateway for sending SMS via Ujumbe API.',
            'author' => 'SimpleISP',
            'website' => 'https://simplux.africa',
        ];
    }

    public static function getConfigParameters()
    {
        return [
            'api_key' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'ujumbe_api_key',
                'value' => setting('ujumbe_api_key'),
            ],
            'email' => [
                'label' => 'Account Email',
                'type' => 'text',
                'name' => 'ujumbe_account_email',
                'value' => setting('ujumbe_account_email'),
            ],
            'sender_id' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'ujumbe_sender_id',
                'value' => setting('ujumbe_sender_id'),
            ],
        ];
    }

    /**
     * Send SMS using the Ujumbe API.
     *
     * @param string $phone The phone number to which the SMS will be sent.
     * @param string $message The SMS message content.
     * @return array An array containing the response from the Ujumbe API.
     */
    public function sendSms($phone, $message)
    {
        $apiKey = setting('ujumbe_api_key');
        $email = setting('ujumbe_account_email');
        $senderId = setting('ujumbe_sender_id'); 

        $apiUrl = "https://ujumbesms.co.ke/api/messaging";
        $queryParams = [
            'email' => $email,
            'to' => $phone,
            'from' => $senderId,
            'auth' => $apiKey,
            'message' => $message,
        ];

        $urlWithQueryParams = $apiUrl . '?' . http_build_query($queryParams);

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $urlWithQueryParams,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET', 
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ['status' => 'error', 'message' => 'cURL error: ' . curl_error($curl)];
        } else {
            $responseArray = json_decode($response, true);
    
            if ($responseArray === null) {
                return ['status' => 'error', 'message' => 'Could not parse API response'];
            } else {
                $status = $responseArray['status'];
                $meta = $responseArray['meta'] ?? null;
    
                if ($status && $status['type'] === 'success') {
                    // Message sent successfully
                    return [
                        'status' => 'success',
                        'message' => 'Message sent successfully!',
                        'meta' => $meta,
                    ];
                } else {
                    // Handle the API error using the provided response description
                    $errorCode = $status['code'] ?? 'Unknown';
                    $errorDescription = $status['description'] ?? 'Unknown error';
    
                    return [
                        'status' => 'error',
                        'message' => "API error - Code: $errorCode, Description: $errorDescription",
                        'meta' => $meta,
                    ];
                }
            }
        }
    }

    /**
     * Fetches account balance using the Ujumbe SMS Gateway API.
     *
     * @return array|null An array containing the balance information, or null if an error occurs.
     */
    public function ujumbeSmsBalance()
    {
        $apiKey = setting('ujumbe_api_key');
        $email = setting('ujumbe_account_email');

        // Initialize a new cURL session
        $curl = curl_init();

        // Set the cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://ujumbesms.co.ke/api/balance", // API URL for getting balance
            CURLOPT_RETURNTRANSFER => true, // Return the result on success, FALSE on failure
            CURLOPT_ENCODING => '', // No encoding
            CURLOPT_MAXREDIRS => 10, // Maximum amount of HTTP redirections to follow
            CURLOPT_TIMEOUT => 0, // No timeout
            CURLOPT_FOLLOWLOCATION => true, // Follow any Location: headers sent by the server
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // Force HTTP 1.1
            CURLOPT_CUSTOMREQUEST => 'POST', // Custom request method to use
            CURLOPT_POSTFIELDS => json_encode(array()), // No data to send in this case
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json', // Set the content type of the request to JSON
                'x-authorization: ' . $apiKey, // Set the API key in the header
                'email: ' . $email // Set the email in the header
            ),
        ));

        // Execute the cURL request and get the response
        $response = curl_exec($curl);

        // Check for any cURL errors
        if (curl_errno($curl)) {
            echo 'Error:' . curl_error($curl);
            return null;
        }

        // Close the cURL session
        curl_close($curl);

        // Convert the JSON response to an associative array
        $balanceData = json_decode($response, true);

        // Check if the response contains the expected structure
        if (isset($balanceData['status']) && isset($balanceData['meta']['credits'])) {
            $credits = intval($balanceData['meta']['credits']);
            // Assuming the currency is always KES
            $currency = "units";
            return ['value' => $credits, 'units' => $currency];
        } else {
            return null;
        }
    }
}

