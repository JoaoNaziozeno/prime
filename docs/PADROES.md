# PADRÕES DE DESENVOLVIMENTO - Prime ERP

## 1. Naming Conventions

### Classes

```php
// Models
class Order
class Vehicle
class Customer

// Controllers
class OrderController
class VehicleController

// Traits
trait HasAudit
trait BelongsToTenant

// Actions
class CreateOrderAction
class UpdateOrderAction

// Services
class OrderService
class InventoryService

// Events
class OrderCreated
class OrderShipped

// Listeners
class SendOrderConfirmationEmail

// Jobs
class ProcessOrderPayment
class GenerateInvoice

// Policies
class OrderPolicy
class VehiclePolicy

// Requests
class StoreOrderRequest
class UpdateOrderRequest

// DTOs
class CreateOrderDTO
class UpdateInventoryDTO
```

### Database

```php
// Tables (plural, snake_case)
users
vehicles
orders
order_items

// Columns (snake_case)
id
user_id
vehicle_id
created_at
updated_at
deleted_at

// Foreign Keys
user_id -> references id on users table
vehicle_id -> references id on vehicles table
```

### Methods

```php
// Queries/Getters (camelCase)
getActiveOrders()
findByVin($vin)
getOrdersByStatus($status)

// Actions/Setters (camelCase)
create($data)
update($id, $data)
delete($id)

// Scopes (lowercase após "scope")
scopeActive($query)
scopeByStatus($query, $status)
scopeRecent($query)

// Relationships
public function company(): BelongsTo
public function orders(): HasMany
public function services(): BelongsToMany
```

---

## 2. Arquivo Structure

### Model File

```php
<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAudit;

class Company extends Model
{
    use SoftDeletes, HasAudit;

    // Constantes
    const ACTIVE = 'active';
    const INACTIVE = 'inactive';

    // Fillable
    protected $fillable = [
        'name',
        'email',
        'phone',
    ];

    // Casts
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamentos
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', self::ACTIVE);
    }

    // Accessors (Se necessário)
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // Mutators (Se necessário)
    public function setEmailAttribute($value)
    {
        $this->attributes['email'] = strtolower($value);
    }
}
```

### Service File

```php
<?php

namespace App\Services;

use App\Models\Master\Company;
use App\DTOs\CreateCompanyDTO;
use Illuminate\Database\Eloquent\Collection;
use Exception;

class CompanyService
{
    /**
     * Listar todas as empresas
     */
    public function all(): Collection
    {
        return Company::active()->get();
    }

    /**
     * Buscar empresa por ID
     */
    public function find(int $id): ?Company
    {
        return Company::find($id);
    }

    /**
     * Criar nova empresa
     */
    public function create(CreateCompanyDTO $dto): Company
    {
        // Validar regra de negócio
        if ($this->nameExists($dto->name)) {
            throw new Exception('Empresa com este nome já existe');
        }

        $company = Company::create([
            'name' => $dto->name,
            'email' => $dto->email,
            'phone' => $dto->phone,
        ]);

        // Disparar evento
        event(new CompanyCreated($company));

        return $company;
    }

    /**
     * Atualizar empresa
     */
    public function update(Company $company, array $data): Company
    {
        $company->update($data);
        event(new CompanyUpdated($company));
        return $company;
    }

    /**
     * Deletar empresa
     */
    public function delete(Company $company): bool
    {
        $company->delete();
        event(new CompanyDeleted($company));
        return true;
    }

    private function nameExists(string $name): bool
    {
        return Company::where('name', $name)->exists();
    }
}
```

### Controller File

```php
<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCompanyRequest;
use App\Http\Requests\Master\UpdateCompanyRequest;
use App\Services\CompanyService;
use App\Models\Master\Company;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $service)
    {
    }

    public function index()
    {
        $this->authorize('viewAny', Company::class);
        
        $companies = $this->service->all();
        return view('master.companies.index', compact('companies'));
    }

    public function create()
    {
        $this->authorize('create', Company::class);
        return view('master.companies.create');
    }

    public function store(StoreCompanyRequest $request)
    {
        $this->authorize('create', Company::class);
        
        $dto = StoreCompanyRequest::toDTO($request->validated());
        $company = $this->service->create($dto);
        
        return redirect()->route('companies.show', $company)
            ->with('success', 'Empresa criada com sucesso');
    }

    public function show(Company $company)
    {
        $this->authorize('view', $company);
        return view('master.companies.show', compact('company'));
    }

    public function edit(Company $company)
    {
        $this->authorize('update', $company);
        return view('master.companies.edit', compact('company'));
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $this->authorize('update', $company);
        
        $this->service->update($company, $request->validated());
        
        return redirect()->route('companies.show', $company)
            ->with('success', 'Empresa atualizada com sucesso');
    }

    public function destroy(Company $company)
    {
        $this->authorize('delete', $company);
        
        $this->service->delete($company);
        
        return redirect()->route('companies.index')
            ->with('success', 'Empresa deletada com sucesso');
    }
}
```

### Request File

```php
<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use App\DTOs\CreateCompanyDTO;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:companies',
            'email' => 'required|email|unique:companies',
            'phone' => 'required|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nome é obrigatório',
            'name.unique' => 'Nome já existe',
            'email.required' => 'Email é obrigatório',
            'email.unique' => 'Email já existe',
        ];
    }

    public static function toDTO(array $data): CreateCompanyDTO
    {
        return new CreateCompanyDTO(
            name: $data['name'],
            email: $data['email'],
            phone: $data['phone'],
        );
    }
}
```

### DTO File

```php
<?php

namespace App\DTOs;

readonly class CreateCompanyDTO
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
    ) {
    }
}
```

### Policy File

```php
<?php

namespace App\Policies;

use App\Models\Master\User;
use App\Models\Master\Company;

class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('companies.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('companies.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.edit');
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->hasPermission('companies.delete');
    }
}
```

---

## 3. Testes com Pest

```php
<?php

uses(RefreshDatabase::class);

describe('CompanyService', function () {
    
    test('pode criar uma empresa', function () {
        $dto = new CreateCompanyDTO(
            name: 'Empresa Teste',
            email: 'teste@empresa.com',
            phone: '11999999999'
        );

        $company = (new CompanyService)->create($dto);

        expect($company)->toBeInstanceOf(Company::class)
            ->and($company->name)->toBe('Empresa Teste')
            ->and(Company::count())->toBe(1);
    });

    test('lança exceção se nome já existe', function () {
        Company::factory()->create(['name' => 'Empresa Existente']);

        $dto = new CreateCompanyDTO(
            name: 'Empresa Existente',
            email: 'nova@empresa.com',
            phone: '11999999999'
        );

        (new CompanyService)->create($dto);
    })->throws(Exception::class);

});
```

---

## 4. Migrations Best Practices

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Data columns
            $table->string('name')->index();
            $table->string('email')->unique();
            $table->string('phone')->nullable();

            // Foreign Keys
            $table->foreignId('plan_id')
                ->constrained('plans')
                ->cascadeOnDelete();

            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('status');
            $table->index(['created_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
```

---

## 5. Enums Usage

```php
<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pendente',
            self::CONFIRMED => 'Confirmado',
            self::PROCESSING => 'Processando',
            self::COMPLETED => 'Concluído',
            self::CANCELLED => 'Cancelado',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::PENDING => 'yellow',
            self::CONFIRMED => 'blue',
            self::PROCESSING => 'indigo',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
        };
    }
}
```

---

## 6. Events & Listeners

### Event

```php
<?php

namespace App\Events;

use App\Models\Master\Company;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CompanyCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Company $company)
    {
    }
}
```

### Listener

```php
<?php

namespace App\Listeners;

use App\Events\CompanyCreated;
use App\Notifications\CompanyCreatedNotification;

class SendCompanyCreatedNotification
{
    public function handle(CompanyCreated $event): void
    {
        $event->company->notify(new CompanyCreatedNotification());
    }
}
```

---

## 7. Jobs para Fila

```php
<?php

namespace App\Jobs;

use App\Models\Master\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCompanySetup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Company $company)
    {
    }

    public function handle(): void
    {
        // Processar setup da empresa
        // Criar database tenant
        // Executar seeders
        // Enviar email de boas-vindas
    }
}
```

---

**Última atualização:** 2026-07-06
