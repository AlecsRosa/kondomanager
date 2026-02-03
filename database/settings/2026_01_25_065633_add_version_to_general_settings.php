<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.version', config('app.version', '1.8.0-beta.2'));
    }

    public function down(): void
    {
        $this->migrator->delete('general.version');
    }
};
