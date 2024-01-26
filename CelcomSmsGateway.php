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

        $curl = curl_init();

        $postFields = array(
            "count" => 1,
            "smslist" => array(
                array(
                    "partnerID" => $partnerID,
                    "apikey" => $apikey,
                    "pass_type" => "plain",
                    "clientsmsid" => 178234,
                    "mobile" => $phone,
                    "message" => $message,
                    "shortcode" => $shortcode
                )
            )
        );

        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://$endpoint.celcomafrica.com/api/services/sendbulk/",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($postFields),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ['status' => 'error', 'message' => $err];
        } else {
            $responseData = json_decode($response, true);

            if ($responseData != null) {
                $messageIDs = [];
                foreach ($responseData['responses'] as $responseItem) {
                    $code = $responseItem['response-code'];
                    switch($code) {
                        case 200:
                            $messageIDs[] = $responseItem['messageid'];
                            break;
                        case 1001:
                            return ['status' => 'error', 'message' => 'Invalid sender id'];
                        case 1002:
                            return ['status' => 'error', 'message' => 'Network not allowed'];
                        case 1003:
                            return ['status' => 'error', 'message' => 'Invalid mobile number'];
                        case 1004:
                            return ['status' => 'error', 'message' => 'Low bulk credits'];
                        case 1005:
                        case 1007:
                            return ['status' => 'error', 'message' => 'Failed. System error'];
                        case 1006:
                            return ['status' => 'error', 'message' => 'Invalid credentials'];
                        case 1008:
                            return ['status' => 'error', 'message' => 'No Delivery Report'];
                        case 1009:
                            return ['status' => 'error', 'message' => 'unsupported data type'];
                        case 1010:
                            return ['status' => 'error', 'message' => 'unsupported request type'];
                        case 4090:
                            return ['status' => 'error', 'message' => 'Internal Error. Try again after 5 minutes'];
                        case 4091:
                            return ['status' => 'error', 'message' => 'No Partner ID is Set'];
                        case 4092:
                            return ['status' => 'error', 'message' => 'No API KEY Provided'];
                        case 4093:
                            return ['status' => 'error', 'message' => 'Details Not Found'];
                        default:
                            return ['status' => 'error', 'message' => 'Unknown error'];
                    }
                }

                if (empty($messageIDs)) {
                    return ['status' => 'error', 'message' => 'No message IDs found in the response'];
                } else {
                    return ['status' => 'success', 'message' => 'Message sent successfully!', 'messageIDs' => $messageIDs];
                }

            } else {
                return ['status' => 'error', 'message' => 'Null Response'];
            }
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