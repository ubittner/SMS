<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class SipgateValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary_SMS(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_NeXXtMobile(): void
    {
        $this->validateModule(__DIR__ . '/../NeXXt Mobile');
    }

    public function testValidateModule_Sipgate(): void
    {
        $this->validateModule(__DIR__ . '/../Sipgate');
    }
}