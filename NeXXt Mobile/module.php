<?php

/**
 * @project       SMS/NeXXt Mobile
 * @file          module.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class SMSNeXXtMobile extends IPSModule
{
    //Helper
    use SMSNM_Config;
    use SMSNM_Notification;

    //Constants
    private const LIBRARY_GUID = '{5D8B19D3-334B-ED9C-4C34-8FE7EE06462D}';
    private const MODULE_GUID = '{7E6DBE40-4438-ABB7-7EE0-93BC4F1AF0CE}';
    private const MODULE_PREFIX = 'SMSNM';

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        ########## Properties

        //Info
        $this->RegisterPropertyString('Note', '');

        //NeXXt Mobile
        $this->RegisterPropertyString('Token', '');
        $this->RegisterPropertyString('SenderNumber', '+49');
        $this->RegisterPropertyInteger('Timeout', 5000);

        //Recipients
        $this->RegisterPropertyString('Recipients', '[]');

        //Visualisation
        $this->RegisterPropertyBoolean('EnableActive', false);
        $this->RegisterPropertyBoolean('EnableCurrentBalance', true);
        $this->RegisterPropertyBoolean('EnableGetCurrentBalance', true);

        ########## Variables

        //Active
        $id = @$this->GetIDForIdent('Active');
        $this->RegisterVariableBoolean('Active', 'Aktiv', '~Switch', 10);
        $this->EnableAction('Active');
        if (!$id) {
            $this->SetValue('Active', true);
        }

        //Current balance
        $id = @$this->GetIDForIdent('CurrentBalance');
        $this->RegisterVariableString('CurrentBalance', 'Guthaben', '', 20);
        if (!$id) {
            IPS_SetIcon(@$this->GetIDForIdent('CurrentBalance'), 'Information');
        }

        //Get current balance
        $profile = self::MODULE_PREFIX . $this->InstanceID . '.GetCurrentBalance';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Guthaben abfragen', 'Euro', 0x00FF00);
        $this->RegisterVariableInteger('GetCurrentBalance', 'Guthaben abfragen', $profile, 30);
        $this->EnableAction('GetCurrentBalance');

        ########## Timers

        $this->RegisterTimer('GetCurrentBalance', 0, self::MODULE_PREFIX . '_GetCurrentBalance(' . $this->InstanceID . ');');
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

        //WebFront options
        IPS_SetHidden($this->GetIDForIdent('Active'), !$this->ReadPropertyBoolean('EnableActive'));
        IPS_SetHidden($this->GetIDForIdent('CurrentBalance'), !$this->ReadPropertyBoolean('EnableCurrentBalance'));
        IPS_SetHidden($this->GetIDForIdent('GetCurrentBalance'), !$this->ReadPropertyBoolean('EnableGetCurrentBalance'));

        $this->SetTimerInterval('GetCurrentBalance', 0);

        // Validation
        if ($this->ValidateConfiguration()) {
            $this->GetCurrentBalance();
        }
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        //Profiles
        $profiles = ['GetCurrentBalance'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if ($Message == IPS_KERNELSTARTED) {
            $this->KernelReady();
        }
    }

    public function UIShowMessage(string $Message): void
    {
        $this->UpdateFormField('InfoMessage', 'visible', true);
        $this->UpdateFormField('InfoMessageLabel', 'caption', $Message);
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Active':
                $this->SetValue($Ident, $Value);
                break;

            case 'GetCurrentBalance':
                $this->GetCurrentBalance();
                break;
        }
    }

    #################### Private

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        // Token
        if (empty($this->ReadPropertyString('Token'))) {
            $result = false;
            $status = 200;
            $text = 'Bitte den angegebenen Token überprüfen!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
        }
        // Sender number
        if (strlen($this->ReadPropertyString('SenderNumber')) <= 3) {
            $result = false;
            $status = 200;
            $text = 'Bitte die angegebene Absendernummer überprüfen!';
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
        if ($this->CheckMaintenance()) {
            $result = false;
            $status = 104;
        }
        $this->SetStatus($status);
        return $result;
    }

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function CheckMaintenance(): bool
    {
        $result = false;
        if (!$this->GetValue('Active')) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Instanz ist inaktiv!', 0);
            $result = true;
        }
        return $result;
    }
}