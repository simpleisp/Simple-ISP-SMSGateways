<?php

namespace App\Gateways\UserDefined;

class CelcomSmsGateway
{
    /**
     * Set information about the Celcom SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Celcom SMS Gateway',
            'description' => 'A gateway for sending SMS via Celcom API.',
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
                'name' => 'celcom_gateway', 
                'value' => setting("celcom_gateway"),
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'celcom_api_key', 
                'value' => setting('celcom_api_key'),
            ],
            'partner_id' => [
                'label' => 'Partner ID',
                'type' => 'text',
                'name' => 'celcom_partner_id', 
                'value' => setting('celcom_partner_id'),
            ],
            'sender_id' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'celcom_sender_id', 
                'value' => setting('celcom_sender_id'),
            ],
            'api_endpoint' => [
                'label' => 'API Endpoint',
                'type' => 'select',
                'name' => 'celcom_api_endpoint', 
                'options' => [
                    'mysms' => 'mysms',
                    'isms' => 'isms',
                ],
                'value' => setting('celcom_api_endpoint'),
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
        $logFile = __DIR__ . '/celcom_sms.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $logEntry = sprintf(
            "[%s] Status: %s | Phone: %s | Message: %s | API Data: %s | Response: %s\n",
            $timestamp,
            $status,
            $phone,
            substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''), // Truncate long messages
            json_encode($apiData),
            json_encode($response)
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Send SMS using the Celcom API.
     *
     * @return array An array containing the parameters to send sms.
     */
    public function sendSms($phone, $message)
    {
        $apikey = setting('celcom_api_key');
        $partnerID = setting('celcom_partner_id');
        $shortcode = setting('celcom_sender_id');
        $endpoint = setting('celcom_api_endpoint');

        // Use GET method with query parameters (as shown in working example)
        $params = array(
            'apikey' => $apikey,
            'partnerID' => $partnerID,
            'shortcode' => str_replace(["\u00a0", "\xc2\xa0"], ' ', trim($shortcode)),
            'mobile' => $phone,
            'message' => urlencode($message) // URL encode the message
        );

        $query = http_build_query($params);
        $full_url = "https://$endpoint.celcomafrica.com/api/services/sendsms/?" . $query;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $full_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'CelcomAfrica-SMS-Client/1.0'
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($err) {
            $errorResponse = ['status' => 'error', 'message' => $err];
            $this->logSmsActivity($phone, $message, $params, $errorResponse, 'CURL_ERROR');
            return $errorResponse;
        }

        if ($http_code !== 200) {
            $errorResponse = ['status' => 'error', 'message' => "HTTP Error: $http_code - $response"];
            $this->logSmsActivity($phone, $message, $params, ['http_code' => $http_code, 'response' => $response], 'HTTP_ERROR');
            return $errorResponse;
        }

        $responseData = json_decode($response, true);

        // Add debugging - you can remove this after fixing
        error_log("Celcom API Response: " . $response);

        if ($responseData === null) {
            $errorResponse = ['status' => 'error', 'message' => 'Invalid JSON response from API'];
            $this->logSmsActivity($phone, $message, $params, $errorResponse, 'INVALID_JSON');
            return $errorResponse;
        }

        // Handle the response format from GET endpoint
        if (isset($responseData['success']) && $responseData['success'] === true) {
            // Success response (new format)
            $messageID = isset($responseData['messageId']) ? $responseData['messageId'] : null;
            $successResponse = ['status' => 'success', 'message' => 'Message sent successfully!', 'messageIDs' => [$messageID]];
            $this->logSmsActivity($phone, $message, $params, $responseData, 'SUCCESS');
            return $successResponse;
        } elseif (isset($responseData['success']) && $responseData['success'] === false) {
            // Error response with success: false (new format)
            $errorMessage = isset($responseData['message']) ? $responseData['message'] : 'Unknown error';
            $errorResponse = ['status' => 'error', 'message' => $errorMessage];
            $this->logSmsActivity($phone, $message, $params, $responseData, 'API_ERROR');
            return $errorResponse;
        } elseif (isset($responseData['responses']) && is_array($responseData['responses'])) {
            // Handle responses array format (which you're getting)
            $messageIDs = [];
            foreach ($responseData['responses'] as $responseItem) {
                if (!isset($responseItem['response-code'])) {
                    continue;
                }

                $code = $responseItem['response-code'];
                if ($code == 200 && isset($responseItem['messageid'])) {
                    $messageIDs[] = $responseItem['messageid'];
                } else {
                    // Handle error codes
                    switch($code) {
                        case 1001:
                            $errorResponse = ['status' => 'error', 'message' => 'Invalid sender id'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1001');
                            return $errorResponse;
                        case 1002:
                            $errorResponse = ['status' => 'error', 'message' => 'Network not allowed'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1002');
                            return $errorResponse;
                        case 1003:
                            $errorResponse = ['status' => 'error', 'message' => 'Invalid mobile number'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1003');
                            return $errorResponse;
                        case 1004:
                            $errorResponse = ['status' => 'error', 'message' => 'Low bulk credits'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1004');
                            return $errorResponse;
                        case 1005:
                        case 1007:
                            $errorResponse = ['status' => 'error', 'message' => 'Failed. System error'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_' . $code);
                            return $errorResponse;
                        case 1006:
                            $errorResponse = ['status' => 'error', 'message' => 'Invalid credentials'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1006');
                            return $errorResponse;
                        case 1008:
                            $errorResponse = ['status' => 'error', 'message' => 'No Delivery Report'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1008');
                            return $errorResponse;
                        case 1009:
                            $errorResponse = ['status' => 'error', 'message' => 'unsupported data type'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1009');
                            return $errorResponse;
                        case 1010:
                            $errorResponse = ['status' => 'error', 'message' => 'unsupported request type'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1010');
                            return $errorResponse;
                        case 4090:
                            $errorResponse = ['status' => 'error', 'message' => 'Internal Error. Try again after 5 minutes'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_4090');
                            return $errorResponse;
                        case 4091:
                            $errorResponse = ['status' => 'error', 'message' => 'No Partner ID is Set'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_4091');
                            return $errorResponse;
                        case 4092:
                            $errorResponse = ['status' => 'error', 'message' => 'No API KEY Provided'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_4092');
                            return $errorResponse;
                        case 4093:
                            $errorResponse = ['status' => 'error', 'message' => 'Details Not Found'];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_4093');
                            return $errorResponse;
                        default:
                            $errorResponse = ['status' => 'error', 'message' => 'Unknown error code: ' . $code];
                            $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_UNKNOWN');
                            return $errorResponse;
                    }
                }
            }

            if (empty($messageIDs)) {
                $errorResponse = ['status' => 'error', 'message' => 'No message IDs found in the response'];
                $this->logSmsActivity($phone, $message, $params, $responseData, 'NO_MESSAGE_IDS');
                return $errorResponse;
            } else {
                $successResponse = ['status' => 'success', 'message' => 'Message sent successfully!', 'messageIDs' => $messageIDs];
                $this->logSmsActivity($phone, $message, $params, $responseData, 'SUCCESS');
                return $successResponse;
            }
        } elseif (isset($responseData['response-code'])) {
            // Handle old response format with response codes
            $code = $responseData['response-code'];
            switch($code) {
                case 200:
                    $messageID = isset($responseData['messageid']) ? $responseData['messageid'] : null;
                    $successResponse = ['status' => 'success', 'message' => 'Message sent successfully!', 'messageIDs' => [$messageID]];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'SUCCESS');
                    return $successResponse;
                case 1001:
                    $errorResponse = ['status' => 'error', 'message' => 'Invalid sender id'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1001');
                    return $errorResponse;
                case 1002:
                    $errorResponse = ['status' => 'error', 'message' => 'Network not allowed'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1002');
                    return $errorResponse;
                case 1003:
                    $errorResponse = ['status' => 'error', 'message' => 'Invalid mobile number'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1003');
                    return $errorResponse;
                case 1004:
                    $errorResponse = ['status' => 'error', 'message' => 'Low bulk credits'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1004');
                    return $errorResponse;
                case 1005:
                case 1007:
                    $errorResponse = ['status' => 'error', 'message' => 'Failed. System error'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_' . $code);
                    return $errorResponse;
                case 1006:
                    $errorResponse = ['status' => 'error', 'message' => 'Invalid credentials'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1006');
                    return $errorResponse;
                case 1008:
                    $errorResponse = ['status' => 'error', 'message' => 'No Delivery Report'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1008');
                    return $errorResponse;
                case 1009:
                    $errorResponse = ['status' => 'error', 'message' => 'unsupported data type'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1009');
                    return $errorResponse;
                case 1010:
                    $errorResponse = ['status' => 'error', 'message' => 'unsupported request type'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_1010');
                    return $errorResponse;
                case 4090:
                    $errorResponse = ['status' => 'error', 'message' => 'Internal Error. Try again after 5 minutes'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_4090');
                    return $errorResponse;
                case 4091:
                    $errorResponse = ['status' => 'error', 'message' => 'No Partner ID is Set'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_4091');
                    return $errorResponse;
                case 4092:
                    $errorResponse = ['status' => 'error', 'message' => 'No API KEY Provided'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_4092');
                    return $errorResponse;
                case 4093:
                    $errorResponse = ['status' => 'error', 'message' => 'Details Not Found'];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_4093');
                    return $errorResponse;
                default:
                    $errorResponse = ['status' => 'error', 'message' => 'Unknown error code: ' . $code];
                    $this->logSmsActivity($phone, $message, $params, $responseData, 'ERROR_UNKNOWN');
                    return $errorResponse;
            }
        } else {
            $errorResponse = ['status' => 'error', 'message' => 'Unexpected response format. Response: ' . $response];
            $this->logSmsActivity($phone, $message, $params, ['raw_response' => $response], 'UNEXPECTED_FORMAT');
            return $errorResponse;
        }
    }

    /**
     * Fetches account balance using the Celcom API.
     *
     * @return array An array containing the balance information.
     */

    public function celcomSmsBalance()
    {
        $apikey = setting('celcom_api_key');
        $partnerID = setting('celcom_partner_id');
        $endpoint = setting('celcom_api_endpoint');

        // Initialize a new cURL session
        $curl = curl_init();

        // Prepare the data to be sent
        $curl_post_data = array(
            "partnerID" => $partnerID,
            "apikey" => $apikey,
        );

        // Set the cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://$endpoint.celcomafrica.com/api/services/getbalance/", // API URL for getting balance
            CURLOPT_RETURNTRANSFER => true, // Return the result on success, FALSE on failure
            CURLOPT_ENCODING => '', // No encoding
            CURLOPT_MAXREDIRS => 10, // Maximum amount of HTTP redirections to follow
            CURLOPT_TIMEOUT => 0, // No timeout
            CURLOPT_FOLLOWLOCATION => true, // Follow any Location: headers sent by the server
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // Force HTTP 1.1
            CURLOPT_CUSTOMREQUEST => 'POST', // Custom request method to use
            CURLOPT_POSTFIELDS => json_encode($curl_post_data), // Data to post in HTTP "POST" operation
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json' // Set the content type of the request to JSON
            ),
        ));

        // Execute the cURL request and get the response
        $response = curl_exec($curl);

        // Check for any cURL errors
        if (curl_errno($curl)) {
            echo 'Error:' . curl_error($curl);
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

            $currency = "KES"; // 
            return ['value' => $value, 'units' => $currency];
        } else {
            return null;
        }
    }
}
