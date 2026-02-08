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
    
    'success_save_mail_settings'              => 'Configurazione SMTP salvata con successo',
    'error_save_mail_settings'                => 'Errore durante il salvataggio della configurazione SMTP',

    /* ------------------------------------------------------------------
     | Mail Status Badge
     | ------------------------------------------------------------------ */
    'mail_status' => [
        'database' => 'SMTP da Database',
        'env'      => 'Configurazione .env',
        'log'      => 'Modalità Sicura (Log)',
    ],

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
        
        'mail_settings_title'          => 'Configurazione Email (SMTP)',
        'mail_settings_description'    => 'Configura i parametri del server per l\'invio di rate, solleciti e comunicazioni ufficiali ai condomini.',
    ],
    /* ------------------------------------------------------------------
     | Labels
     | ------------------------------------------------------------------ */
    'label' => [
        'manage'             => 'Gestisci',
        'settings'           => 'Impostazioni',
        'update_now'         => 'Aggiorna ora',
        'back_to_settings'   => 'Torna alle impostazioni',
        
        'mail_host'          => 'Server SMTP (Host)',
        'mail_port'          => 'Porta SMTP',
        'mail_username'      => 'Username / Email',
        'mail_password'      => 'Password SMTP',
        'mail_encryption'    => 'Crittografia (Sicurezza)',
        'mail_from_address'  => 'Indirizzo Email mittente',
        'mail_from_name'     => 'Nome visualizzato mittente',
        'save_settings'      => 'Salva configurazione',
        'send_test'          => 'Invia email di test',

        'enable_db_settings' => 'Attiva configurazione da Database',
        'enable_db_description' => 'Se disattivato, il sistema userà i parametri definiti nel file .env (es. Mailtrap).',
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
        
        'mail_settings_title'           => 'Configurazione SMTP',
        'mail_settings_description'     => 'Gestisci i parametri SMTP, mittente e test di invio notifiche.',
        'mail_guide_title'              => 'Guida alla configurazione',
        'mail_guide_gmail'              => 'Gmail: Attiva la verifica in 2 passaggi e genera una "Password per le App". Usa porta 587 con TLS.',
        'mail_guide_smtp2go'            => 'Server Gratuiti: Se usi Altervista, ti consigliamo SMTP2Go per superare i blocchi delle porte.',
        'mail_guide_domain'             => 'Consiglio Pro: Usa un dominio professionale validato per evitare lo Spam.',
        
        'test_success_title'            => 'Connessione riuscita',
        'test_success_message'          => 'L\'email di test è stata inviata correttamente al destinatario.',
        'test_error_title'              => 'Errore di connessione',
        'test_error_message'            => 'Impossibile connettersi al server SMTP. Controlla i parametri e riprova.',

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
        'mail_host'       => 'es. smtp.gmail.com',
        'mail_from_address' => 'es. amministrazione@studio-rossi.it',
        'test_recipient'  => 'Inserisci l\'email per il test',
        
        'language' => [
            'it' => 'Italiano',
            'en' => 'Inglese',
            'pt' => 'Portoghese',
        ],
    ],
    'actions' => [
        'save_settings'    => 'Salva impostazioni',
        'copy_url'         => 'Copia URL',
        'regenerate_token' => 'Rigenera token',
    ],
    'confirmations' => [
        'regenerate_token' => 'Sei sicuro? Dovrai aggiornare l\'URL su cron-job.org',
    ],
    'sidebar' => [
        'users'         => 'Utenti',
        'roles'         => 'Ruoli',
        'permissions'   => 'Permessi',
        'invites'       => 'Inviti',
    ],
];