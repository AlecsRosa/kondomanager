[![Read in English](https://img.shields.io/badge/Read_in-English-red.svg)](README.en.md)
[![Leggi in Italiano](https://img.shields.io/badge/Leggi_in-Italiano-green.svg)](README.md)
[![Leia em Português](https://img.shields.io/badge/Leia_em-Português-yellow.svg)](README.pt-br.md)
[![Generic badge](https://img.shields.io/badge/Version-1.8.0-blue.svg)](https://github.com/vince844/kondomanager-free/releases)
[![License](https://img.shields.io/badge/License-AGPL_3.0-blue.svg)](https://opensource.org/licenses/AGPL-3.0)

# KondoManager - Software Gratuito e de Código Aberto para Gestão de Condomínios

**KondoManager** é um software inovador, gratuito e de código aberto para gestão de condomínios, desenvolvido em **Laravel** e base de dados **MySQL**. Pensado para simplificar a vida dos administradores de condomínios, oferecendo ao mesmo tempo transparência e serviços digitais para os condóminos.

---

## Capturas de Ecrã

<table>
  <tr>
    <td><img src="https://dev.karibusana.org/github/Screenshot-3.png" alt="Painel de controlo" width="100%"></td>
    <td><img src="https://dev.karibusana.org/github/Screenshot-2.png" alt="Reporte de avarias" width="100%"></td>
  </tr>
  <tr>
    <td><img src="https://dev.karibusana.org/github/Screenshot-1.png" alt="Quadro de avisos do condomínio" width="100%"></td>
    <td><img src="https://dev.karibusana.org/github/Screenshot-6.png" alt="Arquivo de documentos" width="100%"></td>
  </tr>
  <tr>
    <td><img src="https://dev.karibusana.org/github/Screenshot-4.png" alt="Agenda do condomínio" width="100%"></td>
    <td><img src="https://dev.karibusana.org/github/Screenshot-5.png" alt="Gestão de utilizadores e permissões" width="100%"></td>
  </tr>
</table>

---

## Experimente a Demonstração

Pode visualizar uma demonstração do projeto no seguinte endereço:

👉 **[Demonstração KondoManager](https://rebrand.ly/kondomanager)**

**Atenção:** Por questões de segurança, algumas funcionalidades como o envio de emails e notificações foram desativadas.

**Credenciais de acesso:**

| Função | Email | Palavra-passe |
| :--- | :--- | :--- |
| **Administrador** | `admin@kondomanager.it` | `Pa$$w0rd!` |
| **Utilizador** | `user@kondomanager.it` | `Pa$$w0rd!` |

---

## Funcionalidades do Sistema de Gestão

### Funções Principais

- Sistema de atualização automática a partir do painel de administrador
- Gestão de cadastros de condomínios e fornecedores do condomínio
- Gestão de reportes de avarias do condomínio
- Quadro de avisos digital do condomínio para comunicações
- Arquivo de documentos e categorias do condomínio
- Agenda de prazos com gestão de recorrências
- Gestão avançada de utilizadores, funções e permissões
- Notificações automáticas por email
- Autenticação com proteção de dois fatores
- Sistema de convites para registo de utilizadores
- Localização: Italiano, Inglês, Português

### Módulo de Contabilidade de Gestão e Estrutura

- Gestão de edifícios, escadas e imóveis
- Contas correntes do condómino
- Tabelas de permilagem ilimitadas
- Gestão de exercícios contabilísticos
- Gestões ordinárias e extraordinárias
- Criação de plano de contas
- Geração de plano de prestações com recorrências avançadas
- Registo de recebimentos com repartição automática ou manual
- Partida dupla
- Emissão inteligente de prestações
- Extrato de conta do cadastro
- Caixa de entrada inteligente para prazos interativos na agenda

---

## Requisitos Mínimos

Para instalar o KondoManager, o seu ambiente de servidor deve satisfazer os seguintes requisitos:

- **PHP** >= 8.2
- **Base de dados:** MySQL 5.7+ ou MariaDB 10.3+
- **Extensões PHP:** `zip`, `curl`, `openssl`, `mbstring`, `fileinfo`, `dom`, `xml` - consulte o guia do [Laravel](https://laravel.com/docs/12.x/deployment) para mais informações
- **Para instalação manual:** Node.js & NPM, Composer

---

## Instalação Guiada (Recomendada para utilizadores menos experientes)

Para utilizadores menos experientes ou para instalações rápidas em alojamento partilhado (cPanel, Plesk, etc.), criámos um assistente automatizado.

### 1. Nova Instalação Guiada

1. Descarregue o [ficheiro de instalação](https://kondomanager.short.gy/km-installer) do site oficial do Kondomanager
2. Extraia e carregue o ficheiro `index.php` na **raiz** do seu servidor (via FTP ou Gestor de Ficheiros no cPanel).
3. Abra o navegador no endereço: `https://seusite.com/index.php`.
4. Siga o procedimento guiado no ecrã.

Para mais detalhes, visite o [guia oficial de instalação](https://www.kondomanager.com/docs/installation.html) ou o nosso [canal YouTube](https://www.youtube.com/@Kondomanager)

### 2. Atualização Automática a partir do Painel de Administrador

O sistema de atualização automática gere automaticamente o ciclo de vida das atualizações, garantindo a segurança dos dados com apenas alguns cliques diretamente no painel de administração.

**Atenção:** Se não configurar os processos `CronJob`, a atualização automática não funcionará.

**Como Configurar CronJob**

Aceda ao seu painel de alojamento (cPanel, Plesk) na secção "Cron Jobs" ou "Agendamento de Tarefas". Configure a execução a cada minuto (* * * * *).

**Exemplo para ambiente local MAMP (Mac):**
```bash
/Applications/MAMP/bin/php/php8.2.0/bin/php suapasta/artisan schedule:run >> /dev/null 2>&1
```
**Exemplo para Servidor Partilhado (cPanel/Linux):**
```bash
/usr/local/bin/php /home/seusite/public_html/artisan schedule:run >> /dev/null 2>&1
```

Certifique-se de usar o caminho absoluto para o executável PHP v8.2+, por exemplo
/usr/local/bin/ea-php82 /home/seusite/domain_path/path/to/cron/script 

No exemplo anterior, substitua "ea-php99" pela versão PHP atribuída ao domínio que deseja utilizar. Verifique no MultiPHP Manager a versão PHP efetivamente atribuída a um domínio.

### 3. Atualização da Versão 1.7.0 para 1.8.0

As atualizações automáticas estão disponíveis a partir da versão 1.8.0, portanto, se ainda estiver a utilizar a versão 1.7.0 e quiser atualizar, deve seguir os seguintes passos:

1. Certifique-se de ter uma cópia de segurança da `base de dados` e dos ficheiros da pasta `storage`
2. Descarregue o [ficheiro de atualização](https://kondomanager.short.gy/km-installer) do site oficial do Kondomanager
3. Carregue o ficheiro `index.php` na raiz do seu servidor
4. Abra o navegador no endereço: `https://seusite.com/index.php`.
5. O sistema detetará automaticamente a versão anterior instalada.
6. Clique em **"Atualizar agora"** e siga os passos guiados.

**O que o sistema faz automaticamente:**

- Cópia de segurança automática do ficheiro `.env`.
- Descarregamento e instalação dos novos ficheiros principais.
- Restauro dos dados e das configurações.
- Execução das migrações da base de dados.
- Limpeza e otimização da cache.

**Importante:** Não feche a página do navegador durante o processo de atualização. O ficheiro `index.php` eliminar-se-á automaticamente no final da operação por segurança.

---

## Instalação Manual (Para programadores e utilizadores avançados)

Se deseja contribuir para o código ou tem acesso SSH completo ao servidor.

### Primeira Instalação

1. **Clone o repositório**
```bash
git clone https://github.com/vince844/kondomanager-free.git
cd kondomanager-free
```

2. **Instale as dependências**
```bash
composer install
npm install
```

3. **Configure o ambiente**
```bash
cp .env.example .env
php artisan key:generate
```

Edite o ficheiro `.env` inserindo os parâmetros da sua base de dados (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).

4. **Configuração da Base de Dados**
```bash
php artisan migrate
php artisan db:seed
```

5. **Iniciar**
```bash
npm run dev
php artisan serve
```

Visite http://localhost:8000.

**Credenciais Predefinidas:** `admin@km.com` / `password` (Lembre-se de alterá-las imediatamente indo ao seu perfil `/settings/profile`).

---

### Atualização Manual (via SSH/Terminal)

Se preferir atualizar manualmente, siga rigorosamente estes passos para garantir a compatibilidade com o sistema de versionamento:

1. **Cópia de Segurança da Base de Dados (Recomendado)**
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

2. **Atualizar código e dependências**
```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm install && npm run build
```

3. **PASSO CRÍTICO**

É fundamental limpar a cache das configurações antes de migrar, especialmente para o novo sistema de configurações de versionamento:
```bash
php artisan config:clear
```

4. **Migração e otimização**
```bash
php artisan migrate --force
php artisan optimize:clear
php artisan storage:link
```

5. **Configuração e Início das Filas (Queues)** 

O sistema utiliza por predefinição o controlador de base de dados (também pode utilizar Redis se preferir) para gerir processos em segundo plano. É necessário iniciar o worker para processar as tarefas em fila.
```bash
php artisan queue:work
```
**Nota:** Em ambiente de produção, recomenda-se configurar o Supervisor para manter o processo ativo.

### Verificar Versão Instalada

Pode verificar a versão atual e o funcionamento das configurações através do Tinker:
```bash
php artisan tinker
>>> config('app.version')
```

---

## Documentos Úteis

- [Documentação Laravel](https://laravel.com/docs)
- [Documentação Vue.js](https://vuejs.org/guide/introduction.html)
- [Documentação Tailwind CSS](https://tailwindcss.com/docs)
- [Documentação Inertia.js](https://inertiajs.com/)
- [Spatie Laravel Settings](https://spatie.be/docs/laravel-settings)

---

## Como Contribuir

Quem quiser contribuir para fazer crescer o projeto é sempre bem-vindo!

Para poder contribuir, recomenda-se seguir as indicações descritas na [documentação oficial](https://github.com/vince844/kondomanager-free/blob/main/CONTRIBUTING). Se quiser contribuir ativamente com melhorias simples ou correções, pode [procurar entre as issues](https://github.com/vince844/kondomanager-free/issues) abertas.

---

## Apoie o Projeto

Desenvolver software de código aberto requer muito empenho e dedicação. Ficarei grato se decidir apoiar o projeto.

[Apoie o KondoManager no Patreon](https://www.patreon.com/KondoManager)

---

## Feedback & Suporte

- **Feedback:** Utilize a secção ["Issues" ou "Discussions"](https://github.com/vince844/kondomanager-free/issues) deste repositório.
- **Suporte:** Para pedidos de personalização ou suporte dedicado, utilize o [formulário de contacto](https://dev.karibusana.org/gestionale-condominio-contatti.html) no site oficial.

---

## Licença

Este projeto é lançado sob a licença [AGPL-3.0](https://github.com/vince844/kondomanager-free?tab=AGPL-3.0-1-ov-file#readme).

---

## Créditos

### Programador Principal:
- [Vincenzo Vecchio](https://github.com/vince844) - Fundador do projeto e programador principal

### Contribuidores:
- [Amnit Haldar](https://github.com/amit-eiitech) - Pela sua valiosa contribuição na criação da instalação guiada
- [k3ntinhu](https://github.com/k3ntinhu) - Pela sua valiosa contribuição na configuração de contentores Docker e pela comunidade portuguesa
- [Stefano B](https://github.com/borghiste) - Por ter reportado e corrigido um erro de segurança
- Todos os contribuidores e programadores da comunidade de código aberto.

---