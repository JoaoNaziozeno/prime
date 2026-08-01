<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import Sidebar from '../components/Sidebar.vue';

const router = useRouter();
const authStore = useAuthStore();

// Estados da lista
const vehicles = ref([]);
const pagination = ref({});
const search = ref('');
const filterStatus = ref('');
const filterType = ref('');
const loading = ref(false);
const error = ref('');

// Estados do formulário
const showForm = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitLoading = ref(false);

const form = ref({
  plate: '',
  model: '',
  brand: '',
  year: new Date().getFullYear(),
  type: 'truck',
  vin: '',
  color: '',
  capacity_tons: '',
  renavam: '',
  license_expiration: '',
  status: 'active',
  branch_id: null
});

// Reset do formulário
const resetForm = () => {
  form.value = {
    plate: '',
    model: '',
    brand: '',
    year: new Date().getFullYear(),
    type: 'truck',
    vin: '',
    color: '',
    capacity_tons: '',
    renavam: '',
    license_expiration: '',
    status: 'active',
    branch_id: null
  };
  isEditing.value = false;
  editingId.value = null;
};

// Carregar veículos da API
const fetchVehicles = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/vehicles', {
      params: {
        search: search.value,
        status: filterStatus.value,
        type: filterType.value,
        page
      }
    });
    vehicles.value = response.data.data;
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      prev_page_url: response.data.prev_page_url,
      next_page_url: response.data.next_page_url,
      total: response.data.total
    };
  } catch (err) {
    error.value = 'Falha ao buscar veículos do servidor. Certifique-se de que a API está rodando.';
  } finally {
    loading.value = false;
  }
};

// Estatísticas simples baseadas no resultado ou estáticas calculadas
const stats = computed(() => {
  const total = pagination.value.total || vehicles.value.length || 0;
  
  // Vamos fazer contagens aproximadas com base no estado atual ou na lista local
  let activeCount = 0;
  let maintenanceCount = 0;
  let inactiveCount = 0;

  vehicles.value.forEach(v => {
    if (v.status === 'active') activeCount++;
    else if (v.status === 'maintenance') maintenanceCount++;
    else inactiveCount++;
  });

  // Escalabilidade visual de exemplo baseada no total de registros
  const scale = total > 0 && vehicles.value.length > 0 ? total / vehicles.value.length : 1;
  
  return {
    total,
    active: total > 0 ? Math.round(activeCount * scale) : 0,
    maintenance: total > 0 ? Math.round(maintenanceCount * scale) : 0,
    inactive: total > 0 ? Math.round(inactiveCount * scale) : 0
  };
});

// Submissão do formulário
const handleSubmit = async () => {
  submitLoading.value = true;
  error.value = '';
  try {
    // Normalizar placa para letras maiúsculas
    form.value.plate = form.value.plate.toUpperCase().trim();
    if (form.value.vin) form.value.vin = form.value.vin.toUpperCase().trim();
    if (form.value.renavam) form.value.renavam = form.value.renavam.trim();
    
    // Tratamento de capacidade para número
    const payload = { ...form.value };
    if (!payload.capacity_tons) {
      payload.capacity_tons = null;
    }

    if (isEditing.value) {
      await api.put(`/vehicles/${editingId.value}`, payload);
    } else {
      await api.post('/vehicles', payload);
    }
    resetForm();
    showForm.value = false;
    fetchVehicles();
  } catch (err) {
    error.value = err.response?.data?.message || 'Erro ao salvar veículo. Verifique se a placa ou chassi já não estão cadastrados.';
  } finally {
    submitLoading.value = false;
  }
};

// Editar veículo
const handleEdit = (vehicle) => {
  form.value = { ...vehicle };
  // Formata data do licenciamento para YYYY-MM-DD
  if (vehicle.license_expiration) {
    form.value.license_expiration = vehicle.license_expiration.split('T')[0];
  }
  isEditing.value = true;
  editingId.value = vehicle.id;
  showForm.value = true;
};

// Excluir veículo
const handleDelete = async (id) => {
  if (confirm('Tem certeza que deseja excluir este veículo da frota?')) {
    try {
      await api.delete(`/vehicles/${id}`);
      fetchVehicles();
    } catch (err) {
      alert('Erro ao excluir veículo. Verifique se ele não possui ordens de serviço vinculadas.');
    }
  }
};

// Monitoramento de filtros
watch([search, filterStatus, filterType], () => {
  fetchVehicles(1);
});

// Inicialização
onMounted(() => {
  fetchVehicles(1);
});

// Helper para label de tipo
const getTypeLabel = (type) => {
  const types = {
    truck: 'Caminhão / Cavalo Mecânico',
    van: 'Van / Utilitário',
    car: 'Carro de Passeio',
    motorcycle: 'Motocicleta',
    trailer: 'Carreta / Reboque'
  };
  return types[type] || type;
};

// Helper para ícones de tipo
const getTypeIcon = (type) => {
  const icons = {
    truck: 'bi-truck',
    van: 'bi-truck-flatbed',
    car: 'bi-car-front-fill',
    motorcycle: 'bi-bicycle',
    trailer: 'bi-box-seam'
  };
  return icons[type] || 'bi-truck';
};

// Helper para formatar data
const formatDate = (dateString) => {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('pt-BR', { timeZone: 'UTC' });
};
</script>

<template>
  <div class="d-flex">
    <!-- Sidebar de Navegação Modular -->
    <Sidebar />

    <!-- Área de Conteúdo Principal -->
    <div class="flex-grow-1 min-vh-100 p-4" style="margin-left: 260px;">
      
      <!-- Cabeçalho Superior / Topbar -->
      <header class="d-flex justify-content-between align-items-center pb-4 mb-4 border-bottom">
        <div>
          <span class="text-muted small fw-bold text-uppercase tracking-wider">Módulos</span>
          <h1 class="h3 fw-bold text-slate-900 mb-0 font-headline">Gestão de Veículos</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-slate-900 text-white px-3 py-2 fw-semibold">
            Oficina: {{ authStore.tenantName || 'Carregando...' }}
          </span>
        </div>
      </header>

      <!-- Alerta de Erro -->
      <div v-if="error" class="alert alert-danger alert-dismissible fade show rounded-lg shadow-sm" role="alert">
        {{ error }}
      </div>

      <!-- Grid de Estatísticas (Stitch reference) -->
      <div class="row mb-4">
        <!-- Total -->
        <div class="col-md-3 mb-3">
          <div class="card border-slate-200 shadow-xs rounded-xl overflow-hidden position-relative group cursor-pointer">
            <div class="vertical-bar bg-slate-900"></div>
            <div class="card-body p-4 ps-5">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="text-muted small fw-semibold mb-1">Total de Veículos</p>
                  <h3 class="fw-bold text-slate-900 font-headline mb-0">{{ stats.total }}</h3>
                </div>
                <div class="icon-circle bg-slate-100 text-slate-700">
                  <i class="bi bi-truck fs-4"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Ativos -->
        <div class="col-md-3 mb-3">
          <div class="card border-slate-200 shadow-xs rounded-xl overflow-hidden position-relative group cursor-pointer">
            <div class="vertical-bar bg-success"></div>
            <div class="card-body p-4 ps-5">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="text-muted small fw-semibold mb-1">Disponíveis / Ativos</p>
                  <h3 class="fw-bold text-success font-headline mb-0">{{ stats.active }}</h3>
                </div>
                <div class="icon-circle bg-success-soft text-success">
                  <i class="bi bi-check-circle fs-4"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Em Manutenção -->
        <div class="col-md-3 mb-3">
          <div class="card border-slate-200 shadow-xs rounded-xl overflow-hidden position-relative group cursor-pointer">
            <div class="vertical-bar bg-warning"></div>
            <div class="card-body p-4 ps-5">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="text-muted small fw-semibold mb-1">Em Manutenção</p>
                  <h3 class="fw-bold text-warning font-headline mb-0">{{ stats.maintenance }}</h3>
                </div>
                <div class="icon-circle bg-warning-soft text-warning">
                  <i class="bi bi-wrench-adjustable fs-4"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Inativos -->
        <div class="col-md-3 mb-3">
          <div class="card border-slate-200 shadow-xs rounded-xl overflow-hidden position-relative group cursor-pointer">
            <div class="vertical-bar bg-secondary"></div>
            <div class="card-body p-4 ps-5">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="text-muted small fw-semibold mb-1">Fora de Operação</p>
                  <h3 class="fw-bold text-secondary font-headline mb-0">{{ stats.inactive }}</h3>
                </div>
                <div class="icon-circle bg-secondary-soft text-secondary">
                  <i class="bi bi-slash-circle fs-4"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Barra de Ações, Filtros e Busca -->
      <div class="card border-0 shadow-xs mb-4 rounded-xl">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1" style="max-width: 800px;">
            <!-- Busca -->
            <div class="input-group flex-grow-1" style="min-width: 250px; max-width: 350px;">
              <span class="input-group-text bg-white border-end-0 text-muted">
                <i class="bi bi-search"></i>
              </span>
              <input
                v-model="search"
                type="text"
                class="form-control border-start-0 ps-0 rounded-end-lg"
                placeholder="Placa, modelo, marca ou chassi..."
              />
            </div>
            
            <!-- Filtro de Tipo -->
            <select v-model="filterType" class="form-select rounded-lg" style="width: auto; min-width: 160px;">
              <option value="">Todos os tipos</option>
              <option value="truck">Caminhões</option>
              <option value="van">Vans / Utilitários</option>
              <option value="car">Carros</option>
              <option value="motorcycle">Motos</option>
              <option value="trailer">Carretas / Reboques</option>
            </select>

            <!-- Filtro de Status -->
            <select v-model="filterStatus" class="form-select rounded-lg" style="width: auto; min-width: 160px;">
              <option value="">Todos os status</option>
              <option value="active">Ativo / Disponível</option>
              <option value="inactive">Inativo</option>
              <option value="maintenance">Em Manutenção</option>
            </select>
          </div>

          <button
            @click="showForm = !showForm; if(!showForm) resetForm();"
            class="btn btn-primary fw-semibold rounded-lg d-flex align-items-center gap-2"
          >
            <i class="bi" :class="showForm ? 'bi-x-lg' : 'bi-plus-lg'"></i>
            {{ showForm ? 'Fechar Formulário' : 'Cadastrar Veículo' }}
          </button>
        </div>
      </div>

      <!-- Formulário de Cadastro / Edição -->
      <div v-if="showForm" class="card border-0 shadow-xs mb-4 rounded-xl border-top-slate">
        <div class="card-header bg-white border-0 pt-4 px-4">
          <h5 class="fw-bold text-slate-900 mb-0 font-headline">
            {{ isEditing ? 'Editar Registro de Veículo' : 'Cadastrar Novo Veículo na Frota' }}
          </h5>
        </div>
        <div class="card-body px-4 pb-4">
          <form @submit.prevent="handleSubmit">
            <div class="row">
              <!-- Placa -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Placa (Ex: ABC1D23)</label>
                <input v-model="form.plate" type="text" class="form-control rounded-lg uppercase" required placeholder="Placa" />
              </div>
              <!-- Marca -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Marca / Fabricante</label>
                <input v-model="form.brand" type="text" class="form-control rounded-lg" required placeholder="Ex: Mercedes-Benz, Scania" />
              </div>
              <!-- Modelo -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Modelo do Veículo</label>
                <input v-model="form.model" type="text" class="form-control rounded-lg" required placeholder="Ex: Actros 2651, R540" />
              </div>
              <!-- Ano -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Ano Fabricação</label>
                <input v-model="form.year" type="number" class="form-control rounded-lg" required placeholder="Ex: 2023" />
              </div>

              <!-- Tipo -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Tipo de Veículo</label>
                <select v-model="form.type" class="form-select rounded-lg">
                  <option value="truck">Caminhão / Cavalo Mecânico</option>
                  <option value="van">Van / Utilitário</option>
                  <option value="car">Carro de Passeio</option>
                  <option value="motorcycle">Motocicleta</option>
                  <option value="trailer">Carreta / Reboque</option>
                </select>
              </div>
              <!-- Chassi -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Chassi / VIN</label>
                <input v-model="form.vin" type="text" class="form-control rounded-lg uppercase" placeholder="Número do Chassi" />
              </div>
              <!-- Cor -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Cor</label>
                <input v-model="form.color" type="text" class="form-control rounded-lg" placeholder="Ex: Vermelho, Branco" />
              </div>
              <!-- Capacidade -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Capacidade (Toneladas)</label>
                <input v-model="form.capacity_tons" type="number" step="0.01" class="form-control rounded-lg" placeholder="Ex: 45.50" />
              </div>

              <!-- Renavam -->
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Código RENAVAM</label>
                <input v-model="form.renavam" type="text" class="form-control rounded-lg" placeholder="Ex: 12345678901" />
              </div>
              <!-- Vencimento do Licenciamento -->
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Vencimento do Licenciamento</label>
                <input v-model="form.license_expiration" type="date" class="form-control rounded-lg" />
              </div>
              <!-- Status -->
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Status Operacional</label>
                <select v-model="form.status" class="form-select rounded-lg">
                  <option value="active">Ativo / Disponível</option>
                  <option value="inactive">Inativo / Fora de Operação</option>
                  <option value="maintenance">Em Manutenção</option>
                </select>
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
              <button type="button" @click="showForm = false; resetForm();" class="btn btn-light fw-semibold rounded-lg">
                Cancelar
              </button>
              <button type="submit" class="btn btn-primary fw-semibold rounded-lg" :disabled="submitLoading">
                <span v-if="submitLoading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                {{ isEditing ? 'Atualizar Veículo' : 'Salvar Veículo' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Tabela de Veículos -->
      <div class="card border-0 shadow-xs rounded-xl overflow-hidden">
        <div class="card-body p-0">
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2 small">Carregando frota de veículos...</p>
          </div>

          <div v-else-if="vehicles.length === 0" class="text-center py-5">
            <p class="text-muted mb-0">Nenhum veículo cadastrado na frota ou correspondente aos filtros.</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="px-4 text-slate-700 fw-bold border-bottom-0">Veículo / Tipo</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Placa</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Chassi (VIN)</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Ficha Técnica</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Licenciamento</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Status</th>
                  <th class="text-end px-4 text-slate-700 fw-bold border-bottom-0">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="vehicle in vehicles" :key="vehicle.id" class="table-row-compact">
                  <td class="px-4">
                    <div class="d-flex align-items-center gap-2">
                      <div class="icon-badge bg-slate-100 text-slate-700 px-2 py-1.5 rounded">
                        <i class="bi" :class="getTypeIcon(vehicle.type)"></i>
                      </div>
                      <div>
                        <div class="fw-semibold text-slate-900">{{ vehicle.brand }} {{ vehicle.model }}</div>
                        <small class="text-muted text-uppercase" style="font-size: 0.65rem;">
                          {{ getTypeLabel(vehicle.type) }} • {{ vehicle.year }}
                        </small>
                      </div>
                    </div>
                  </td>
                  <td class="font-monospace fw-bold text-slate-900">{{ vehicle.plate }}</td>
                  <td class="font-monospace small text-slate-700">{{ vehicle.vin || 'N/A' }}</td>
                  <td class="small text-slate-700">
                    <span v-if="vehicle.color">{{ vehicle.color }}</span>
                    <span v-if="vehicle.color && vehicle.capacity_tons"> • </span>
                    <span v-if="vehicle.capacity_tons">{{ vehicle.capacity_tons }}T</span>
                    <span v-if="!vehicle.color && !vehicle.capacity_tons">-</span>
                  </td>
                  <td class="small font-monospace" :class="new Date(vehicle.license_expiration) < new Date() ? 'text-danger fw-semibold' : 'text-slate-700'">
                    {{ formatDate(vehicle.license_expiration) }}
                  </td>
                  <td>
                    <span
                      class="badge"
                      :class="[
                        vehicle.status === 'active' ? 'bg-success-soft text-success' : '',
                        vehicle.status === 'inactive' ? 'bg-secondary-soft text-secondary' : '',
                        vehicle.status === 'maintenance' ? 'bg-warning-soft text-warning' : '',
                      ]"
                      style="font-size: 0.65rem;"
                    >
                      {{ vehicle.status === 'active' ? 'Ativo' : (vehicle.status === 'maintenance' ? 'Manutenção' : 'Inativo') }}
                    </span>
                  </td>
                  <td class="text-end px-4">
                    <button @click="handleEdit(vehicle)" class="btn btn-outline-primary btn-sm me-2 fw-semibold rounded-lg py-1 px-2.5" style="font-size: 0.75rem;">
                      Editar
                    </button>
                    <button @click="handleDelete(vehicle.id)" class="btn btn-outline-danger btn-sm fw-semibold rounded-lg py-1 px-2.5" style="font-size: 0.75rem;">
                      Excluir
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Rodapé de Paginação -->
        <div v-if="pagination.last_page > 1" class="card-footer bg-white border-0 d-flex justify-content-between align-items-center py-3 px-4 border-top">
          <span class="text-muted small">Total de {{ pagination.total }} veículos cadastrados</span>
          <div class="d-flex gap-1">
            <button
              @click="fetchVehicles(pagination.current_page - 1)"
              class="btn btn-outline-secondary btn-sm rounded-lg"
              :disabled="pagination.current_page === 1"
            >
              Anterior
            </button>
            <button
              @click="fetchVehicles(pagination.current_page + 1)"
              class="btn btn-outline-secondary btn-sm rounded-lg"
              :disabled="pagination.current_page === pagination.last_page"
            >
              Próximo
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.text-slate-900 {
  color: #0f172a;
}
.text-slate-700 {
  color: #334155;
}
.bg-slate-900 {
  background-color: #0f172a;
}
.border-top-slate {
  border-top: 3px solid #0f172a;
}
.rounded-xl {
  border-radius: 12px;
}
.rounded-lg {
  border-radius: 8px;
}
.bg-primary-soft {
  background-color: rgba(13, 110, 253, 0.1);
}
.bg-secondary-soft {
  background-color: rgba(108, 117, 125, 0.1);
}
.bg-success-soft {
  background-color: rgba(40, 167, 69, 0.1);
}
.bg-warning-soft {
  background-color: rgba(255, 193, 7, 0.1);
}
.bg-danger-soft {
  background-color: rgba(220, 53, 69, 0.1);
}
.bg-slate-100 {
  background-color: #f1f5f9;
}
.icon-circle {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.icon-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.vertical-bar {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
}
.table th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.7rem;
  letter-spacing: 0.5px;
  border-bottom: none;
}
.table-row-compact {
  height: 48px; /* Altura compacta do design system */
}
.form-control:focus, .form-select:focus {
  border-color: #0f172a;
  box-shadow: 0 0 0 0.2rem rgba(15, 23, 42, 0.15);
}
.uppercase {
  text-transform: uppercase;
}
</style>
