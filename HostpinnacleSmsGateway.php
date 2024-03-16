<?php

namespace App\Gateways\UserDefined;

class HostpinnacleSmsGateway
{
    /**
     * Set information about the HostPinnacle SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'HostPinnacle SMS Gateway',
            'description' => 'A gateway for sending SMS via HostPinnacle API.',
            'author' => 'Simplux',
            'website' => 'https://www.simplux.africa',
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
            'username' => [
                'label' => 'Username',
                'type' => 'text',
                'name' => 'hostpinnacle_username',
                'value' => setting("hostpinnacle_username"),
            ],
            'password' => [
                'label' => 'Password',
                'type' => 'text',
                'name' => 'hostpinnacle_password',
                'value' => setting("hostpinnacle_password"),
            ],
            'senderId' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'hostpinnacle_sender_id',
                'value' => setting("hostpinnacle_sender_id"),
            ],
            'apiKey' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'hostpinnacle_api_key',
                'value' => setting("hostpinnacle_api_key"),
            ],
        ];
    }
    
    /**
     * Sends an SMS using the HostPinnacle API.
     *
     * @param string $phone The phone number to send the SMS to.
     * @param string $message The message to send.
     * @return array An array containing the response from the HostPinnacle API.
     */
    public function sendSms($phone, $message)
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://smsportal.hostpinnacle.co.ke/SMSApi/send",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query([
                'userid' => setting("hostpinnacle_username"),
                'password' => setting("hostpinnacle_password"),
                'sendMethod' => 'quick',
                'mobile' => $phone,
                'msg' => $message,
                'senderid' => setting("hostpinnacle_sender_id"),
                'msgType' => 'text',
                'duplicatecheck' => 'true',
                'output' => 'json',
            ]),
            CURLOPT_HTTPHEADER => array(
                "apikey: " . setting("hostpinnacle_api_key"),
                "cache-control: no-cache",
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return ['status' => 'error', 'message' => "cURL Error #: $err"];
        } else {
            return ['status' => 'success', 'response' => json_decode($response, true)];
        }
    }

    /**
     * Fetches the SMS balance for the HostPinnacle account.
     *
     * @return array|null An array with "currency" and "value" keys representing the currency code and balance value respectively, or null on error.
     */
    public function hostpinnacleSmsBalance()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://smsportal.hostpinnacle.co.ke/SMSApi/account/readstatus",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query([
                'userid' => setting("hostpinnacle_username"),
                'password' => setting("hostpinnacle_password"),
                'output' => 'json',
            ]),
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/x-www-form-urlencoded"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return null; // Return null on error
        } else {
            $responseData = json_decode($response, true);
            if (isset($responseData['response']['status']) && $responseData['response']['status'] === 'success') {
                $balance = $responseData['response']['account']['smsBalance'];
                return [
                    'currency' => 'KES', // Replace 'XXX' with the appropriate currency code
                    'value' => $balance,
                ];
            } else {
                return null; // Return null if balance retrieval is not successful
            }
        }
    }


}