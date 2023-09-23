<?php

/**
 * @project       SMS/NeXXt Mobile
 * @file          SMSNM_Notification.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait SMSNM_Notification
{
    /**
     * Gets the current balance from NeXXt Mobile.
     *
     * @return void
     * @throws Exception
     */
    public function GetCurrentBalance(): void
    {
        $this->SetTimerInterval('GetCurrentBalance', 0);
        if ($this->CheckMaintenance()) {
            return;
        }
        $token = $this->ReadPropertyString('Token');
        if (empty($token)) {
            return;
        } else {
            $token = rawurlencode($token);
        }
        $timeout = $this->ReadPropertyInteger('Timeout');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://api.nexxtmobile.de/?mode=user&token=' . $token . '&function=getBalance',
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => 60]);
        $result = curl_exec($ch);
        if (!curl_errno($ch)) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $this->SendDebug(__FUNCTION__, 'HTTP Code: ' . $httpCode, 0);
            switch ($httpCode) {
                case $httpCode >= 200 && $httpCode < 300:
                    $this->SendDebug(__FUNCTION__, 'Response: ' . $result, 0);
                    $data = json_decode($result, true);
                    if (!empty($data)) {
                        if (array_key_exists('isError', $data)) {
                            $isError = $data['isError'];
                            if ($isError) {
                                $this->SendDebug(__FUNCTION__, 'Es ist ein Fehler aufgetreten!', 0);
                            }
                        } else {
                            $this->SendDebug(__FUNCTION__, 'Es ist ein Fehler aufgetreten!', 0);
                        }
                        if (array_key_exists('result', $data)) {
                            if (array_key_exists('balanceFormated', $data['result'])) {
                                $balance = $data['result']['balanceFormated'] . ' €';
                                $this->SendDebug(__FUNCTION__, 'Aktuelles Guthaben: ' . $balance, 0);
                                $this->SetValue('CurrentBalance', $balance);
                            }
                        }
                    } else {
                        $this->SendDebug(__FUNCTION__, 'Keine Rückantwort erhalten!', 0);
                    }
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, 'HTTP Code: ' . $httpCode, 0);
            }
        } else {
            $error_msg = curl_error($ch);
            $this->SendDebug(__FUNCTION__, 'Es ist ein Fehler aufgetreten: ' . json_encode($error_msg), 0);
        }
    }

    public function SendMessage(string $Text): bool
    {
        if ($this->CheckMaintenance()) {
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
        if ($this->CheckMaintenance()) {
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
        if ($this->CheckMaintenance()) {
            return false;
        }
        if (empty($Text)) {
            return false;
        }
        if (empty($PhoneNumber) || strlen($PhoneNumber) <= 3) {
            return false;
        }
        $token = $this->ReadPropertyString('Token');
        if (empty($token)) {
            return false;
        }
        $token = rawurlencode($token);
        $originator = $this->ReadPropertyString('SenderNumber');
        if (empty($originator) || strlen($originator) <= 3) {
            return false;
        }
        $originator = rawurlencode($originator);
        $result = true;
        $messageText = rawurlencode(substr($Text, 0, 360));
        $endpoint = 'https://api.nexxtmobile.de/?mode=user&token=' . $token . '&function=sms&originator=' . $originator . '&recipient=' . $PhoneNumber . '&text=' . $messageText;
        $timeout = $this->ReadPropertyInteger('Timeout');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $endpoint,
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR    => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT        => 60]);
        $response = curl_exec($ch);
        if (!curl_errno($ch)) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            switch ($httpCode) {
                case $httpCode >= 200 && $httpCode < 300:
                    $this->SendDebug(__FUNCTION__, 'Response: ' . $response, 0);
                    $data = json_decode($response, true);
                    if (!empty($data)) {
                        if (array_key_exists('isError', $data)) {
                            $isError = $data['isError'];
                            if ($isError) {
                                $result = false;
                                $this->SendDebug(__FUNCTION__, 'Es ist ein Fehler aufgetreten!', 0);
                            }
                        } else {
                            $result = false;
                            $this->SendDebug(__FUNCTION__, 'Es ist ein Fehler aufgetreten!', 0);
                        }
                    }
                    break;

                default:
                    $this->SendDebug(__FUNCTION__, 'HTTP Code: ' . $httpCode, 0);
            }
        } else {
            $result = false;
            $error_msg = curl_error($ch);
            $this->SendDebug(__FUNCTION__, 'An error has occurred: ' . json_encode($error_msg), 0);
        }
        curl_close($ch);
        if ($result) {
            //Get current balance
            $this->SetTimerInterval('GetCurrentBalance', 30 * 1000);
        }
        return $result;
    }
}