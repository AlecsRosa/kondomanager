<?php

return [
    /* ------------------------------------------------------------------
     | Backend notifications
     | ------------------------------------------------------------------ */
    'success_update_notification_preferences' => 'Your notification preferences have been updated successfully',
    'error_update_notification_preferences'   => 'An error occurred while updating your notification preferences',
    'success_save_general_settings'           => 'General settings are successfully saved.',
    'error_save_general_settings'             => 'An error occurred while saving general settings.',
    'success_save_cron_settings'              => 'Cloud automation settings have been saved successfully',
    'error_save_cron_settings'                => 'An error occurred while saving cloud automation settings',
    'success_regenerate_cron_token'           => 'Webhook token regenerated successfully',
    'error_regenerate_cron_token'             => 'An error occurred while regenerating the token',
    
    'success_save_mail_settings'              => 'SMTP configuration saved successfully',
    'error_save_mail_settings'                => 'An error occurred while saving the SMTP configuration',

    /* ------------------------------------------------------------------
     | Mail Status Badge
     | ------------------------------------------------------------------ */
    'mail_status' => [
        'database' => 'Database SMTP',
        'env'      => '.env Configuration',
        'log'      => 'Safe Mode (Log)',
    ],

    /* ------------------------------------------------------------------
     | Front‑end strings (headings, titles, descriptions)
     | ------------------------------------------------------------------ */
    'header' => [
        'settings_head'                => 'Settings',
        'settings_title'               => 'Application settings',
        'settings_description'         => 'Below is a list of all the configurable settings for the application',
        'general_settings_title'       => 'General settings',
        'general_settings_description' => 'On this page you can manage the general settings of the application',
        'cron_settings_title'          => 'Cloud automation (External cron)',
        'cron_settings_description'    => 'Use this feature if your hosting does not support cron jobs every minute. Supported services: cron-job.org',
        
        'mail_settings_title'          => 'Email Configuration (SMTP)',
        'mail_settings_description'    => 'Configure server parameters for sending installments, reminders, and official communications.',
    ],
    /* ------------------------------------------------------------------
     | Labels
     | ------------------------------------------------------------------ */
    'label' => [
        'manage'             => 'Manage',
        'settings'           => 'Settings',
        'update_now'         => 'Update now',
        'back_to_settings'   => 'Back to settings',
        
        'mail_host'          => 'SMTP Server (Host)',
        'mail_port'          => 'SMTP Port',
        'mail_username'      => 'Username / Email',
        'mail_password'      => 'SMTP Password',
        'mail_encryption'    => 'Encryption (Security)',
        'mail_from_address'  => 'Sender Email address',
        'mail_from_name'     => 'Sender Display Name',
        'save_settings'      => 'Save configuration',
        'send_test'          => 'Send test email',

        'enable_db_settings' => 'Enable Database Configuration',
        'enable_db_description' => 'If disabled, the system will use parameters defined in the .env file (e.g., Mailtrap).',
    ],
    /* ------------------------------------------------------------------
     | Empty‑state / dialog messages
     | ------------------------------------------------------------------ */
    'dialogs' => [
        'general_settings_title'        => 'General settings',
        'general_settings_description'  => 'General application configuration settings',
        'users_settings_title'          => 'User management',
        'users_settings_description'    => 'Settings for managing users, roles, and permissions',
        'backups_settings_title'        => 'Backup management',
        'backups_settings_description'  => 'Settings related to database and file backups',
        'updates_title'                 => 'System updates',
        'updates_desc_available'        => 'New version available: :version',
        'updates_desc_latest'           => 'System is up to date with the latest version',
        
        'mail_settings_title'           => 'SMTP Configuration',
        'mail_settings_description'     => 'Manage SMTP parameters, sender info, and notification testing.',
        'mail_guide_title'              => 'Configuration Guide',
        'mail_guide_gmail'              => 'Gmail: Enable 2-Step Verification and generate an "App Password". Use port 587 with TLS.',
        'mail_guide_smtp2go'            => 'Free Servers: If using Altervista, we recommend SMTP2Go to bypass port blocks.',
        'mail_guide_domain'             => 'Pro Tip: Purchase a domain and validate DNS (SPF/DKIM) to prevent emails from going to spam.',
        
        'test_success_title'            => 'Connection Successful',
        'test_success_message'          => 'The test email has been successfully sent to the recipient.',
        'test_error_title'              => 'Connection Error',
        'test_error_message'            => 'Unable to connect to the SMTP server. Check parameters and try again.',

        'cron_settings_title'           => 'Cloud automation',
        'cron_settings_description'     => 'Configure cron-job.org for shared hosting',
        'enable_external_scheduler_title' => 'Enable external scheduler',
        'enable_external_scheduler_description' => 'Allow third-party services to run automations',
        'webhook_url_title'             => 'Webhook URL',
        'webhook_url_description'       => 'Copy this URL and set up a GET request every 1 minute on your external service',
        'webhook_url_badge'             => 'Secret',
        'security_warning_title'        => 'IP security active',
        'security_warning_description'  => 'The system only accepts requests from official cron-job.org IP addresses. If you use a different service, this configuration will not work.',
    ],
    /* ------------------------------------------------------------------
     | Placeholders for inputs
     | ------------------------------------------------------------------ */
    'placeholder' => [
        'select_building' => 'Select building',
        'select_language' => 'Select language',
        'search_settings' => 'Filter settings...',
        'mail_host'       => 'e.g., smtp.gmail.com',
        'mail_from_address' => 'e.g., info@your-domain.com',
        'test_recipient'  => 'Enter email for testing',
        
        'language' => [
            'it' => 'Italian',
            'en' => 'English',
            'pt' => 'Portuguese',
        ],
    ],
    'actions' => [
        'save_settings'    => 'Save settings',
        'copy_url'         => 'Copy URL',
        'regenerate_token' => 'Regenerate token',
    ],
    'confirmations' => [
        'regenerate_token' => 'Are you sure? You will need to update the URL on cron-job.org',
    ],
    'sidebar' => [
        'users'         => 'Users',
        'roles'         => 'Roles',
        'permissions'   => 'Permissions',
        'invites'       => 'Invites',
    ],
];