<?php

namespace App\Gateways\UserDefined;
use AfricasTalking\SDK\AfricasTalking;

class AfricastalkingSmsGateway
{
    /**
     * Set information about the Africastalking SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Africastalking SMS Gateway',
            'description' => 'A gateway for sending SMS via Africastalking API.',
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
                'name' => 'africastalking_gateway',
                'value' => setting("africastalking_gateway"),
            ],
            'senderId' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'africastalking_sender_id',
                'value' => setting("africastalking_sender_id"),
            ],
            'username' => [
                'label' => 'Username',
                'type' => 'text',
                'name' => 'africastalking_username',
                'value' => setting("africastalking_username"),
            ],
            'apiKey' => [
                'label' => 'API Key',
                'type' => 'text',
                'name' => 'africastalking_api_key',
                'value' => setting("africastalking_api_key"),
            ],
        ];
    }

    
    /**
     * Sends an SMS using the Africa's Talking API.
     *
     * @param string $mobile The phone number to send the SMS to.
     * @param string $message The message to send.
     */
    public function sendSms($phone, $message)
    {
        // Get the necessary settings for the Africa's Talking API
        $gateway = setting("africastalking_gateway");
        $sender_id = setting("africastalking_sender_id");
        $username = setting("africastalking_username");
        $apiKey = setting("africastalking_api_key");

        // Create an instance of the Africa's Talking class using the credentials
        $AT = new AfricasTalking($username, $apiKey);

        // Get the SMS service and send the message
        $sms = $AT->sms();

        try {
            $sms->send([
                "to" => $phone,
                "message" => $message,
                "from" => $sender_id,
            ]);

            return ['status' => 'success', 'message' => 'Message sent successfully!'];

        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Fetches the SMS balance for the AfricasTalking account.
     *
     * @return array|null An array with "currency" and "value" keys representing the currency code and balance value respectively, or null on error.
     */
    public function africastalkingSmsBalance()
    {
        // Set your AfricasTalking credentials
        $username = setting("africastalking_username"); // The username of your AfricasTalking account.
        $apiKey = setting("africastalking_api_key"); // The API key for your AfricasTalking account.

        try {
            // Initialize the SDK with your credentials
            $AT = new AfricasTalking($username, $apiKey);

            // Get the Application service
            $application = $AT->application();

            // Fetch the application data, including SMS balance
            $response = $application->fetchApplicationData();

            // Decode the JSON response into an associative array
            $data = json_decode(json_encode($response), true);

            // Get the balance value from the response
            $balance = $data["data"]["UserData"]["balance"];

            // Split the balance string into currency and value using a regular expression
            preg_match("/([A-Z]+) ([\d\.]+)/", $balance, $matches); // Extracts the currency code and balance value from the balance string.
            $currency = $matches[1];
            $value = $matches[2];

            // Return the currency code and balance value as an array
            return [
                "units" => 'KES',
                "value" => $value,
            ];
        } catch (Exception $e) {
            // Log the error and return null
            Log::error($e->getMessage()); // Logs the error message to a log file or other logging service.
            return null; // Returns null to indicate an error occurred.
        }
    }
}