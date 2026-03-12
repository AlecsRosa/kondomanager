<?php

namespace App\Providers;

use App\Models\Segnalazione;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\SegnalazionePolicy;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\MigrationsEnded;
use App\Settings\GeneralSettings;
use Illuminate\Support\Facades\URL;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sincronizza la versione dopo ogni migrazione
        Event::listen(MigrationsEnded::class, function () {
            try {
                $settings = app(GeneralSettings::class);
                $settings->version = config('app.version');
                $settings->save();
            } catch (\Exception $e) {
                // Ignora se settings non è ancora configurato
                // (prima installazione in corso)
            }
        });

        JsonResource::withoutWrapping();
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Segnalazione::class, SegnalazionePolicy::class);

        if (config('app.url')) {
            URL::forceRootUrl(config('app.url'));
            
            // Se siamo in una sottocartella, forziamo il base path se non rilevato
            $path = parse_url(config('app.url'), PHP_URL_PATH);
            if ($path && $path !== '/') {
                app('request')->server->set('SCRIPT_NAME', $path . '/index.php');
            }
        }

    }
}
