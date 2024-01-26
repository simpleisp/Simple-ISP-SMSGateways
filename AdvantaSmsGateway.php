<?php

namespace App\Gateways\UserDefined;


class AdvantaSmsGateway
{
    /**
     * Set information about the Your Gateway SMS gateway.
     * Note: Ensure that the 'name' provided is unique and representative of the gateway. The 'website' URL should lead to
     * relevant documentation or the official source for additional information.
     *
     * @return array An associative array containing information about Your Gateway SMS gateway.
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Advanta Sms Gateway',
            'description' => 'A gateway for sending SMS via Your Advanta API.',
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
                'name' => 'advanta_gateway', 
                'value' => setting("advanta_gateway"),
            ],
            'apiKey' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'advanta_api_key',
                'value' => setting("advanta_api_key"),
            ],
            'partner_id' => [
                'label' => 'Partner ID',
                'type' => 'text',
                'name' => 'advanta_partner_id', 
                'value' => setting('advanta_partner_id'),
            ],
            'senderId' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'advanta_sender_id',
                'value' => setting("advanta_sender_id"),
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
        $endpoint = "https://quicksms.advantasms.com/api/services/sendsms/";

        $apiKey = setting("advanta_api_key");
        $partnerID = setting('advanta_partner_id');
        $shortcode = setting("advanta_sender_id");

        $postData = array(
            "apikey" => $apiKey,
            "partnerID" => $partnerID,
            "message" => $message,
            "shortcode" => $shortcode,
            "mobile" => $phone
        );

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return ['status' => 'error', 'message' => 'cURL Error: ' . curl_error($ch)];
        }

        curl_close($ch);

        $responseArray = json_decode($response, true);

        if (isset($responseArray['responses'][0]['response-code']) && $responseArray['responses'][0]['response-code'] == 200) {
            // Message sent successfully
            $messageId = $responseArray['responses'][0]['messageid'];
            return ['status' => 'success', 'message' => 'Message sent successfully! Message ID: ' . $messageId];
        } else {
            // Handle the error
            return ['status' => 'error', 'message' => 'Failed to send message. ' . json_encode($responseArray)];
        }
    }


    /**
     * Fetches account balance using the Your Gateway API.
     *
     * @return array An array containing the balance information.
     * The expected output should be in the format:
     * [
     *    "units" => 'units',  // Replace 'units' with the actual units used by your gateway (optional)
     *    "value" => $value, // Replace $value with the actual SMS balance value
     * ]
     */
    public function advantaSmsBalance()
    {
        // Get the API key and partner ID from the settings
        $apiKey = setting("advanta_api_key");
        $partnerID = setting('advanta_partner_id');

        // Set the endpoint URL
        $endpoint = 'https://quicksms.advantasms.com/api/services/getbalance/';

        // Prepare the request body
        $postData = [
            'apikey' => $apiKey,
            'partnerID' => $partnerID
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
            curl_close($curl);
            return null;
        }

        // Close the cURL session
        curl_close($curl);

        // Convert the JSON response to an associative array
        $balanceData = json_decode($response, true);

        // Check if the response code is 200 and 'credit' is present
        if (isset($balanceData['response-code']) && $balanceData['response-code'] == 200 && isset($balanceData['credit'])) {
            // Extract the credit value from the response
            $value = floatval($balanceData['credit']);

            $units = "units"; 

            return ['value' => $value, 'units' => $units];
        } else {
            return null;
        }
    }

}
