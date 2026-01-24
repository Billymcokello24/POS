<?php

namespace App\Helpers;

class PhoneFormatter
{
    public static function toMpesaFormat(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone = ltrim($phone, '0');
        if (!str_starts_with($phone, '254')) {
            $phone = '254' . $phone;
        }
        return $phone;
    }
}

