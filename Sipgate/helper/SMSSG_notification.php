<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/SMS/tree/main/Sipgate
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SMSSG_notification
{
    public function SendMessage(string $Text): bool
    {
        if (!$this->CheckInstance()) {
            return false;
        }
        if (empty($Text)) {
            return false;
        }
        $recipients = json_decode($this->ReadPropertyString('Recipients'));
        if (empty($recipients)) {
            return false;
        }
        $result = true;
        foreach ($recipients as $recipient) {
            if (!$recipient->Use) {
                continue;
            }
            $result = $this->SendData($Text, $recipient->PhoneNumber);
            if (!$result) {
                $result = false;
            }
        }
        return $result;
    }

    public function SendMessageEx(string $Text, string $PhoneNumber): bool
    {
        if (!$this->CheckInstance()) {
            return false;
        }
        if (empty($Text)) {
            return false;
        }
        if (empty($PhoneNumber) || strlen($PhoneNumber) <= 3) {
            return false;
        }
        return $this->SendData($Text, $PhoneNumber);
    }

    private function SendData(string $Text, string $PhoneNumber): bool
    {
        if (!$this->CheckInstance()) {
            return false;
        }
        if (empty($Text)) {
            return false;
        }
        if (empty($PhoneNumber) || strlen($PhoneNumber) <= 3) {
            return false;
        }
        $user = $this->ReadPropertyString('User');
        if (empty($user)) {
            return false;
        }
        $password = $this->ReadPropertyString('Password');
        if (empty($password)) {
            return false;
        }
        $result = true;
        $endpoint = 'https://api.sipgate.com/v2/sessions/sms';
        $postfields = json_encode(['smsId' => 's0', 'recipient' => $PhoneNumber, 'message' => $Text]);
        $userPassword = $user . ':' . $password;
        $timeout = $this->ReadPropertyInteger('Timeout');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_USERPWD        => $userPassword,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postfields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => 60]);
        $response = curl_exec($ch);
        $responseData = true;
        if (empty($response)) {
            $response = 'No response received!';
            $responseData = false;
            $result = false;
        }
        $this->SendDebug(__FUNCTION__, 'Response: ' . $response, 0);
        if (!curl_errno($ch)) {
            switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
                case $http_code >= 200 && $http_code < 300:
                    if ($responseData) {
                        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                        $header = substr($response, 0, $header_size);
                        $body = substr($response, $header_size);
                        $this->SendDebug(__FUNCTION__, 'Header: ' . $header, 0);
                        $this->SendDebug(__FUNCTION__, 'Body: ' . $body, 0);
                    }
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, 'HTTP Code: ' . $http_code, 0);
            }
        } else {
            $error_msg = curl_error($ch);
            $result = false;
            $this->SendDebug(__FUNCTION__, 'An error has occurred: ' . json_encode($error_msg), 0);
        }
        curl_close($ch);
        return $result;
    }
}