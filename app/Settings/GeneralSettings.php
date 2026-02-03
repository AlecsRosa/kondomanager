<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public bool $user_frontend_registration = false;
    public string $language = 'it';
    public string $version = '1.7.0';
    
    public static function group(): string
    {
        return 'general';
    }
    
}