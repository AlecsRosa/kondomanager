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
    
    'success_save_mail_settings'              => 'Configuração SMTP guardada com sucesso',
    'error_save_mail_settings'                => 'Erro ao guardar a configuração SMTP',

    /* ------------------------------------------------------------------
     | Mail Status Badge
     | ------------------------------------------------------------------ */
    'mail_status' => [
        'database' => 'SMTP da Base de Dados',
        'env'      => 'Configuração .env',
        'log'      => 'Modo de Segurança (Log)',
    ],

    /* ------------------------------------------------------------------
     | Front‑end strings (headings, titles, descriptions)
     | ------------------------------------------------------------------ */
    'header' => [
        'settings_head'                => 'Configurações',
        'settings_title'               => 'Configurações da aplicação',
        'settings_description'         => 'A seguir uma lista de todas as configurações disponíveis para a aplicação',
        'general_settings_title'       => 'Configurações gerais',
        'general_settings_description' => 'Nesta página pode gerir as configurações gerais da aplicação',
        'cron_settings_title'          => 'Automação cloud (Cron esterno)',
        'cron_settings_description'    => 'Utilize esta função se o seu alojamento não suporta cron jobs a cada minuto. Serviços suportados: cron-job.org',
        
        'mail_settings_title'          => 'Configuração de Email (SMTP)',
        'mail_settings_description'    => 'Configure os parâmetros do servidor para o envio de recibos, avisos e comunicações oficiais.',
    ],
    /* ------------------------------------------------------------------
     | Labels
     | ------------------------------------------------------------------ */
    'label' => [
        'manage'             => 'Gerir',
        'settings'           => 'Configurações',
        'update_now'         => 'Atualizar agora',
        'back_to_settings'   => 'Voltar às configurações',
        
        'mail_host'          => 'Servidor SMTP (Host)',
        'mail_port'          => 'Porta SMTP',
        'mail_username'      => 'Nome de utilizador / Email',
        'mail_password'      => 'Palavra-passe SMTP',
        'mail_encryption'    => 'Encriptação (Segurança)',
        'mail_from_address'  => 'Endereço de email do remetente',
        'mail_from_name'     => 'Nome do remetente a exibir',
        'save_settings'      => 'Guardar configuração',
        'send_test'          => 'Enviar e-mail de teste',

        'enable_db_settings' => 'Ativar configuração da Base de Dados',
        'enable_db_description' => 'Se desativado, o sistema usará os parâmetros definidos no ficheiro .env (ex: Mailtrap).',
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
        
        'mail_settings_title'           => 'Configuração SMTP',
        'mail_settings_description'     => 'Gerir parâmetros SMTP, remetente e teste de envio de notificações.',
        'mail_guide_title'              => 'Guia de configuração',
        'mail_guide_gmail'              => 'Gmail: Ative a verificação em 2 passos e gere uma "Palavra-passe de aplicação". Use a porta 587 com TLS.',
        'mail_guide_smtp2go'            => 'Servidores Gratuitos: Se usa o Altervista, recomendamos o SMTP2Go para contornar bloqueios de portas.',
        'mail_guide_domain'             => 'Dica Pro: Compre um domínio e valide o DNS (SPF/DKIM) para evitar que os emails vão para o spam.',
        
        'test_success_title'            => 'Conexão Bem-sucedida',
        'test_success_message'          => 'O email de teste foi enviado com sucesso para o destinatário.',
        'test_error_title'              => 'Erro de Conexão',
        'test_error_message'            => 'Não foi possível ligar ao servidor SMTP. Verifique os parâmetros e tente novamente.',

        'cron_settings_title'           => 'Automação cloud',
        'cron_settings_description'     => 'Configure cron-job.org para hospedagem compartilhada',
        'enable_external_scheduler_title' => 'Ativar agendador esterno',
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
        'search_settings' => 'Filtrar configurações...',
        'mail_host'       => 'ex: smtp.gmail.com',
        'mail_from_address' => 'ex: geral@seu-dominio.pt',
        'test_recipient'  => 'Inserir e-mail para o teste',
        
        'language' => [
            'it' => 'Italiano',
            'en' => 'Inglês',
            'pt' => 'Português',
        ],
    ],
    'actions' => [
        'save_settings'    => 'Guardar configurações',
        'copy_url'         => 'Copiar URL',
        'regenerate_token' => 'Regenerar token',
    ],
    'confirmations' => [
        'regenerate_token' => 'Tem a certeza? Terá de atualizar o URL no cron-job.org',
    ],
    'sidebar' => [
        'users'       => 'Utilizadores',
        'roles'       => 'Papéis',
        'permissions' => 'Permissões',
        'invites'     => 'Convites',
    ],
];