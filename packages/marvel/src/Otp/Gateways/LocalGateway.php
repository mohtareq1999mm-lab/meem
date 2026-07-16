<?php

namespace Marvel\Otp\Gateways;

use Marvel\Otp\OtpInterface;
use Marvel\Otp\Result;

class LocalGateway implements OtpInterface
{
    public function __construct()
    {
        // Local gateway requires no external credentials
    }

    public function startVerification($phone_number)
    {
        // Return a static id for local/testing
        return new Result('local-verification-id');
    }

    public function checkVerification($id, $code, $phone_number)
    {
        // Accept static OTP '123456' for local/testing
        if ($code !== null && $code === '123456') {
            return new Result('local-verification-checked');
        }

        return new Result(['Invalid code for local verification']);
    }

    public function sendSms($phone_number, $messageBody)
    {
        return new Result('local-message-sent');
    }
}
