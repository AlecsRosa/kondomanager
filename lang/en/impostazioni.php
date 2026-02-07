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
    /* ------------------------------------------------------------------
     | Front‑end strings (headings, titles, descriptions)
     | ------------------------------------------------------------------ */
    'header' => [
        'settings_head'                => 'Impostazioni',
        'settings_title'               => 'Application settings',
        'settings_description'         => 'Below is a list of all the configurable settings for the application',
        'general_settings_title'       => 'General settings',
        'general_settings_description' => 'On this page you can manage the general settings of the application',
        'cron_settings_title'          => 'Cloud automation (External cron)',
        'cron_settings_description'    => 'Use this feature if your hosting does not support cron jobs every minute. Supported services: cron-job.org',
    ],
    /* ------------------------------------------------------------------
     | Labels
     | ------------------------------------------------------------------ */
    'label' => [
        'manage'     => 'Manage',
        'settings'   => 'Settings',
        'update_now' => 'Update now',
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
        'language_settings_title'       => 'Application language',
        'language_settings_description' => 'Select the primary language for the entire application',
        'default_building_title'        => 'Open building on login',
        'default_building_description'  => 'If enabled, users will be automatically redirected to their default building after login',
        'select_building_title'         => 'Default building',
        'select_building_description'   => 'Choose which building should open automatically after login',
        'user_registration_title'       => 'Enable user registration',
        'user_registration_description' => 'If enabled, visitors can create a new account from the home page',
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
        'language' => [
            'it' => 'Italian',
            'en' => 'English',
            'pt' => 'Portuguese',
        ],
    ],
    /* ------------------------------------------------------------------
     | Action buttons (toolbar, card actions, etc.)
     | ------------------------------------------------------------------ */
    'actions' => [
        'save_settings'    => 'Save settings',
        'copy_url'         => 'Copy URL',
        'regenerate_token' => 'Regenerate token',
    ],
    /* ------------------------------------------------------------------
     | Confirmations
     | ------------------------------------------------------------------ */
    'confirmations' => [
        'regenerate_token' => 'Are you sure? You will need to update the URL on cron-job.org',
    ],
    /* ------------------------------------------------------------------
    | Sidebar navigation
    | ------------------------------------------------------------------ */
    'sidebar' => [
        'users'         => 'Users',
        'roles'         => 'Roles',
        'permissions'   => 'Permissions',
        'invites'       => 'Invites',
    ],
];