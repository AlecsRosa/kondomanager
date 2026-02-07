<?php

return [
    /* ------------------------------------------------------------------
     | Backend notifications
     | ------------------------------------------------------------------ */
    'success_update_notification_preferences' => 'As suas preferências de notificação foram atualizadas com sucesso.',
    'error_update_notification_preferences'   => 'Ocorreu um erro ao tentar atualizar as suas preferências de notificação.',
    'success_save_general_settings'           => 'As configurações gerais foram guardadas com sucesso.',
    'error_save_general_settings'             => 'Ocorreu um erro durante a gravação das configurações gerais.',
    'success_save_cron_settings'              => 'As configurações de automação cloud foram guardadas com sucesso',
    'error_save_cron_settings'                => 'Ocorreu um erro ao guardar as configurações de automação cloud',
    'success_regenerate_cron_token'           => 'Token do webhook regenerado com sucesso',
    'error_regenerate_cron_token'             => 'Ocorreu um erro ao regenerar o token',
    /* ------------------------------------------------------------------
     | Front‑end strings (headings, titles, descriptions)
     | ------------------------------------------------------------------ */
    'header' => [
        'settings_head'                => 'Configurações',
        'settings_title'               => 'Configurações da aplicação',
        'settings_description'         => 'A seguir uma lista de todas as configurações disponíveis para a aplicação',
        'general_settings_title'       => 'Configurações gerais',
        'general_settings_description' => 'Nesta página pode gerir as configurações gerais da aplicação',
        'cron_settings_title'          => 'Automação cloud (Cron externo)',
        'cron_settings_description'    => 'Utilize esta função se o seu alojamento não suporta cron jobs a cada minuto. Serviços suportados: cron-job.org',
    ],
    /* ------------------------------------------------------------------
     | Labels
     | ------------------------------------------------------------------ */
    'label' => [
        'manage'     => 'Gerir',
        'settings'   => 'Configurações',
        'update_now' => 'Atualizar agora',
    ],
    /* ------------------------------------------------------------------
     | Empty‑state / dialog messages
     | ------------------------------------------------------------------ */
    'dialogs' => [
        'general_settings_title'        => 'Configurações gerais',
        'general_settings_description'  => 'Configurações gerais e opções de personalização da aplicação',
        'users_settings_title'          => 'Gestão de utilizadores',
        'users_settings_description'    => 'Configurações de gestão de utilizadores, papéis e permissões',
        'backups_settings_title'        => 'Gestão de cópias de segurança',
        'backups_settings_description'  => 'Configurações de gestão das cópias de segurança',
        'updates_title'                 => 'Atualizações do sistema',
        'updates_desc_available'        => 'Nova versão disponível: :version',
        'updates_desc_latest'           => 'O sistema está atualizado com a versão mais recente',
        'language_settings_title'       => 'Idioma da aplicação',
        'language_settings_description' => 'Selecione o idioma principal da aplicação',
        'default_building_title'        => 'Abrir condomínio ao iniciar sessão',
        'default_building_description'  => 'Se ativado, o utilizador será redirecionado diretamente para o condomínio selecionado',
        'select_building_title'         => 'Condomínio predefinido',
        'select_building_description'   => 'Selecione o condomínio a abrir automaticamente após o início de sessão',
        'user_registration_title'       => 'Ativar registo de utilizadores',
        'user_registration_description' => 'Se ativado, os utilizadores podem registar-se a partir da página inicial',
        'cron_settings_title'           => 'Automação cloud',
        'cron_settings_description'     => 'Configure cron-job.org para hospedagem compartilhada',
        'enable_external_scheduler_title' => 'Ativar agendador externo',
        'enable_external_scheduler_description' => 'Permitir que serviços terceiros executem as automações',
        'webhook_url_title'             => 'Webhook URL',
        'webhook_url_description'       => 'Copie este URL e configure uma chamada GET a cada 1 minuto no seu serviço externo',
        'webhook_url_badge'             => 'Secreto',
        'security_warning_title'        => 'Segurança IP ativa',
        'security_warning_description'  => 'O sistema aceita apenas chamadas dos endereços IP oficiais do cron-job.org. Se usar outro serviço, esta configuração não funcionará.',
    ],
    /* ------------------------------------------------------------------
     | Placeholders for inputs
     | ------------------------------------------------------------------ */
    'placeholder' => [
        'select_building' => 'Selecionar condomínio',
        'select_language' => 'Selecionar idioma',
        'search_settings' => 'Filter settings...',
        'language' => [
            'it' => 'Italiano',
            'en' => 'Inglês',
            'pt' => 'Português',
        ],
    ],
    /* ------------------------------------------------------------------
     | Action buttons (toolbar, card actions, etc.)
     | ------------------------------------------------------------------ */
    'actions' => [
        'save_settings'    => 'Guardar configurações',
        'copy_url'         => 'Copiar URL',
        'regenerate_token' => 'Regenerar token',
    ],
    /* ------------------------------------------------------------------
     | Confirmations
     | ------------------------------------------------------------------ */
    'confirmations' => [
        'regenerate_token' => 'Tem a certeza? Terá de atualizar o URL no cron-job.org',
    ],
    /* ------------------------------------------------------------------
     | Sidebar
     | ------------------------------------------------------------------ */
    'sidebar' => [
        'users'       => 'Utilizadores',
        'roles'       => 'Papéis',
        'permissions' => 'Permissões',
        'invites'     => 'Convites',
    ],
];