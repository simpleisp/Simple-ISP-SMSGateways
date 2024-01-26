<?php

namespace App\Gateways\UserDefined;

class MspaceSmsGateway
{
    /**
     * Get information about the Mspace SMS gateway.
     *
     * @return array
     */
    public static function getGatewayInfo()
    {
        return [
            'name' => 'Mspace SMS Gateway',
            'description' => 'A gateway for sending SMS via Mspace API.',
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
                'name' => 'mspace_gateway', 
                'value' => setting("mspace_gateway"),
            ],
            'username' => [
                'label' => 'Username',
                'type' => 'text',
                'name' => 'mspace_username', 
                'value' => setting('mspace_username'),
            ],
            'password' => [
                'label' => 'Password',
                'type' => 'text',
                'name' => 'mspace_password', 
                'value' => setting('mspace_password'),
            ],
            'sender_id' => [
                'label' => 'Sender ID',
                'type' => 'text',
                'name' => 'mspace_sender_id', 
                'value' => setting('mspace_sender_id'),
            ],
        ];
    }
    

    /**
     * Send SMS using the mspace API.
     *
     * @return array An array containing the parameters to send sms.
     */
    public function sendSms($phone, $message)
    {
        // Get the Mspace username, password and sender ID from settings
        $username = setting('mspace_username');
        $password = setting('mspace_password');
        $senderID = setting('mspace_sender_id');

        // Build the URL with username, password, senderID, recipient and message values
        $url = 'http://www.mspace.co.ke/mspaceservice/wr/sms/sendtext/username=' . $username . '/password=' . $password . '/senderid=' . $senderID . '/recipient=' . $phone . '/message=' . rawurlencode($message);

        // Initialize cURL session
        $curl = curl_init($url);

        // Set cURL option to return the response
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        // Execute the cURL request
        $response = curl_exec($curl);

        // Close the cURL session
        curl_close($curl);

        // Check for errors
        if ($response === false) {
            return ['status' => 'error', 'message' => 'cURL error'];
        } else {
            $responseArray = json_decode($response, true);

            if ($responseArray === null) {
                return ['status' => 'error', 'message' => 'Could not parse API response'];
            } elseif (isset($responseArray['status']) && $responseArray['status'] === 111) { // Here, check for the correct status code, 111
                // Assume the message was sent successfully if no error was found
                return ['status' => 'success', 'message' => 'Message sent successfully!', 'messageId' => $responseArray['statusDescription']];
            } else {
                // Handle the API error appropriately
                return ['status' => 'error', 'message' => $responseArray['statusDescription'] ?? 'Unknown error'];
            }
        }

        // If all else fails, return the raw response
        return $response;
    }

    /**
     * Fetches account balance using the mspace API.
     *
     * @return array An array containing the balance information.
     */

    public function mspaceSmsBalance()
    {
        // Retrieve the username and password from settings
        $username = setting('mspace_username');
        $password = setting('mspace_password');

        // Build the URL for the API request, including the username and password
        $url = 'http://www.mspace.co.ke/mspaceservice/wr/sms/balance/username=' . $username . '/password=' . $password;

        // Initialize a cURL session
        $ch = curl_init($url);

        // Set the cURL option to return the result as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Execute the cURL request
        $response = curl_exec($ch);

        // Close the cURL session
        curl_close($ch);

        // Check for errors in the cURL request
        if ($response === false) {
            $error = curl_error($ch);
            // If there is an error, return it
            return ['error' => $error];
        } else {
            // If the response contains 'HTTP Status 404', return an error indicating that balance couldn't be fetched
            if (strpos($response, 'HTTP Status 404') !== false) {
                return ['error' => 'Unable to fetch balance'];
            }
            // If the response starts with 'ERR', return the error message
            elseif (substr($response, 0, 3) === 'ERR') {
                return ['error' => $response];
            } else {
                // Otherwise, return the balance as a float with the assumed currency (Kenyan Shillings - KES)
                $balance = floatval($response);
                $currency = "KES"; // Assumed currency
                return ['value' => $balance, 'units' => $currency];
            }
        }
    }
}