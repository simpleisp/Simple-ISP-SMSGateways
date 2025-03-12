<?php

namespace App\Gateways\UserDefined;

class TililtechSmsGateway
{
    /**
     * Set information about the Tililtech SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Tililtech SMS Gateway',
            'description' => 'A gateway for sending SMS via Tililtech API.',
            'author' => 'SimpleISP',
            'website' => 'https://tililtech.com',
        ];
    }

    /**
     * Set an array of configuration parameters for the form.
     *
     * @return array
     */
    public static function getConfigParameters()
    {
        return [
            'gateway' => [
                'label' => 'Gateway',
                'type' => 'hidden',
                'name' => 'tililtech_gateway',
                'value' => setting("tililtech_gateway"),
            ],
            'senderId' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'tililtech_sender_id',
                'value' => setting("tililtech_sender_id"),
            ],
            'apiKey' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'tililtech_api_key',
                'value' => setting("tililtech_api_key"),
            ],
        ];
    }

    /**
     * Sends an SMS using the Tililtech API.
     *
     * @param string $phone The phone number to send the SMS to.
     * @param string $message The message to send.
     * @param string|null $scheduleTime Optional. The time to send the message (format: 'Y-m-d H:i:s'). If null, the message is sent immediately.
     * @return array
     */
    public function sendSms($phone, $message, $scheduleTime = null)
    {
        $apiUrl = 'https://api.tililtech.com/sms/v3/sendsms';
        $apiKey = setting("tililtech_api_key");
        $senderId = setting("tililtech_sender_id");

        $requestData = [
            'api_key' => $apiKey,
            'service' => 0, // Updated parameter name
            'mobile' => $phone,
            'response_type' => 'json',
            'shortcode' => $senderId,
            'message' => $message,
        ];

        // Include 'date_send' only if a schedule time is provided
        if (!empty($scheduleTime)) {
            $requestData['date_send'] = $scheduleTime;
        }

        $response = $this->makeRequest($apiUrl, $requestData);

        // Check if the response is valid
        if (!is_array($response)) {
            return ['status' => 'error', 'message' => 'Invalid response received from Tililtech API'];
        }

        // Check if the status code indicates success
        if (isset($response[0]['status_code']) && $response[0]['status_code'] == '1000') {
            return ['status' => 'success', 'message' => 'Message sent successfully!'];
        } else {
            $errorDesc = isset($response[0]['status_desc']) ? $response[0]['status_desc'] : 'Unknown error';
            return ['status' => 'error', 'message' => 'Failed to send message: ' . $errorDesc];
        }
    }

    /**
     * Fetches the SMS balance for the Tililtech account.
     *
     * @return array|null An array with "currency" and "value" keys representing the currency code and balance value respectively, or null on error.
     */
    public function tililtechSmsBalance()
    {
        $apiUrl = 'https://api.tililtech.com/sms/v3/profile';
        $apiKey = setting("tililtech_api_key");

        $requestData = [
            'api_key' => $apiKey,
        ];

        $response = $this->makeRequest($apiUrl, $requestData);

        if (!isset($response['wallet']['credit_balance'])) {
            return null;
        }

        return [
            'units' => 'KES', // Assuming Kenyan Shillings
            'value' => $response['wallet']['credit_balance'],
        ];
    }

    /**
     * Makes an HTTP request to the Tililtech API.
     *
     * @param string $url The API endpoint URL.
     * @param array $data The request data.
     * @return array The API response.
     */
    private function makeRequest($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
