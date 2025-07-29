<?php

namespace App\Gateways\UserDefined;

class MspaceSmsGateway
{
    /**
     * Set information about the MSPACE SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'MSPACE SMS Gateway',
            'description' => 'A gateway for sending SMS via MSPACE API.',
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
                'name' => 'mspace_gateway',
                'value' => setting("mspace_gateway"),
            ],
            'username' => [
                'label' => 'Username',
                'type' => 'text',
                'name' => 'mspace_username',
                'value' => setting("mspace_username"),
            ],
            'apiKey' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'mspace_api_key',
                'value' => setting("mspace_api_key"),
            ],
            'senderId' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'mspace_sender_id',
                'value' => setting("mspace_sender_id"),
            ],
        ];
    }

    /**
     * Sends an SMS using the MSPACE API.
     *
     * @param string $phone The phone number to send the SMS to.
     * @param string $message The message to send.
     * @return array
     */
    public function sendSms($phone, $message)
    {
        // Get the necessary settings for the MSPACE API
        $username = setting("mspace_username");
        $apiKey = setting("mspace_api_key");
        $senderId = setting("mspace_sender_id");

        $curl = curl_init();

        // Format the request body as per MSPACE documentation
        $postFields = json_encode([
            'username'  => $username,
            'senderId'  => $senderId,
            'recipient' => $phone,
            'message'   => $message,
        ]);

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.mspace.co.ke/smsapi/v2/sendtext',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => array(
                'apikey: ' . $apiKey,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ['status' => 'error', 'message' => 'cURL Error: ' . $err];
        }

        $responseData = json_decode($response, true);

        if ($responseData === null) {
            return ['status' => 'error', 'message' => 'Invalid JSON response from MSPACE API'];
        }

        try {
            // Handle MSPACE specific response format
            if ($httpCode == 200 && isset($responseData['message']) && is_array($responseData['message'])) {
                $messageInfo = $responseData['message'][0]; // Get first message info
                $status = $messageInfo['status'] ?? 0;
                $statusDesc = $messageInfo['statusDescription'] ?? 'Unknown status';
                
                // Status 111 means message sent successfully for MSPACE
                if ($status == 111 || strpos(strtolower($statusDesc), 'success') !== false) {
                    return ['status' => 'success', 'message' => 'Message sent successfully!'];
                } else {
                    return ['status' => 'error', 'message' => $statusDesc];
                }
            } else {
                $errorMessage = $responseData['error'] ?? $responseData['message'] ?? 'Unknown error from MSPACE API';
                return ['status' => 'error', 'message' => $errorMessage];
            }
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Fetches the SMS balance for the MSPACE account.
     *
     * @return array|null An array with "units" and "value" keys representing the currency code and balance value respectively, or null on error.
     */
    public function mspaceSmsBalance()
    {
        // Get the necessary settings for the MSPACE API
        $username = setting("mspace_username");
        $apiKey = setting("mspace_api_key");

        try {
            $curl = curl_init();

            // Format the request body as per MSPACE documentation
            $postFields = json_encode([
                'username' => $username,
            ]);

            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.mspace.co.ke/smsapi/v2/balance',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $postFields,
                CURLOPT_HTTPHEADER => array(
                    'apikey: ' . $apiKey,
                    'Content-Type: application/json'
                ),
            ));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                return null;
            }

            // Handle MSPACE balance response - it returns just a number
            if ($httpCode == 200) {
                // Check if response is just a number (balance)
                if (is_numeric($response)) {
                    return [
                        "units" => 'KES',
                        "value" => floatval($response),
                    ];
                }
                
                // Fallback: try to parse as JSON
                $responseData = json_decode($response, true);
                if (isset($responseData['balance'])) {
                    return [
                        "units" => 'KES',
                        "value" => floatval($responseData['balance']),
                    ];
                }
            }

            return null;

        } catch (Exception $e) {
            return null;
        }
    }
}
