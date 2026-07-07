# Prime ERP - Setup e Inicialização

## Requisitos

- PHP 8.2+
- Composer
- MySQL 8.0+
- Redis
- Node.js 18+ (para Vite)

## Instalação

### 1. Dependências do Sistema

```bash
# Windows (XAMPP recomendado)
# ou use WSL2 com Linux

# Linux/Mac
sudo apt-get install php mysql-server redis-server nodejs npm
```

### 2. Configure o Projeto

```bash
cd c:\projetos\prime
```

### 3. Instalar Dependências PHP

```bash
composer install
```

### 4. Configurar Variáveis de Ambiente

```bash
# Copiar .env
copy .env.example .env

# Gerar APP_KEY
php artisan key:generate
```

### 5. Configurar Banco de Dados

Editar `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prime_master
DB_USERNAME=root
DB_PASSWORD=
```

### 6. Executar Migrations

```bash
# Migrations do banco MASTER
php artisan migrate --path=database/migrations/master

# Migrations do banco TENANT (depois de configurar tenancy)
php artisan migrate --path=database/migrations/tenant
```

### 7. Instalar Dependências Front-end

```bash
npm install
npm run dev
```

### 8. Iniciar Servidor

```bash
php artisan serve
```

Acesse: `http://localhost:8000`

---

## Estrutura do Projeto

Ver [ARCHITECTURE.md](ARCHITECTURE.md) para detalhes completos.

---

## Comandos Úteis

```bash
# Listar rotas
php artisan route:list

# Criar Model com recursos
php artisan make:model Models/Master/Company -mcfsp

# Criar Controller
php artisan make:controller Http/Controllers/Master/CompanyController --resource

# Criar Livewire Component
php artisan livewire:make Companies.ListCompanies

# Executar testes
php artisan test

# Verificar sintaxe
php artisan tinker
```

---

## Stack de Tecnologia

| Camada | Tecnologia |
|--------|-----------|
| Backend | Laravel 11 |
| Frontend | Blade, Livewire 4, Alpine.js, Tailwind CSS |
| Database | MySQL (Master + Tenant) |
| Cache | Redis |
| Storage | MinIO (S3-compatible) |
| Auth | Sanctum |
| Multi-Tenant | stancl/tenancy |
| Permissions | Spatie Laravel Permission |
| Tests | Pest |

---

## Progresso

Status: **Etapa 1 - Estrutura do Projeto (EM ANDAMENTO)**

Ver [docs/CHECKLIST.md](docs/CHECKLIST.md) para detalhes.

---

## Documentação

- [ARCHITECTURE.md](ARCHITECTURE.md) - Arquitetura geral
- [docs/CHECKLIST.md](docs/CHECKLIST.md) - Checklist de implementação
- [Laravel Docs](https://laravel.com/docs)
- [Livewire Docs](https://livewire.laravel.com)
- [stancl/tenancy Docs](https://tenancy.samuelczech.com)
