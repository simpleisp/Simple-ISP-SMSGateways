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
     * Log SMS activity to a file in the same directory
     *
     * @param string $phone
     * @param string $message
     * @param array $requestData
     * @param array $response
     * @param string $status
     */
    private function logSmsActivity($phone, $message, $requestData, $response, $status)
    {
        $logFile = __DIR__ . '/tililtech_sms.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $logEntry = sprintf(
            "[%s] Status: %s | Phone: %s | Message: %s | Request: %s | Response: %s\n",
            $timestamp,
            $status,
            $phone,
            substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''),
            json_encode($requestData),
            json_encode($response)
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Sends an SMS using the Tililtech API.
     *
     * @param string $phone The phone number to send the SMS to.
     * @param string $message The message to send.
     * @return array
     */
    public function sendSms($phone, $message)
    {
        $apiUrl = 'https://api.tililtech.com/sms/v3/sendsms';
        $apiKey = setting("tililtech_api_key");
        $senderId = setting("tililtech_sender_id");

        $requestData = [
            'api_key' => $apiKey,
            'service' => 0,
            'mobile' => $phone,
            'response_type' => 'json',
            'shortcode' => $senderId,
            'message' => $message,
        ];

        $response = $this->makeRequest($apiUrl, $requestData);

        // Check if the response is valid
        if (!is_array($response)) {
            $errorResponse = ['status' => 'error', 'message' => 'Invalid response received from Tililtech API'];
            $this->logSmsActivity($phone, $message, $requestData, ['error' => 'Invalid response'], 'INVALID_RESPONSE');
            return $errorResponse;
        }

        // Check if there's a cURL or connection error
        if (isset($response['error'])) {
            $errorResponse = ['status' => 'error', 'message' => 'Connection error: ' . $response['error']];
            $this->logSmsActivity($phone, $message, $requestData, $response, 'CONNECTION_ERROR');
            return $errorResponse;
        }

        // Check if the status code indicates success
        if (isset($response[0]['status_code']) && $response[0]['status_code'] == '1000') {
            $messageId = isset($response[0]['message_id']) ? $response[0]['message_id'] : null;
            $successResponse = [
                'status' => 'success', 
                'message' => 'Message sent successfully!',
                'messageIDs' => $messageId ? [$messageId] : []
            ];
            $this->logSmsActivity($phone, $message, $requestData, $response, 'SUCCESS');
            return $successResponse;
        } else {
            $statusCode = isset($response[0]['status_code']) ? $response[0]['status_code'] : 'unknown';
            $errorDesc = isset($response[0]['status_desc']) ? $response[0]['status_desc'] : 'Unknown error';
            $errorResponse = ['status' => 'error', 'message' => 'Failed to send message: ' . $errorDesc];
            $this->logSmsActivity($phone, $message, $requestData, $response, 'ERROR_' . $statusCode);
            return $errorResponse;
        }
    }

    /**
     * Fetches the SMS balance for the Tililtech account.
     *
     * @return array|null An array with "units" and "value" keys representing the currency code and balance value respectively, or null on error.
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
            'units' => 'KES',
            'value' => floatval($response['wallet']['credit_balance']),
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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Tililtech API cURL Error: " . $error);
            return ['error' => $error, 'http_code' => $httpCode];
        }

        $decoded = json_decode($response, true);
        
        if ($decoded === null) {
            error_log("Tililtech API Invalid JSON Response: " . $response);
            return ['error' => 'Invalid JSON response', 'raw_response' => $response];
        }

        return $decoded;
    }
}