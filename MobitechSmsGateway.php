<?php

namespace App\Gateways\UserDefined;

class MobitechSmsGateway
{
    /**
     * Get information about the mobitech SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'mobitech SMS Gateway',
            'description' => 'A gateway for sending SMS via mobitech API.',
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
                'name' => 'mobitech_gateway', 
                'value' => setting("mobitech_gateway"),
            ],
            'api_key' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'mobitech_api_key', 
                'value' => setting('mobitech_api_key'),
            ],
            'sender_id' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'mobitech_sender_id', 
                'value' => setting('mobitech_sender_id'),
            ],
        ];
    }
    

    /**
     * Send SMS using the mobitech API.
     *
     * @return array An array containing the parameters to send sms.
     */
    public function sendSms($phone, $message)
    {
         // Get the API key and sender ID from the settings
        $apiKey = setting("mobitech_api_key");
        $sender_id = setting("mobitech_sender_id");
        
        // Initialize a new cURL session
        $curl = curl_init();

        // Set the cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.mobitechtechnologies.com/sms/sendsms',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "mobile": "'.$phone.'",
                "response_type": "json",
                "sender_name": "'.$sender_id.'",
                "service_id": 0,
                "message": "'.$message.'"
            }',
            CURLOPT_HTTPHEADER => array(
                'h_api_key: '.$apiKey,
                'Content-Type: application/json'
            ),
        ));
        
        // Send the cURL request and store the response
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        // Close the cURL session
        curl_close($curl);
        
        // Check if there was an error
        if ($err) {
            return ['status' => 'error', 'message' => $err];
        } else {
            $responseArray = json_decode($response, true);
            
            // Check if the status code is present in the response
            if (isset($responseArray[0]['status_code'])) {
                $status_code = $responseArray[0]['status_code'];
                
                // Check the status code and return a user-friendly message accordingly
                switch ($status_code) {
                    case '1000':
                        return ['status' => 'success', 'message' => 'Message sent successfully!'];
                    case '1001':
                        return ['status' => 'error', 'message' => 'Invalid short code'];
                    case '1002':
                        return ['status' => 'error', 'message' => 'Network not allowed'];
                    case '1003':
                        return ['status' => 'error', 'message' => 'Invalid mobile number'];
                    case '1004':
                        return ['status' => 'error', 'message' => 'Low bulk credits'];
                    case '1005':
                        return ['status' => 'error', 'message' => 'Internal system error'];
                    case '1006':
                        return ['status' => 'error', 'message' => 'Invalid credentials'];
                    case '1007':
                        return ['status' => 'error', 'message' => 'Db connection failed'];
                    case '1008':
                        return ['status' => 'error', 'message' => 'Db selection failed'];
                    case '1009':
                        return ['status' => 'error', 'message' => 'Data type not supported'];
                    case '1010':
                        return ['status' => 'error', 'message' => 'Request type not supported'];
                    case '1011':
                        return ['status' => 'error', 'message' => 'Invalid user state or account suspended'];
                    case '1012':
                        return ['status' => 'error', 'message' => 'Mobile number in DND'];
                    case '1013':
                        return ['status' => 'error', 'message' => 'Invalid API Key'];
                    case '1014':
                        return ['status' => 'error', 'message' => 'IP not allowed'];
                    default:
                        return ['status' => 'error', 'message' => 'Unknown error occurred'];
                }
            } else {
                return ['status' => 'error', 'message' => 'Unknown error occurred'];
            }
        }
    }

    /**
     * Fetches account balance using the mobitech API.
     *
     * @return array An array containing the balance information.
     */

    public function mobitechSmsBalance()
    {
        // Get the API key from the settings
        $apiKey = setting("mobitech_api_key");

        // Initialize a new cURL session
        $curl = curl_init();

        // Set the cURL options
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.mobitechtechnologies.com/sms/units', // API endpoint to retrieve balance
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'h_api_key: ' . $apiKey
            ),
        ));

        // Send the cURL request and store the response
        $response = curl_exec($curl);
        $err = curl_error($curl);

        // Close the cURL session
        curl_close($curl);

        // Check if there was an error
        if ($err) {
            return null;
        } else {
            $responseArray = json_decode($response, true);

            // Check if the credit balance is present in the response
            if (isset($responseArray['credit_balance'])) {
                $credit_balance = $responseArray['credit_balance'];

                // Extract the balance value from the credit balance string
                $value = floatval($credit_balance);

                // Set the default currency (e.g., "KES" for Kenyan Shillings)
                $currency = "Units";

                return ['value' => $value, 'units' => $currency];
            } else {
                return null;
            }
        }
    }
}