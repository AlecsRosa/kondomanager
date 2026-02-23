<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.external_cron_enabled', false);
        $this->migrator->add('general.external_cron_token', ''); 
    }
    
    public function down(): void
    {
        $this->migrator->delete('general.external_cron_enabled');
        $this->migrator->delete('general.external_cron_token');
    }
};
