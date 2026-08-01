<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import Sidebar from '../components/Sidebar.vue';

const router = useRouter();
const authStore = useAuthStore();

// Estados da lista
const customers = ref([]);
const pagination = ref({});
const search = ref('');
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
  cpf_cnpj: '',
  type: 'individual',
  street: '',
  number: '',
  complement: '',
  city: '',
  state: '',
  zip_code: '',
  status: 'active',
  notes: ''
});

// Reset do formulário
const resetForm = () => {
  form.value = {
    name: '',
    email: '',
    phone: '',
    cpf_cnpj: '',
    type: 'individual',
    street: '',
    number: '',
    complement: '',
    city: '',
    state: '',
    zip_code: '',
    status: 'active',
    notes: ''
  };
  isEditing.value = false;
  editingId.value = null;
};

// Carregar clientes da API
const fetchCustomers = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/customers', {
      params: { search: search.value, page }
    });
    customers.value = response.data.data;
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      prev_page_url: response.data.prev_page_url,
      next_page_url: response.data.next_page_url,
      total: response.data.total
    };
  } catch (err) {
    error.value = 'Falha ao buscar clientes do servidor.';
  } finally {
    loading.value = false;
  }
};

// Submissão do formulário (Criar ou Editar)
const handleSubmit = async () => {
  submitLoading.value = true;
  error.value = '';
  try {
    if (isEditing.value) {
      await api.put(`/customers/${editingId.value}`, form.value);
    } else {
      await api.post('/customers', form.value);
    }
    resetForm();
    showForm.value = false;
    fetchCustomers();
  } catch (err) {
    error.value = err.response?.data?.message || 'Erro ao salvar cliente. Verifique os dados.';
  } finally {
    submitLoading.value = false;
  }
};

// Carregar dados no formulário para edição
const handleEdit = (customer) => {
  form.value = { ...customer };
  isEditing.value = true;
  editingId.value = customer.id;
  showForm.value = true;
};

// Excluir cliente
const handleDelete = async (id) => {
  if (confirm('Tem certeza que deseja excluir este cliente?')) {
    try {
      await api.delete(`/customers/${id}`);
      fetchCustomers();
    } catch (err) {
      alert('Erro ao excluir cliente. Verifique se ele possui ordens de serviço ativas.');
    }
  }
};

// Monitora campo de busca com intervalo dinâmico
watch(search, () => {
  fetchCustomers(1);
});

// Inicialização
onMounted(() => {
  fetchCustomers(1);
});
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
          <h1 class="h3 fw-bold text-slate-900 mb-0 font-headline">Gestão de Clientes</h1>
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

      <!-- Barra de Ações e Busca -->
      <div class="card border-0 shadow-xs mb-4 rounded-xl">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 400px;">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0 text-muted">
                <i class="bi bi-search"></i>
              </span>
              <input
                v-model="search"
                type="text"
                class="form-control border-start-0 ps-0 rounded-end-lg"
                placeholder="Pesquisar por nome, e-mail ou documento..."
              />
            </div>
          </div>
          <button
            @click="showForm = !showForm; if(!showForm) resetForm();"
            class="btn btn-primary fw-semibold rounded-lg d-flex align-items-center gap-2"
          >
            <i class="bi" :class="showForm ? 'bi-x-lg' : 'bi-plus-lg'"></i>
            {{ showForm ? 'Fechar Formulário' : 'Novo Cliente' }}
          </button>
        </div>
      </div>

      <!-- Formulário de Cadastro / Edição -->
      <div v-if="showForm" class="card border-0 shadow-xs mb-4 rounded-xl border-top-slate">
        <div class="card-header bg-white border-0 pt-4 px-4">
          <h5 class="fw-bold text-slate-900 mb-0 font-headline">
            {{ isEditing ? 'Editar Registro de Cliente' : 'Cadastrar Novo Cliente' }}
          </h5>
        </div>
        <div class="card-body px-4 pb-4">
          <form @submit.prevent="handleSubmit">
            <div class="row">
              <!-- Nome -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Nome Completo / Razão Social</label>
                <input v-model="form.name" type="text" class="form-control rounded-lg" required />
              </div>
              <!-- Tipo -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Tipo de Cliente</label>
                <select v-model="form.type" class="form-select rounded-lg">
                  <option value="individual">Pessoa Física (CPF)</option>
                  <option value="company">Pessoa Jurídica (CNPJ)</option>
                </select>
              </div>
              <!-- CPF / CNPJ -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">CPF / CNPJ</label>
                <input v-model="form.cpf_cnpj" type="text" class="form-control rounded-lg" />
              </div>

              <!-- E-mail -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Endereço de E-mail</label>
                <input v-model="form.email" type="email" class="form-control rounded-lg" />
              </div>
              <!-- Telefone -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Telefone / WhatsApp</label>
                <input v-model="form.phone" type="text" class="form-control rounded-lg" />
              </div>
              <!-- Status -->
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Status</label>
                <select v-model="form.status" class="form-select rounded-lg">
                  <option value="active">Ativo</option>
                  <option value="inactive">Inativo</option>
                  <option value="suspended">Suspenso</option>
                </select>
              </div>

              <!-- Endereço -->
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Rua / Logradouro</label>
                <input v-model="form.street" type="text" class="form-control rounded-lg" />
              </div>
              <div class="col-md-2 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Número</label>
                <input v-model="form.number" type="text" class="form-control rounded-lg" />
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Complemento</label>
                <input v-model="form.complement" type="text" class="form-control rounded-lg" />
              </div>
              <div class="col-md-3 mb-3">
                <label class="form-label small fw-semibold text-slate-700">CEP</label>
                <input v-model="form.zip_code" type="text" class="form-control rounded-lg" />
              </div>

              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Cidade</label>
                <input v-model="form.city" type="text" class="form-control rounded-lg" />
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Estado (UF)</label>
                <input v-model="form.state" type="text" class="form-control rounded-lg" maxlength="2" placeholder="Ex: SP" />
              </div>

              <div class="col-12 mb-4">
                <label class="form-label small fw-semibold text-slate-700">Observações Internas</label>
                <textarea v-model="form.notes" class="form-control rounded-lg" rows="3"></textarea>
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
              <button type="button" @click="showForm = false; resetForm();" class="btn btn-light fw-semibold rounded-lg">
                Cancelar
              </button>
              <button type="submit" class="btn btn-primary fw-semibold rounded-lg" :disabled="submitLoading">
                <span v-if="submitLoading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                {{ isEditing ? 'Atualizar Cliente' : 'Salvar Cliente' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Tabela de Clientes -->
      <div class="card border-0 shadow-xs rounded-xl overflow-hidden">
        <div class="card-body p-0">
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2 small">Carregando lista de clientes...</p>
          </div>

          <div v-else-if="customers.length === 0" class="text-center py-5">
            <p class="text-muted mb-0">Nenhum cliente cadastrado ou encontrado.</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="px-4 text-slate-700 fw-bold border-bottom-0">Nome / Localização</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">CPF / CNPJ</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">E-mail</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Telefone</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Tipo</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Status</th>
                  <th class="text-end px-4 text-slate-700 fw-bold border-bottom-0">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="customer in customers" :key="customer.id" class="table-row-compact">
                  <td class="px-4">
                    <div class="fw-semibold text-slate-900">{{ customer.name }}</div>
                    <small class="text-muted text-uppercase font-monospace" style="font-size: 0.65rem;" v-if="customer.city">
                      {{ customer.city }} - {{ customer.state }}
                    </small>
                  </td>
                  <td class="font-monospace small text-slate-700">{{ customer.cpf_cnpj || 'Não informado' }}</td>
                  <td class="small text-slate-700">{{ customer.email || 'N/A' }}</td>
                  <td class="small text-slate-700">{{ customer.phone || 'N/A' }}</td>
                  <td>
                    <span class="badge bg-secondary-soft text-secondary text-capitalize font-monospace" style="font-size: 0.65rem;">
                      {{ customer.type === 'company' ? 'Jurídica' : 'Física' }}
                    </span>
                  </td>
                  <td>
                    <span
                      class="badge"
                      :class="[
                        customer.status === 'active' ? 'bg-success-soft text-success' : '',
                        customer.status === 'inactive' ? 'bg-secondary-soft text-secondary' : '',
                        customer.status === 'suspended' ? 'bg-danger-soft text-danger' : '',
                      ]"
                      style="font-size: 0.65rem;"
                    >
                      {{ customer.status === 'active' ? 'Ativo' : (customer.status === 'suspended' ? 'Suspenso' : 'Inativo') }}
                    </span>
                  </td>
                  <td class="text-end px-4">
                    <button @click="handleEdit(customer)" class="btn btn-outline-primary btn-sm me-2 fw-semibold rounded-lg py-1 px-2.5" style="font-size: 0.75rem;">
                      Editar
                    </button>
                    <button @click="handleDelete(customer.id)" class="btn btn-outline-danger btn-sm fw-semibold rounded-lg py-1 px-2.5" style="font-size: 0.75rem;">
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
          <span class="text-muted small">Total de {{ pagination.total }} clientes cadastrados</span>
          <div class="d-flex gap-1">
            <button
              @click="fetchCustomers(pagination.current_page - 1)"
              class="btn btn-outline-secondary btn-sm rounded-lg"
              :disabled="pagination.current_page === 1"
            >
              Anterior
            </button>
            <button
              @click="fetchCustomers(pagination.current_page + 1)"
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
  height: 48px; /* Altura compacta do design system */
}
.form-control:focus, .form-select:focus {
  border-color: #0f172a;
  box-shadow: 0 0 0 0.2rem rgba(15, 23, 42, 0.15);
}
</style>

