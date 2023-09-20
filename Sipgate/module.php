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

include_once __DIR__ . '/helper/autoload.php';

class SMSSipgate extends IPSModule
{
    //Helper
    use SMSSG_notification;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        // Function
        $this->RegisterPropertyBoolean('Active', true);
        // Sipgate
        $this->RegisterPropertyString('User', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyInteger('Timeout', 5000);
        // Recipients
        $this->RegisterPropertyString('Recipients', '[]');
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Validation
        $this->ValidateConfiguration();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        // Status
        $formData['status'][0] = [
            'code'    => 101,
            'icon'    => 'active',
            'caption' => 'SMS Sipgate wird erstellt',
        ];
        $formData['status'][1] = [
            'code'    => 102,
            'icon'    => 'active',
            'caption' => 'SMS Sipgate ist aktiv (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][2] = [
            'code'    => 103,
            'icon'    => 'active',
            'caption' => 'SMS Sipgate wird gelöscht (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][3] = [
            'code'    => 104,
            'icon'    => 'inactive',
            'caption' => 'SMS Sipgate ist inaktiv (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][4] = [
            'code'    => 200,
            'icon'    => 'inactive',
            'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug! (ID ' . $this->InstanceID . ')',
        ];
        return json_encode($formData);
    }

    #################### Protected

    protected function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        // User
        if (empty($this->ReadPropertyString('User'))) {
            $result = false;
            $status = 200;
            $text = 'Bitte den angegebenen Benutzer überprüfen!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
        }
        // Password
        if (strlen($this->ReadPropertyString('Password')) <= 3) {
            $result = false;
            $status = 200;
            $text = 'Bitte das angegebene Kennwort überprüfen!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
        }
        // Recipients
        $recipients = json_decode($this->ReadPropertyString('Recipients'));
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                if ($recipient->Use) {
                    $phoneNumber = $recipient->PhoneNumber;
                    if (empty($phoneNumber) || strlen($phoneNumber) < 3) {
                        $result = false;
                        $status = 200;
                        $text = 'Bitte die angegebenen Rufnummern der Empfänger überprüfen!';
                        $this->SendDebug(__FUNCTION__, $text, 0);
                        $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
                    }
                }
            }
        }
        // Check instance
        $active = $this->CheckInstance();
        if (!$active) {
            $result = false;
            $status = 104;
        }
        $this->SetStatus($status);
        return $result;
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function CheckInstance(): bool
    {
        $result = $this->ReadPropertyBoolean('Active');
        if (!$result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Instanz inst inaktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, die Instanz ist inaktiv!', KL_WARNING);
        }
        return $result;
    }
}