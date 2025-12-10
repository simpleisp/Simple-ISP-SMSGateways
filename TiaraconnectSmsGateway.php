<?php

namespace App\Gateways\UserDefined;

class TiaraconnectSmsGateway
{
    /**
     * Set information about the Tiaraconnect SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Tiaraconnect SMS Gateway',
            'description' => 'A gateway for sending SMS via Tiaraconnect API.',
            'author' => 'SimpleISP',
            'website' => 'https://simplux.africa',
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
                'name' => 'tiaraconnect_gateway',
                'value' => setting("tiaraconnect_gateway"),
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'password',
                'name' => 'tiaraconnect_api_key',
                'value' => setting("tiaraconnect_api_key"),
                'description' => 'Bearer token for API authorization',
            ],
            'sender_id' => [
                'label' => 'Sender ID / Short Code',
                'type' => 'text',
                'name' => 'tiaraconnect_sender_id',
                'value' => setting("tiaraconnect_sender_id"),
                'description' => 'Sender ID or short code used in sending SMS',
            ],
        ];
    }

    /**
     * Log SMS activity to a file in the same directory
     *
     * @param string $phone
     * @param string $message
     * @param array $apiData
     * @param array $response
     * @param string $status
     */
    private function logSmsActivity($phone, $message, $apiData, $response, $status)
    {
        $logFile = __DIR__ . '/tiaraconnect_sms.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $logEntry = sprintf(
            "[%s] Status: %s | Phone: %s | Message: %s | API Data: %s | Response: %s\n",
            $timestamp,
            $status,
            $phone,
            substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''),
            json_encode($apiData),
            json_encode($response)
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Send SMS using the Tiaraconnect API.
     *
     * @param string $phone
     * @param string $message
     * @param string|null $refId Optional reference ID from client system
     * @return array
     */
    public function sendSms($phone, $message, $refId = null)
    {
        $apiKey = setting('tiaraconnect_api_key');
        $senderId = setting('tiaraconnect_sender_id');
        $endpoint = 'https://api2.tiaraconnect.io/api/messaging/sendsms';

        // Validate required settings
        if (empty($apiKey)) {
            return ['status' => 'error', 'message' => 'API Key is not configured'];
        }
        
        if (empty($senderId)) {
            return ['status' => 'error', 'message' => 'Sender ID is not configured'];
        }

        // Prepare request data according to Tiara API specification
        $postData = [
            'from' => $senderId,
            'to' => $phone,
            'message' => $message,
        ];

        // Add optional refId if provided
        if (!empty($refId)) {
            $postData['refId'] = $refId;
        }

        // Initialize cURL
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Tiaraconnect-SMS-Client/1.0',
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // Handle cURL errors
        if ($err) {
            $errorResponse = ['status' => 'error', 'message' => 'cURL Error: ' . $err];
            $this->logSmsActivity($phone, $message, $postData, $errorResponse, 'CURL_ERROR');
            return $errorResponse;
        }

        // Handle HTTP errors
        if ($httpCode !== 200) {
            $errorResponse = ['status' => 'error', 'message' => "HTTP Error: $httpCode", 'raw_response' => $response];
            $this->logSmsActivity($phone, $message, $postData, ['http_code' => $httpCode, 'response' => $response], 'HTTP_ERROR');
            return $errorResponse;
        }

        // Parse JSON response
        $responseData = json_decode($response, true);

        if ($responseData === null) {
            $errorResponse = ['status' => 'error', 'message' => 'Invalid JSON response from API', 'raw_response' => $response];
            $this->logSmsActivity($phone, $message, $postData, $errorResponse, 'INVALID_JSON');
            return $errorResponse;
        }

        // Check for API errors (non-zero statusCode indicates failure)
        if (isset($responseData['statusCode']) && $responseData['statusCode'] != '0') {
            $errorMessage = $responseData['desc'] ?? 'Unknown API error';
            $errorResponse = ['status' => 'error', 'message' => $errorMessage, 'api_response' => $responseData];
            $this->logSmsActivity($phone, $message, $postData, $responseData, 'API_ERROR');
            return $errorResponse;
        }

        // Check for success status
        if (isset($responseData['status']) && $responseData['status'] === 'SUCCESS') {
            $messageId = $responseData['msgId'] ?? null;
            $cost = $responseData['cost'] ?? null;
            $balance = $responseData['balance'] ?? null;
            
            $successResponse = [
                'status' => 'success', 
                'message' => 'Message sent successfully!',
                'messageId' => $messageId,
                'cost' => $cost,
                'balance' => $balance,
                'api_response' => $responseData
            ];
            
            $this->logSmsActivity($phone, $message, $postData, $responseData, 'SUCCESS');
            return $successResponse;
        }

        // Handle unexpected response format
        $errorResponse = ['status' => 'error', 'message' => 'Unexpected response format', 'api_response' => $responseData];
        $this->logSmsActivity($phone, $message, $postData, $responseData, 'UNEXPECTED_FORMAT');
        return $errorResponse;
    }

    /**
     * Fetches account balance using the Tiaraconnect API.
     *
     * @return array|null
     */
    public function tiaraconnectSmsBalance()
    {
        $apiKey = setting('tiaraconnect_api_key');
        $endpoint = 'https://api2.tiaraconnect.io/api/messaging/checkbalance';

        if (empty($apiKey)) {
            return ['status' => 'error', 'message' => 'API Key is not configured'];
        }

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Tiaraconnect-Balance-Check/1.0',
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($err) {
            error_log('Tiaraconnect Balance Check cURL Error: ' . $err);
            return ['status' => 'error', 'message' => 'cURL Error: ' . $err];
        }

        if ($httpCode !== 200) {
            error_log("Tiaraconnect Balance Check HTTP Error: $httpCode - $response");
            return ['status' => 'error', 'message' => "HTTP Error: $httpCode"];
        }

        $balanceData = json_decode($response, true);

        if ($balanceData === null) {
            return ['status' => 'error', 'message' => 'Invalid JSON response from balance API'];
        }

        // Check for API errors
        if (isset($balanceData['statusCode']) && $balanceData['statusCode'] != '0') {
            $errorMessage = $balanceData['desc'] ?? 'Unknown balance API error';
            return ['status' => 'error', 'message' => $errorMessage];
        }

        // Check for success status
        if (isset($balanceData['status']) && $balanceData['status'] === 'SUCCESS' && isset($balanceData['balance'])) {
            $value = floatval($balanceData['balance']);
            $currency = $balanceData['currency'] ?? 'KES';
            
            return [
                'value' => $value,
                'units' => $currency,
                'raw_response' => $balanceData,
                'status' => 'success'
            ];
        }

        return ['status' => 'error', 'message' => 'Unable to retrieve balance information'];
    }
}