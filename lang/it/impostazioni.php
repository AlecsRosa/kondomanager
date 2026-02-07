<?php

return [
    /* ------------------------------------------------------------------
     | Backend notifications
     | ------------------------------------------------------------------ */
    'success_update_notification_preferences' => 'Le tue preferenze di notifica sono state aggiornate con successo',
    'error_update_notification_preferences'   => 'Si è verificato un errore nel tentativo di aggiornare le tue preferenze di notifica',
    'success_save_general_settings'           => 'Le impostazioni generali sono state salvate con successo',
    'error_save_general_settings'             => 'Si è verificato un errore durante il salvataggio delle impostazioni generali',
    'success_save_cron_settings'              => 'Le impostazioni di automazione cloud sono state salvate con successo',
    'error_save_cron_settings'                => 'Si è verificato un errore durante il salvataggio delle impostazioni di automazione cloud',
    'success_regenerate_cron_token'           => 'Token webhook rigenerato con successo',
    'error_regenerate_cron_token'             => 'Si è verificato un errore durante la rigenerazione del token',
    /* ------------------------------------------------------------------
     | Front‑end strings (headings, titles, descriptions)
     | ------------------------------------------------------------------ */
    'header' => [
        'settings_head'                => 'Settings',
        'settings_title'               => 'Impostazioni applicazione',
        'settings_description'         => 'Di seguito un elenco di tutte le impostazioni configurabili per l\'applicazione',
        'general_settings_title'       => 'Impostazioni generali',
        'general_settings_description' => 'On this page you can manage the general settings of the application',
        'cron_settings_title'          => 'Automazione cloud (Cron esterno)',
        'cron_settings_description'    => 'Utilizza questa funzione se il tuo hosting non supporta cron jobs ogni minuto. Servizi supportati: cron-job.org',
    ],
    /* ------------------------------------------------------------------
     | Labels
     | ------------------------------------------------------------------ */
    'label' => [
        'manage'     => 'Gestisci',
        'settings'   => 'Impostazioni',
        'update_now' => 'Aggiorna ora',  
    ],
    /* ------------------------------------------------------------------
     | Empty‑state / dialog messages
     | ------------------------------------------------------------------ */
    'dialogs' => [
        'general_settings_title'        => 'Impostazioni generali',
        'general_settings_description'  => 'Impostazioni generali di configurazione dell\'applicazione',
        'users_settings_title'          => 'Gestione utenti',
        'users_settings_description'    => 'Impostazioni di gestione degli utenti, ruoli e permessi',
        'backups_settings_title'        => 'Gestione backups',
        'backups_settings_description'  => 'Impostazioni di gestione dei backups',
        'updates_title'                 => 'Aggiornamenti sistema',
        'updates_desc_available'        => 'Nuova versione disponibile: :version',
        'updates_desc_latest'           => 'Il sistema è aggiornato all\'ultima versione',
        'language_settings_title'       => 'Lingua applicazione',
        'language_settings_description' => 'Seleziona la lingua principale per l\'applicazione',
        'default_building_title'        => 'Apri condominio al login',
        'default_building_description'  => 'Se attivato, l\'utente verrà reindirizzato direttamente al condominio selezionato',
        'select_building_title'         => 'Condominio predefinito',
        'select_building_description'   => 'Seleziona il condominio da aprire automaticamente il gestionale dopo il login',
        'user_registration_title'       => 'Abilita registrazione utenti',
        'user_registration_description' => 'Se attivato, gli utenti possono registrarsi dalla home page',
        'cron_settings_title'           => 'Automazione cloud',
        'cron_settings_description'     => 'Configura cron-job.org per hosting condivisi',
        'enable_external_scheduler_title' => 'Abilita scheduler esterno',
        'enable_external_scheduler_description' => 'Permetti a servizi terzi di eseguire le automazioni',
        'webhook_url_title'             => 'Webhook URL',
        'webhook_url_description'       => 'Copia questo URL e imposta una chiamata GET ogni 1 minuto sul tuo servizio esterno',
        'webhook_url_badge'             => 'Segreto',
        'security_warning_title'        => 'Sicurezza IP attiva',
        'security_warning_description'  => 'Il sistema accetta chiamate solo dagli IP ufficiali di cron-job.org. Se usi un altro servizio, questa configurazione non funzionerà.',
    ],
    /* ------------------------------------------------------------------
     | Placeholders for inputs
     | ------------------------------------------------------------------ */
    'placeholder' => [
        'select_building' => 'Seleziona condominio',
        'select_language' => 'Seleziona lingua',
        'search_settings' => 'Filtra impostazioni...',
        'language' => [
            'it' => 'Italiano',
            'en' => 'Inglese',
            'pt' => 'Portoghese',
        ],
    ],
    /* ------------------------------------------------------------------
     | Action buttons (toolbar, card actions, etc.)
     | ------------------------------------------------------------------ */
    'actions' => [
        'save_settings'    => 'Salva impostazioni',
        'copy_url'         => 'Copia URL',
        'regenerate_token' => 'Rigenera token',
    ],
    /* ------------------------------------------------------------------
     | Confirmations
     | ------------------------------------------------------------------ */
    'confirmations' => [
        'regenerate_token' => 'Sei sicuro? Dovrai aggiornare l\'URL su cron-job.org',
    ],
    /* ------------------------------------------------------------------
    | Sidebar navigation
    | ------------------------------------------------------------------ */
    'sidebar' => [
        'users'         => 'Utenti',
        'roles'         => 'Ruoli',
        'permissions'   => 'Permessi',
        'invites'       => 'Inviti',
    ],
];