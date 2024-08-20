<?php

namespace App\Gateways\UserDefined;

class MobilesasaSmsGateway
{
    /**
     * Set information about the Mobilesasa SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Mobilesasa SMS Gateway',
            'description' => 'A gateway for sending SMS via Mobilesasa API.',
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
                'name' => 'mobilesasa_gateway', 
                'value' => setting("mobilesasa_gateway"),
            ],
            'sender_id' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'mobilesasa_sender_id', 
                'value' => setting('mobilesasa_sender_id'),
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'mobilesasa_api_key', 
                'value' => setting('mobilesasa_api_key'),
            ],
        ];
    }
    

    /**
     * Send SMS using the mobilesasa API.
     *
     * @return array An array containing the parameters to send sms.
     */
    public function sendSms($phone, $message)
    {
        // Initialize a new cURL session
        $curl = curl_init();

        // Get sender ID and API key from the settings
        $senderID = setting("mobilesasa_sender_id");
        $apiKey = setting("mobilesasa_api_key");

        // Set the data to be sent as a POST request
        $postFields = array(
            "senderID" => $senderID,  // Sender ID
            "message" => $message,    // Message to be sent
            "phones" => $phone        // Phone number(s) to send the message to
        );

        // Set the cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.mobilesasa.com/v2/send/bulk',  // API URL
            CURLOPT_RETURNTRANSFER => true,    // Return the result on success, FALSE on failure
            CURLOPT_ENCODING => '',    // Empty '' means set the encoding to identity (no compression / decompression)
            CURLOPT_MAXREDIRS => 10,   // Maximum amount of HTTP redirections to follow
            CURLOPT_TIMEOUT => 0,  // No timeout
            CURLOPT_FOLLOWLOCATION => true,    // Follow any Location: headers sent by the server
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,   // Force HTTP 1.1
            CURLOPT_CUSTOMREQUEST => 'POST',    // Custom request method to use
            CURLOPT_POSTFIELDS => json_encode($postFields),   // Data to post in HTTP "POST" operation
            CURLOPT_HTTPHEADER => array(   // Custom HTTP headers
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json'
            ),
        ));

        // Execute the cURL request and get the response
        $response = curl_exec($curl);

        // Get any error that occurred during the execution of the cURL request
        $err = curl_error($curl);

        // Close the cURL session
        curl_close($curl);

        // Check if there was an error during the cURL request
        if ($err) {
            // Return the error message
            return ['status' => 'error', 'message' => $err];
        } else {
            // If there was no error, decode the response
            $responseArray = json_decode($response, true);
            
            // Check if the response status is false
            if (isset($responseArray['status']) && $responseArray['status'] === false) {
                // Return the error message and response code
                return ['status' => 'error', 'message' => $responseArray['message'] ?? 'Unknown error', 'responseCode' => $responseArray['responseCode'] ?? 'Unknown response code'];
            } else {
                // If the status was not false, assume the message was sent successfully
                return ['status' => 'success', 'message' => 'Message sent successfully!'];
            }
        }
    }

    /**
     * Fetches account balance using the mobilesasa API.
     *
     * @return array An array containing the balance information.
     */

    public function mobilesasaSmsBalance()
    {
        // Get the API key from the settings
        $apiKey = setting("mobilesasa_api_key");

        // Initialize a new cURL session
        $curl = curl_init();

        // Set the cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.mobilesasa.com/v2/get-balance', // API endpoint to retrieve balance
            CURLOPT_RETURNTRANSFER => true, // If it is set to TRUE, curl_exec() will return the result on success, FALSE on failure
            CURLOPT_ENCODING => '', // An empty string means no encoding
            CURLOPT_MAXREDIRS => 10, // The maximum amount of HTTP redirections to follow
            CURLOPT_TIMEOUT => 0, // The maximum number of seconds to allow cURL functions to execute
            CURLOPT_FOLLOWLOCATION => true, // TRUE to follow any Location: header that the server sends as part of the HTTP header
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // Force cURL to use specific HTTP version
            CURLOPT_CUSTOMREQUEST => 'GET', // Set a custom request method
            CURLOPT_HTTPHEADER => array( // Set two headers 'Accept' and 'Authorization' in our request
                'Accept: application/json',
                'Authorization: Bearer ' . $apiKey
            ),
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

            // Set the currency (e.g., "KES" for Kenyan Shillings)
            $currency = isset($balance['currency']) ? $balance['currency'] : "KES";

            return ['value' => $value, 'units' => $currency];
        } else {
            return null;
        }
    }
}
