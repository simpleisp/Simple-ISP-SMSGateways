<?php

namespace App\Gateways\UserDefined;

class PashaSmsGateway
{
    /**
     * Get information about the Pasha SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Pasha SMS Gateway',
            'description' => 'A gateway for sending SMS via Pasha API.',
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
                'name' => 'pasha_gateway', 
                'value' => setting("pasha_gateway"),
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'pasha_api_key', 
                'value' => setting('pasha_api_key'),
            ],
            'sender_id' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'pasha_sender_id', 
                'value' => setting('pasha_sender_id'),
            ],
        ];
    }
    

    /**
     * Send SMS using the pasha API.
     *
     * @return array An array containing the parameters to send sms.
     */
    public function sendSms($phone, $message)
    {
        // This nested function generates a random string of a specified length
        function generateRandomString($length = 10)
        {
            $characters =
                "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
            $charactersLength = strlen($characters);
            $randomString = "";
            for ($i = 0; $i < $length; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
        }

        // Generate a random string to use as the correlator and get the sender ID from the settings
        $correlator = generateRandomString();
        $sender = setting("pasha_sender_id");

        // Set up the cURL request with the necessary parameters and headers
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.pasha.biz/v2/send-sms",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS =>
                ' { 
                "sender": "' .
                $sender .
                '", 
                "correlator": "' .
                $correlator .
                '", 
                "content": [
                    {
                    "phone": "' .
                $phone .
                '",
                    "message": "' .
                $message .
                '"
                    }
                ]
                } ',
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . setting("pasha_api_key"),
                "Content-Type: application/json",
            ],
        ]);

        // Execute the cURL request
        $response = curl_exec($curl);
        $err = curl_error($curl);

        // Close cURL
        curl_close($curl);

        // Check if there was a cURL error
        if ($err) {
            return ['status' => 'error', 'message' => $err];
        } 

        // No cURL error, check the API response
        $responseArray = json_decode($response, true);

        // Check the response and return a user-friendly message
        if (isset($responseArray['errors'])) { 
            // If there are multiple errors, concatenate them into a single string
            $errorMessage = is_array($responseArray['errors']) ? implode(' ', $responseArray['errors']) : $responseArray['errors'];
            return ['status' => 'error', 'message' => $errorMessage];
        } else {
            return ['status' => 'success', 'message' => 'Message sent successfully!'];
        }
    }

}