# ERP SaaS - Arquitetura

## 1. Visão Geral

Este é um **ERP SaaS Multi-Tenant** profissional para oficinas mecânicas diesel pesadas, construído com Laravel, Livewire e MySQL.

### Stack Técnica
- **Backend:** Laravel 11 (latest)
- **Frontend:** Blade, Livewire 4, Alpine.js, Tailwind CSS
- **Database:** MySQL (Master + Tenant por empresa)
- **Cache:** Redis
- **Storage:** MinIO (S3-compatible)
- **Autenticação:** Laravel Sanctum
- **Multi-Tenant:** stancl/tenancy
- **Permissões:** Spatie Laravel Permission
- **Filas:** Laravel Queues + Redis
- **Testes:** Pest

---

## 2. Arquitetura Multi-Tenant

### 2.1 Modelo Híbrido

```
┌─────────────────────────────────────────┐
│         MASTER DATABASE (MySQL)         │
├─────────────────────────────────────────┤
│ • Empresas                              │
│ • Planos de Assinatura                  │
│ • Usuários Globais                      │
│ • Assinaturas                           │
│ • Tenants (Configuração)                │
│ • Logs de Autenticação                  │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│      TENANT DATABASE (MySQL)            │
│      (Um por Empresa/Tenant)            │
├─────────────────────────────────────────┤
│ • Clientes                              │
│ • Motoristas                            │
│ • Veículos                              │
│ • Ordens de Serviço                     │
│ • Estoque & Produtos                    │
│ • Serviços                              │
│ • Financeiro                            │
│ • Relatórios                            │
│ • Usuários do Tenant                    │
└─────────────────────────────────────────┘
```

### 2.2 Fluxo de Autenticação

1. Usuário faz login (Master DB)
2. Sanctum valida credenciais
3. Tenant é identificado (subdomínio ou headers)
4. Conexão alternada para Tenant DB
5. Todas operações executadas no Tenant correto

---

## 3. Estrutura de Diretórios

```
prime/
├── app/
│   ├── Models/
│   │   ├── Master/          # Modelos do banco Master
│   │   └── Tenant/          # Modelos do banco Tenant
│   ├── Services/            # Lógica de negócio
│   ├── Actions/             # Ações discretas
│   ├── Policies/            # Autorização
│   ├── DTOs/                # Data Transfer Objects
│   ├── Enums/               # Enums do domínio
│   ├── Events/              # Eventos de domínio
│   ├── Listeners/           # Listeners de eventos
│   ├── Jobs/                # Jobs para filas
│   ├── Observers/           # Observers de modelos
│   ├── Traits/              # Traits reutilizáveis
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Master/
│   │   │   └── Tenant/
│   │   ├── Requests/
│   │   │   ├── Master/
│   │   │   └── Tenant/
│   │   ├── Middleware/
│   │   └── Livewire/
│   └── Providers/
├── database/
│   ├── migrations/
│   │   ├── master/          # Migrations do Master DB
│   │   └── tenant/          # Migrations do Tenant DB
│   ├── factories/
│   │   ├── master/
│   │   └── tenant/
│   └── seeders/
│       ├── master/
│       └── tenant/
├── resources/
│   ├── views/
│   │   ├── layouts/
│   │   ├── master/
│   │   ├── tenant/
│   │   └── shared/
│   ├── css/
│   └── js/
├── routes/
├── config/
│   └── erp.php              # Configurações do ERP
├── docs/
└── tests/
```

---

## 4. Padrões de Desenvolvimento

### 4.1 SOLID & Clean Architecture

- **Single Responsibility:** Cada classe tem uma única responsabilidade
- **Open/Closed:** Aberto para extensão, fechado para modificação
- **Liskov Substitution:** Subtypes substituem tipos base
- **Interface Segregation:** Interfaces específicas
- **Dependency Inversion:** Dependências invertidas

### 4.2 Camadas

```
┌─────────────────────┐
│   Http Layer        │
│  Controllers        │
│  Requests           │
│  Policies           │
└──────────┬──────────┘
           │
┌──────────▼──────────┐
│  Service Layer      │
│  Services           │
│  Actions            │
│  Events             │
└──────────┬──────────┘
           │
┌──────────▼──────────┐
│  Data Layer         │
│  Models             │
│  Queries            │
│  Observers          │
└─────────────────────┘
```

### 4.3 Services

Toda regra de negócio vai em Services. Exemplo:

```php
class OrderService
{
    public function create(CreateOrderDTO $dto): Order
    {
        // Validações de negócio
        // Consultas ao banco
        // Disparo de eventos
        // Retorno de dados
    }
}
```

### 4.4 Actions

Ações discretas e reutilizáveis:

```php
class CreateOrderAction
{
    public function __invoke(CreateOrderDTO $dto): Order
    {
        // Uma ação específica
    }
}
```

### 4.5 Eventos

Desacoplamento via eventos:

```php
event(new OrderCreated($order));
```

---

## 5. Modelos (Models)

### 5.1 Estrutura de Model

Cada model possui:

- **Model**: Definição da entidade
- **Migration**: Estrutura do banco
- **Factory**: Dados de teste
- **Seeder**: Seeds iniciais
- **Policy**: Autorização
- **Observer**: Listeners automáticos (quando necessário)

### 5.2 Relacionamentos

Todos os relacionamentos completamente definidos:

```php
// One-to-Many
public function orders(): HasMany

// Many-to-One
public function company(): BelongsTo

// Many-to-Many
public function services(): BelongsToMany

// Has-One-Through
public function companyPhone(): HasOneThrough
```

### 5.3 Scopes Úteis

```php
public function scopeActive(Builder $query): Builder
public function scopeRecent(Builder $query): Builder
public function scopeByTenant(Builder $query, $tenant): Builder
```

### 5.4 Casts

```php
protected $casts = [
    'created_at' => 'datetime',
    'status' => OrderStatusEnum::class,
    'metadata' => 'json',
];
```

---

## 6. Controllers

Utilizamos **Resource Controllers** com responsabilidades claras:

```php
// ResourceController padrão
- index()    // Listagem
- create()   // Formulário criação
- store()    // Salvar criação
- show()     // Visualizar
- edit()     // Formulário edição
- update()   // Salvar edição
- destroy()  // Deletar
```

**Regra:** Lógica complexa vai em Services, não em Controllers.

---

## 7. Livewire Components

Estrutura:

```
app/Http/Livewire/
├── Orders/
│   ├── ListOrders.php
│   ├── CreateOrderModal.php
│   ├── EditOrderForm.php
│   └── OrderFilters.php
└── Dashboard/
    └── SalesChart.php
```

---

## 8. Autorização

### 8.1 Policies

Todas as entidades possuem Policies:

```php
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    public function create(User $user): bool
    public function update(User $user, Order $order): bool
    public function delete(User $user, Order $order): bool
}
```

### 8.2 Uso em Controllers

```php
$this->authorize('update', $order);
```

---

## 9. Auditoria

Todas as alterações críticas são auditadas:

```
audit_logs table:
- user_id
- model_type
- model_id
- action (created, updated, deleted)
- old_values
- new_values
- ip_address
- user_agent
- timestamp
```

---

## 10. Testes

Utilizamos **Pest** para testes:

```
tests/
├── Unit/
│   ├── Models/
│   ├── Services/
│   └── Actions/
├── Feature/
│   ├── Api/
│   ├── Http/
│   └── Livewire/
└── Pest.php
```

---

## 11. Próximos Passos

1. ✅ Estrutura do Projeto
2. ⏳ Infraestrutura (Migrations Master)
3. ⏳ Multi-Tenant Configuration
4. ⏳ Banco MASTER (Empresas, Planos, Usuários)
5. ⏳ Banco TENANT (Schema base)
6. ⏳ Autenticação (Sanctum + Login)
... (continuar até Portal do Cliente)

---

## 12. Convenções

### Naming
- Models: `PascalCase` singular (Order, Vehicle)
- Controllers: `PascalCase` com `Controller` (OrderController)
- Tables: `snake_case` plural (orders, vehicles)
- Columns: `snake_case` (order_number, created_at)
- Methods: `camelCase` (getActiveOrders)

### Database
- Sempre usar `id` como chave primária
- Sempre usar `timestamps` (created_at, updated_at)
- Soft deletes quando apropriado
- Foreign keys com `_id` suffix
- Índices em colunas de busca frequente

### Code
- PSR-12 coding standard
- Type hints completos
- Docblocks where helpful
- Constants em UPPER_CASE

---

**Status:** Etapa 1 iniciada
**Última atualização:** 2026-07-06
