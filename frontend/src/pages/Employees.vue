<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import Sidebar from '../components/Sidebar.vue';

const router = useRouter();
const authStore = useAuthStore();

// Estados da lista
const employees = ref([]);
const pagination = ref({});
const search = ref('');
const filterStatus = ref('');
const filterCnh = ref('');
const loading = ref(false);
const error = ref('');

// Estados do formulário
const showForm = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitLoading = ref(false);

const form = ref({
  name: '',
  email: '',
  phone: '',
  cpf: '',
  cnh: '',
  cnh_category: 'D',
  cnh_expiration: '',
  status: 'active',
  hired_at: new Date().toISOString().split('T')[0]
});

// Reset do formulário
const resetForm = () => {
  form.value = {
    name: '',
    email: '',
    phone: '',
    cpf: '',
    cnh: '',
    cnh_category: 'D',
    cnh_expiration: '',
    status: 'active',
    hired_at: new Date().toISOString().split('T')[0]
  };
  isEditing.value = false;
  editingId.value = null;
};

// Carregar funcionários/motoristas da API
const fetchEmployees = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/drivers', {
      params: {
        search: search.value,
        status: filterStatus.value,
        cnh_category: filterCnh.value,
        page
      }
    });
    employees.value = response.data.data || [];
    pagination.value = {
      current_page: response.data.current_page || 1,
      last_page: response.data.last_page || 1,
      prev_page_url: response.data.prev_page_url,
      next_page_url: response.data.next_page_url,
      total: response.data.total || 0
    };
  } catch (err) {
    error.value = 'Falha ao buscar colaboradores do servidor.';
  } finally {
    loading.value = false;
  }
};

// Submissão do formulário
const handleSubmit = async () => {
  submitLoading.value = true;
  error.value = '';
  try {
    const payload = { ...form.value };
    if (!payload.cnh_expiration) payload.cnh_expiration = null;
    if (!payload.hired_at) payload.hired_at = null;

    if (isEditing.value) {
      await api.put(`/drivers/${editingId.value}`, payload);
    } else {
      await api.post('/drivers', payload);
    }
    resetForm();
    showForm.value = false;
    fetchEmployees();
  } catch (err) {
    error.value = err.response?.data?.message || 'Erro ao salvar colaborador. Verifique se o CPF ou CNH já existem.';
  } finally {
    submitLoading.value = false;
  }
};

// Editar colaborador
const handleEdit = (emp) => {
  form.value = { ...emp };
  if (emp.cnh_expiration) {
    form.value.cnh_expiration = emp.cnh_expiration.split('T')[0];
  }
  if (emp.hired_at) {
    form.value.hired_at = emp.hired_at.split('T')[0];
  }
  isEditing.value = true;
  editingId.value = emp.id;
  showForm.value = true;
};

// Excluir colaborador
const handleDelete = async (id) => {
  if (confirm('Tem certeza que deseja remover este colaborador da equipe?')) {
    try {
      await api.delete(`/drivers/${id}`);
      fetchEmployees();
    } catch (err) {
      alert('Erro ao excluir colaborador.');
    }
  }
};

// Monitoramento de filtros
watch([search, filterStatus, filterCnh], () => {
  fetchEmployees(1);
});

// Inicialização
onMounted(() => {
  fetchEmployees(1);
});

// Helper para pegar iniciais
const getInitials = (name) => {
  if (!name) return 'FN';
  const parts = name.split(' ');
  if (parts.length > 1) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return name.slice(0, 2).toUpperCase();
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
          <h1 class="h3 fw-bold text-slate-900 mb-0 font-headline">Equipe de Funcionários</h1>
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
                placeholder="Buscar por nome, email, CPF ou CNH..."
              />
            </div>
            
            <!-- Categoria de Habilitação -->
            <select v-model="filterCnh" class="form-select rounded-lg" style="width: auto; min-width: 160px;">
              <option value="">Todas as CNH</option>
              <option value="A">Categoria A</option>
              <option value="B">Categoria B</option>
              <option value="C">Categoria C</option>
              <option value="D">Categoria D</option>
              <option value="E">Categoria E</option>
            </select>

            <!-- Filtro de Status -->
            <select v-model="filterStatus" class="form-select rounded-lg" style="width: auto; min-width: 160px;">
              <option value="">Todos os status</option>
              <option value="active">Ativo</option>
              <option value="inactive">Inativo</option>
              <option value="suspended">Suspenso</option>
            </select>
          </div>

          <button
            @click="showForm = !showForm; if(!showForm) resetForm();"
            class="btn btn-primary fw-semibold rounded-lg d-flex align-items-center gap-2"
          >
            <i class="bi" :class="showForm ? 'bi-x-lg' : 'bi-plus-lg'"></i>
            {{ showForm ? 'Fechar' : 'Adicionar Funcionário' }}
          </button>
        </div>
      </div>

      <!-- Formulário de Cadastro / Edição -->
      <div v-if="showForm" class="card border-0 shadow-xs mb-4 rounded-xl border-top-slate">
        <div class="card-header bg-white border-0 pt-4 px-4">
          <h5 class="fw-bold text-slate-900 mb-0 font-headline">
            {{ isEditing ? 'Editar Colaborador' : 'Adicionar Colaborador à Equipe' }}
          </h5>
        </div>
        <div class="card-body px-4 pb-4">
          <form @submit.prevent="handleSubmit">
            <div class="row">
              <!-- Nome -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Nome Completo</label>
                <input v-model="form.name" type="text" class="form-control rounded-lg" required placeholder="Nome do Colaborador" />
              </div>
              <!-- CPF -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">CPF</label>
                <input v-model="form.cpf" type="text" class="form-control rounded-lg" placeholder="Ex: 000.000.000-00" />
              </div>
              <!-- Data de Contratação -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Contratado em</label>
                <input v-model="form.hired_at" type="date" class="form-control rounded-lg" />
              </div>

              <!-- E-mail -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Endereço de E-mail</label>
                <input v-model="form.email" type="email" class="form-control rounded-lg" placeholder="email@dominio.com" />
              </div>
              <!-- Telefone -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Telefone / Celular</label>
                <input v-model="form.phone" type="text" class="form-control rounded-lg" placeholder="Ex: (11) 99999-9999" />
              </div>

              <!-- Número CNH -->
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Número da CNH</label>
                <input v-model="form.cnh" type="text" class="form-control rounded-lg" placeholder="Registro CNH" />
              </div>
              <!-- Categoria CNH -->
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Categoria Habilitação</label>
                <select v-model="form.cnh_category" class="form-select rounded-lg">
                  <option value="A">Categoria A (Motos)</option>
                  <option value="B">Categoria B (Carros)</option>
                  <option value="C">Categoria C (Caminhões leve)</option>
                  <option value="D">Categoria D (Caminhões/Ônibus)</option>
                  <option value="E">Categoria E (Articulados)</option>
                </select>
              </div>
              <!-- Vencimento CNH -->
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Vencimento da CNH</label>
                <input v-model="form.cnh_expiration" type="date" class="form-control rounded-lg" />
              </div>

              <!-- Status -->
              <div class="col-md-12 mb-4">
                <label class="form-label small fw-semibold text-slate-700">Status do Colaborador</label>
                <select v-model="form.status" class="form-select rounded-lg">
                  <option value="active">Ativo / Operacional</option>
                  <option value="inactive">Inativo / Desligado</option>
                  <option value="suspended">Suspenso temporariamente</option>
                </select>
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
              <button type="button" @click="showForm = false; resetForm();" class="btn btn-light fw-semibold rounded-lg">
                Cancelar
              </button>
              <button type="submit" class="btn btn-primary fw-semibold rounded-lg" :disabled="submitLoading">
                <span v-if="submitLoading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                {{ isEditing ? 'Atualizar Colaborador' : 'Adicionar Colaborador' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Grid de Cards de Funcionários (Stitch aesthetic) -->
      <div v-if="loading" class="text-center py-5">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="text-muted mt-2 small">Carregando lista de funcionários...</p>
      </div>

      <div v-else-if="employees.length === 0" class="text-center py-5 bg-white rounded-xl shadow-xs">
        <p class="text-muted mb-0">Nenhum funcionário cadastrado ou encontrado.</p>
      </div>

      <div v-else>
        <div class="row">
          <div v-for="emp in employees" :key="emp.id" class="col-md-4 col-lg-3 mb-4">
            <div class="card h-100 border-slate-200 shadow-xs hover-card rounded-xl overflow-hidden position-relative group">
              <!-- Accent bar based on status -->
              <div
                class="vertical-bar"
                :class="[
                  emp.status === 'active' ? 'bg-success' : '',
                  emp.status === 'inactive' ? 'bg-secondary' : '',
                  emp.status === 'suspended' ? 'bg-danger' : '',
                ]"
              ></div>
              
              <div class="card-body p-4 ps-5 flex-column d-flex justify-content-between h-100">
                
                <div class="d-flex justify-content-between align-items-start mb-3">
                  <!-- Avatar -->
                  <div class="avatar bg-slate-900 text-white fw-bold d-flex align-items-center justify-content-center rounded-circle">
                    {{ getInitials(emp.name) }}
                  </div>
                  
                  <!-- Badge Status -->
                  <span
                    class="badge"
                    :class="[
                      emp.status === 'active' ? 'bg-success-soft text-success' : '',
                      emp.status === 'inactive' ? 'bg-secondary-soft text-secondary' : '',
                      emp.status === 'suspended' ? 'bg-danger-soft text-danger' : '',
                    ]"
                    style="font-size: 0.65rem;"
                  >
                    {{ emp.status === 'active' ? 'Ativo' : (emp.status === 'suspended' ? 'Suspenso' : 'Inativo') }}
                  </span>
                </div>

                <div class="mb-3">
                  <h5 class="fw-bold text-slate-900 mb-1 font-headline">{{ emp.name }}</h5>
                  <p class="text-primary small fw-semibold font-monospace mb-2">
                    Categoria CNH: <span class="badge bg-slate-900 text-white rounded">{{ emp.cnh_category || 'N/A' }}</span>
                  </p>
                  
                  <div class="small text-muted font-monospace" style="font-size: 0.75rem;">
                    <div class="d-flex align-items-center gap-1.5 mb-1">
                      <i class="bi bi-envelope"></i>
                      <span class="text-truncate">{{ emp.email || 'Sem e-mail' }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-1.5 mb-1">
                      <i class="bi bi-telephone"></i>
                      <span>{{ emp.phone || 'Sem telefone' }}</span>
                    </div>
                    <div class="d-flex align-items-center gap-1.5">
                      <i class="bi bi-calendar-check"></i>
                      <span>Admissão: {{ formatDate(emp.hired_at) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Specialties / Tags based on CNH -->
                <div class="d-flex flex-wrap gap-1 mb-3">
                  <span v-if="emp.cnh" class="badge bg-light text-dark font-monospace" style="font-size: 0.6rem;">CNH: {{ emp.cnh }}</span>
                  <span v-if="emp.cnh_category === 'D' || emp.cnh_category === 'E'" class="badge bg-slate-100 text-slate-700" style="font-size: 0.6rem;">Veículos Pesados</span>
                  <span v-else class="badge bg-slate-100 text-slate-700" style="font-size: 0.6rem;">Frota Leve</span>
                </div>

                <!-- Action buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-auto justify-content-end">
                  <button @click="handleEdit(emp)" class="btn btn-light btn-sm rounded-lg d-flex align-items-center justify-content-center p-2" title="Editar">
                    <i class="bi bi-pencil-fill text-primary"></i>
                  </button>
                  <button @click="handleDelete(emp.id)" class="btn btn-light btn-sm rounded-lg d-flex align-items-center justify-content-center p-2" title="Excluir">
                    <i class="bi bi-trash-fill text-danger"></i>
                  </button>
                </div>

              </div>
            </div>
          </div>
        </div>

        <!-- Paginação -->
        <div v-if="pagination.last_page > 1" class="d-flex justify-content-between align-items-center mt-3 px-2">
          <span class="text-muted small">Total de {{ pagination.total }} colaboradores</span>
          <div class="d-flex gap-1">
            <button
              @click="fetchEmployees(pagination.current_page - 1)"
              class="btn btn-outline-secondary btn-sm rounded-lg"
              :disabled="pagination.current_page === 1"
            >
              Anterior
            </button>
            <button
              @click="fetchEmployees(pagination.current_page + 1)"
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
.bg-danger-soft {
  background-color: rgba(220, 53, 69, 0.1);
}
.bg-slate-100 {
  background-color: #f1f5f9;
}
.avatar {
  width: 44px;
  height: 44px;
  font-size: 1rem;
}
.vertical-bar {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 4px;
}
.hover-card {
  transition: transform 0.2s, box-shadow 0.2s;
}
.hover-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.05) !important;
}
.form-control:focus, .form-select:focus {
  border-color: #0f172a;
  box-shadow: 0 0 0 0.2rem rgba(15, 23, 42, 0.15);
}
</style>
