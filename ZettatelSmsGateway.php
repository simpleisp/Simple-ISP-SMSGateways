<?php

namespace App\Gateways\UserDefined;

class ZettatelSmsGateway
{
    /**
     * Get information about the zettatel SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Zettatel SMS Gateway',
            'description' => 'A gateway for sending SMS via Zettatel API.',
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
                'name' => 'zettatel_gateway', 
                'value' => setting("zettatel_gateway"),
            ],
            'sender_id' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'zettatel_sender_id', 
                'value' => setting('zettatel_sender_id'),
            ],
            'user_id' => [
                'label' => 'User ID',
                'type' => 'text',
                'name' => 'zettatel_userid', 
                'value' => setting('zettatel_userid'),
            ],
            'password' => [
                'label' => 'Password',
                'type' => 'text',
                'name' => 'zettatel_password', 
                'value' => setting('zettatel_password'),
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'zettatel_api_key', 
                'value' => setting('zettatel_api_key'),
            ],
        ];
    }
    

    /**
     * Send SMS using the zettatel API.
     *
     * @return array An array containing the parameters to send sms.
     */
    public function sendSms($phone, $message)
    {
        // Get the necessary API credentials and settings
        $apiKey = setting("zettatel_api_key");
        $userid = setting("zettatel_userid");
        $password = setting("zettatel_password");
        $senderid = setting("zettatel_sender_id");

        // Set the message type, duplicate check, output format, and send method
        $msgType = 'text';
        $duplicatecheck = 'true';
        $output = 'json';
        $sendMethod = 'quick';

        // Create the POST fields for the API request
        $postFields = "userid={$userid}&password={$password}&mobile={$phone}&msg={$message}&senderid={$senderid}&msgType={$msgType}&duplicatecheck={$duplicatecheck}&output={$output}&sendMethod={$sendMethod}";

        // Initialize a new cURL session
        $curl = curl_init();

        // Set the cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://portal.zettatel.com/SMSApi/send", // API endpoint
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => array(
                "apikey: {$apiKey}",
                "cache-control: no-cache",
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        // Send the cURL request and store the response
        $response = curl_exec($curl);
        $err = curl_error($curl);

        // Close the cURL session
        curl_close($curl);

        // Handle any errors that occurred during the cURL request
        if ($err) {
            return ['status' => 'error', 'message' => $err];
        } else {
            // Parse the response as JSON into an associative array
            $responseArray = json_decode($response, true);

            // Check the response and return a user-friendly message
            if ($responseArray['status'] != 'success') {
                return ['status' => 'error', 'message' => $responseArray['reason']];
            } else {
                return ['status' => 'success', 'message' => 'Message sent successfully!'];
            }
        }
    }

}