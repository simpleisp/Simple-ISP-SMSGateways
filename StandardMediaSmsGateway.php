<?php

namespace App\Gateways\UserDefined;

class StandardMediaSmsGateway
{
    /**
     * Set information about the Standard Media SMS gateway.
     *
     * @return array An associative array containing information about Standard Media SMS gateway.
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Standard Media Sms Gateway',
            'description' => 'A gateway for sending SMS via Standard Media API.',
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
            'email' => [
                'label' => 'Email',
                'type' => 'text',
                'name' => 'standard_media_email',
                'value' => setting("standard_media_email"),
            ],
            'sender' => [
                'label' => 'Sender',
                'type' => 'text',
                'name' => 'standard_media_sender',
                'value' => setting("standard_media_sender"),
            ],
            'apiKey' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'standard_media_api_key',
                'value' => setting("standard_media_api_key"),
            ],
        ];
    }

    /**
     * Send SMS using the Standard Media API.
     *
     * @param string $phone
     * @param string $message
     * @return array An array containing the parameters to send SMS.
     */
    public function sendSms($phone, $message)
    {
        $endpoint = "https://vas2.standardmedia.co.ke/api/sendmessages";

        $email = setting("standard_media_email");
        $sender = setting("standard_media_sender");
        $apiKey = setting("standard_media_api_key");

        $postData = [
            'email' => $email,
            'sender' => $sender,
            'sms' => [
                [
                    'msidn' => $phone,
                    'message' => $message,
                    'requestid' => uniqid(), // Generate a unique request ID
                    'schedule' => null, // You can set scheduled time if needed
                ]
            ]
        ];

        $headers = [
            'Content-Type: application/json',
            'api_key: ' . $apiKey
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            \Log::error('cURL Error: ' . curl_error($ch));
            curl_close($ch);
            return ['status' => 'error', 'message' => 'Failed to send message due to cURL error.'];
        }

        curl_close($ch);

        $responseArray = json_decode($response, true);

        if ($responseArray === null) {
            \Log::error('Failed to decode JSON response: ' . $response);
            return ['status' => 'error', 'message' => 'Failed to decode JSON response.'];
        }

        \Log::info('API Response: ' . json_encode($responseArray));

        if (isset($responseArray['status']) && $responseArray['status'] == 'success') {
            return ['status' => 'success', 'message' => 'Message sent successfully.'];
        } else {
            \Log::error('Failed to send message. ' . json_encode($responseArray));
            return ['status' => 'error', 'message' => 'Failed to send message. ' . json_encode($responseArray)];
        }
    }

    /**
     * Fetches account balance using the Standard Media API.
     *
     * @return array An array containing the balance information.
     */
    public function standardMediaSmsBalance()
    {
        $email = setting("standard_media_email");
        $apiKey = setting("standard_media_api_key");

        $endpoint = 'https://vas2.standardmedia.co.ke/api/getbalance?email=' . $email;

        $headers = [
            'api_key: ' . $apiKey
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            \Log::error('cURL Error: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        $balanceData = json_decode($response, true);

        if ($balanceData !== null && isset($balanceData['DepartmentAccounts'])) {
            // Loop through DepartmentAccounts to find the desired section
            foreach ($balanceData['DepartmentAccounts'] as $account) {
                if ($account['Account'] === 'Safaricom' && $account['Department'] === 'Administration') {
                    // Return the balance if found
                    return ['value' => $account['Balance'], 'units' => ''];
                }
            }
            // If the desired section is not found, log an error
            \Log::error('Failed to find balance for Safaricom - Administration.');
            return null;
        } else {
            \Log::error('Failed to fetch SMS balance. ' . $response);
            return null;
        }
    }

}
