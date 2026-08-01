<script setup>
import { ref, onMounted, watch, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import Sidebar from '../components/Sidebar.vue';

const router = useRouter();
const authStore = useAuthStore();

// Estados da lista
const services = ref([]);
const categories = ref([]);
const pagination = ref({});
const search = ref('');
const filterCategory = ref('');
const loading = ref(false);
const error = ref('');

// Estados do formulário
const showForm = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitLoading = ref(false);

const form = ref({
  category_id: '',
  name: '',
  code: '',
  description: '',
  base_price: 0,
  estimated_hours: '',
  is_active: true
});

// Reset do formulário
const resetForm = () => {
  form.value = {
    category_id: categories.value[0]?.id || '',
    name: '',
    code: '',
    description: '',
    base_price: 0,
    estimated_hours: '',
    is_active: true
  };
  isEditing.value = false;
  editingId.value = null;
};

// Carregar categorias de serviços da API
const fetchCategories = async () => {
  try {
    const response = await api.get('/service-categories');
    // Suporta array direto ou objeto paginado
    categories.value = Array.isArray(response.data) ? response.data : (response.data.data || []);
    if (categories.value.length > 0 && !form.value.category_id) {
      form.value.category_id = categories.value[0].id;
    }
  } catch (err) {
    console.error('Falha ao carregar categorias de serviço.');
  }
};

// Carregar serviços da API
const fetchServices = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/services', {
      params: {
        search: search.value,
        category_id: filterCategory.value,
        is_active: 1, // listamos apenas ativos por padrão ou sem filtro
        page
      }
    });
    services.value = response.data.data || [];
    pagination.value = {
      current_page: response.data.current_page || 1,
      last_page: response.data.last_page || 1,
      prev_page_url: response.data.prev_page_url,
      next_page_url: response.data.next_page_url,
      total: response.data.total || 0
    };
  } catch (err) {
    error.value = 'Falha ao buscar serviços do servidor.';
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
    if (!payload.estimated_hours) {
      payload.estimated_hours = null;
    }

    if (isEditing.value) {
      await api.put(`/services/${editingId.value}`, payload);
    } else {
      await api.post('/services', payload);
    }
    resetForm();
    showForm.value = false;
    fetchServices();
  } catch (err) {
    error.value = err.response?.data?.message || err.response?.data?.error || 'Erro ao salvar serviço. Verifique se o código/slug é único.';
  } finally {
    submitLoading.value = false;
  }
};

// Editar serviço
const handleEdit = (service) => {
  form.value = {
    category_id: service.category_id,
    name: service.name,
    code: service.code,
    description: service.description || '',
    base_price: service.base_price,
    estimated_hours: service.estimated_hours || '',
    is_active: service.is_active
  };
  isEditing.value = true;
  editingId.value = service.id;
  showForm.value = true;
};

// Excluir serviço
const handleDelete = async (id) => {
  if (confirm('Tem certeza que deseja desativar/excluir este serviço?')) {
    try {
      await api.delete(`/services/${id}`);
      fetchServices();
    } catch (err) {
      alert('Erro ao excluir serviço.');
    }
  }
};

// Monitoramento de filtros
watch([search, filterCategory], () => {
  fetchServices(1);
});

// Inicialização
onMounted(async () => {
  await fetchCategories();
  await fetchServices(1);
});

// Helper para formatar moeda
const formatCurrency = (val) => {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);
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
          <h1 class="h3 fw-bold text-slate-900 mb-0 font-headline">Serviços da Oficina</h1>
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

      <!-- Barra de Ações e Filtros -->
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
                placeholder="Pesquisar serviço por nome ou código..."
              />
            </div>
            
            <!-- Filtro de Categoria -->
            <select v-model="filterCategory" class="form-select rounded-lg" style="width: auto; min-width: 200px;">
              <option value="">Todas as categorias</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.name }}
              </option>
            </select>
          </div>

          <button
            @click="showForm = !showForm; if(!showForm) resetForm();"
            class="btn btn-primary fw-semibold rounded-lg d-flex align-items-center gap-2"
          >
            <i class="bi" :class="showForm ? 'bi-x-lg' : 'bi-plus-lg'"></i>
            {{ showForm ? 'Fechar Formulário' : 'Novo Serviço' }}
          </button>
        </div>
      </div>

      <!-- Formulário de Cadastro / Edição -->
      <div v-if="showForm" class="card border-0 shadow-xs mb-4 rounded-xl border-top-slate">
        <div class="card-header bg-white border-0 pt-4 px-4">
          <h5 class="fw-bold text-slate-900 mb-0 font-headline">
            {{ isEditing ? 'Editar Serviço Existente' : 'Cadastrar Novo Serviço de Oficina' }}
          </h5>
        </div>
        <div class="card-body px-4 pb-4">
          <!-- Alerta se não houver categorias -->
          <div v-if="categories.length === 0" class="alert alert-warning rounded-lg mb-3">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Nenhuma categoria de serviço cadastrada no banco. Cadastre uma categoria antes de prosseguir.
          </div>

          <form @submit.prevent="handleSubmit">
            <div class="row">
              <!-- Nome -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Nome do Serviço</label>
                <input v-model="form.name" type="text" class="form-control rounded-lg" required placeholder="Ex: Alinhamento de Chassi, Retífica de Cabeçote" />
              </div>
              <!-- Código -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Código Único (Ref/SKU)</label>
                <input v-model="form.code" type="text" class="form-control rounded-lg" required placeholder="Ex: SERV-001" />
              </div>
              <!-- Categoria -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Categoria</label>
                <select v-model="form.category_id" class="form-select rounded-lg" required>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                  </option>
                </select>
              </div>

              <!-- Preço Base -->
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Preço de Mão de Obra Padrão (R$)</label>
                <input v-model="form.base_price" type="number" step="0.01" class="form-control rounded-lg" required />
              </div>
              <!-- Horas Estimadas -->
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Tempo Estimado (Horas)</label>
                <input v-model="form.estimated_hours" type="number" step="0.1" class="form-control rounded-lg" placeholder="Ex: 2.5" />
              </div>
              <!-- Status Ativo -->
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Disponibilidade</label>
                <select v-model="form.is_active" class="form-select rounded-lg">
                  <option :value="true">Ativo / Disponível</option>
                  <option :value="false">Inativo / Suspenso</option>
                </select>
              </div>

              <!-- Descrição -->
              <div class="col-12 mb-4">
                <label class="form-label small fw-semibold text-slate-700">Descrição Detalhada do Serviço</label>
                <textarea v-model="form.description" class="form-control rounded-lg" rows="3" placeholder="Instruções técnicas de execução do serviço..."></textarea>
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
              <button type="button" @click="showForm = false; resetForm();" class="btn btn-light fw-semibold rounded-lg">
                Cancelar
              </button>
              <button type="submit" class="btn btn-primary fw-semibold rounded-lg" :disabled="submitLoading || categories.length === 0">
                <span v-if="submitLoading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                {{ isEditing ? 'Atualizar Serviço' : 'Salvar Serviço' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Tabela de Serviços -->
      <div class="card border-0 shadow-xs rounded-xl overflow-hidden">
        <div class="card-body p-0">
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2 small">Carregando catálogo de serviços...</p>
          </div>

          <div v-else-if="services.length === 0" class="text-center py-5">
            <p class="text-muted mb-0">Nenhum serviço cadastrado na oficina ou correspondente aos filtros.</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="px-4 text-slate-700 fw-bold border-bottom-0">Serviço / Código</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Categoria</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Descrição Técnica</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Horas Estimadas</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Preço de Mão de Obra</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Status</th>
                  <th class="text-end px-4 text-slate-700 fw-bold border-bottom-0">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="serv in services" :key="serv.id" class="table-row-compact">
                  <td class="px-4">
                    <div class="fw-semibold text-slate-900">{{ serv.name }}</div>
                    <small class="text-muted text-uppercase font-monospace" style="font-size: 0.65rem;">
                      {{ serv.code }}
                    </small>
                  </td>
                  <td>
                    <span class="badge bg-secondary-soft text-secondary font-monospace" style="font-size: 0.65rem;">
                      {{ serv.category?.name || 'Geral' }}
                    </span>
                  </td>
                  <td class="small text-slate-700 text-truncate" style="max-width: 250px;" :title="serv.description">
                    {{ serv.description || 'Sem descrição' }}
                  </td>
                  <td class="font-monospace small text-slate-700">
                    {{ serv.estimated_hours ? `${serv.estimated_hours}h` : 'N/A' }}
                  </td>
                  <td class="fw-bold text-slate-900 font-monospace">
                    {{ formatCurrency(serv.base_price) }}
                  </td>
                  <td>
                    <span
                      class="badge"
                      :class="serv.is_active ? 'bg-success-soft text-success' : 'bg-secondary-soft text-secondary'"
                      style="font-size: 0.65rem;"
                    >
                      {{ serv.is_active ? 'Disponível' : 'Inativo' }}
                    </span>
                  </td>
                  <td class="text-end px-4">
                    <button @click="handleEdit(serv)" class="btn btn-outline-primary btn-sm me-2 fw-semibold rounded-lg py-1 px-2.5" style="font-size: 0.75rem;">
                      Editar
                    </button>
                    <button @click="handleDelete(serv.id)" class="btn btn-outline-danger btn-sm fw-semibold rounded-lg py-1 px-2.5" style="font-size: 0.75rem;">
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
          <span class="text-muted small">Total de {{ pagination.total }} serviços catalogados</span>
          <div class="d-flex gap-1">
            <button
              @click="fetchServices(pagination.current_page - 1)"
              class="btn btn-outline-secondary btn-sm rounded-lg"
              :disabled="pagination.current_page === 1"
            >
              Anterior
            </button>
            <button
              @click="fetchServices(pagination.current_page + 1)"
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
.table th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.7rem;
  letter-spacing: 0.5px;
  border-bottom: none;
}
.table-row-compact {
  height: 48px;
}
.form-control:focus, .form-select:focus {
  border-color: #0f172a;
  box-shadow: 0 0 0 0.2rem rgba(15, 23, 42, 0.15);
}
</style>
