<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import Sidebar from '../components/Sidebar.vue';

const router = useRouter();
const authStore = useAuthStore();

// Estados da lista
const users = ref([]);
const tenantRolesList = ref([]); // Lista de papéis disponíveis no tenant
const pagination = ref({});
const search = ref('');
const loading = ref(false);
const error = ref('');
const successMessage = ref('');

// Estados do formulário
const showForm = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitLoading = ref(false);

const form = ref({
  name: '',
  email: '',
  password: '',
  role: 'user', // central role: admin ou user
  roles: [] // papéis locais no inquilino (ex: Mecânico, Eletricista)
});

// Reset do formulário
const resetForm = () => {
  form.value = {
    name: '',
    email: '',
    password: '',
    role: 'user',
    roles: []
  };
  isEditing.value = false;
  editingId.value = null;
};

// Estatísticas calculadas locais
const totalUsers = ref(0);
const adminCount = ref(0);
const userCount = ref(0);

const updateLocalStats = () => {
  totalUsers.value = users.value.length;
  adminCount.value = users.value.filter(u => u.role === 'admin' || u.role === 'super_admin').length;
  userCount.value = users.value.filter(u => u.role === 'user').length;
};

// Carregar papéis (roles) do tenant
const fetchTenantRoles = async () => {
  try {
    const response = await api.get('/rbac/roles');
    tenantRolesList.value = response.data || [];
  } catch (err) {
    console.error('Falha ao carregar papéis do inquilino.');
  }
};

// Carregar usuários associados à empresa/inquilino
const fetchUsers = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/users', {
      params: {
        search: search.value,
        page
      }
    });
    users.value = response.data.data || [];
    pagination.value = {
      current_page: response.data.current_page || 1,
      last_page: response.data.last_page || 1,
      prev_page_url: response.data.prev_page_url,
      next_page_url: response.data.next_page_url,
      total: response.data.total || 0
    };
    updateLocalStats();
  } catch (err) {
    error.value = 'Falha ao buscar usuários do servidor. Certifique-se de que sua conta é administradora.';
  } finally {
    loading.value = false;
  }
};

// Submissão do formulário
const handleSubmit = async () => {
  submitLoading.value = true;
  error.value = '';
  successMessage.value = '';
  try {
    const payload = { ...form.value };
    if (isEditing.value) {
      // Se estiver editando e a senha estiver vazia, não envia
      if (!payload.password) {
        delete payload.password;
      }
      await api.put(`/users/${editingId.value}`, payload);
      successMessage.value = 'Usuário atualizado com sucesso!';
    } else {
      await api.post('/users', payload);
      successMessage.value = 'Usuário cadastrado e associado com sucesso!';
    }
    resetForm();
    showForm.value = false;
    fetchUsers();
  } catch (err) {
    error.value = err.response?.data?.message || err.response?.data?.error || 'Erro ao processar requisição de usuário.';
  } finally {
    submitLoading.value = false;
  }
};

// Editar usuário
const handleEdit = (user) => {
  form.value = {
    name: user.name,
    email: user.email,
    password: '', // Deixa vazia para edição opcional
    role: user.role,
    roles: user.tenant_roles || []
  };
  isEditing.value = true;
  editingId.value = user.id;
  showForm.value = true;
};

// Desassociar usuário da empresa
const handleDelete = async (id) => {
  if (confirm('Tem certeza que deseja remover o acesso deste usuário à empresa? O cadastro central dele não será deletado, mas ele perderá o acesso a esta oficina.')) {
    try {
      await api.delete(`/users/${id}`);
      successMessage.value = 'Acesso do usuário revogado com sucesso.';
      fetchUsers();
    } catch (err) {
      alert('Erro ao desassociar usuário.');
    }
  }
};

// Monitoramento da busca
watch(search, () => {
  fetchUsers(1);
});

// Inicialização
onMounted(async () => {
  await fetchTenantRoles();
  await fetchUsers(1);
});

// Helper para iniciais
const getInitials = (name) => {
  if (!name) return 'US';
  const parts = name.trim().split(' ');
  if (parts.length > 1) {
    return (parts[0][0] + parts[1][0]).toUpperCase();
  }
  return name.slice(0, 2).toUpperCase();
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
          <span class="text-muted small fw-bold text-uppercase tracking-wider">Configurações SaaS</span>
          <h1 class="h3 fw-bold text-slate-900 mb-0 font-headline">Usuários e Permissões</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-slate-900 text-white px-3 py-2 fw-semibold">
            Oficina: {{ authStore.tenantName || 'Carregando...' }}
          </span>
        </div>
      </header>

      <!-- Alertas de Status -->
      <div v-if="error" class="alert alert-danger alert-dismissible fade show rounded-lg shadow-sm" role="alert">
        {{ error }}
      </div>
      <div v-if="successMessage" class="alert alert-success alert-dismissible fade show rounded-lg shadow-sm" role="alert">
        {{ successMessage }}
      </div>

      <!-- Cards de Métricas Rápidas -->
      <div class="row mb-4">
        <div class="col-md-4">
          <div class="card border-0 shadow-xs rounded-xl p-4 d-flex align-items-center gap-3">
            <div class="icon-badge bg-primary-soft text-primary p-3 rounded">
              <i class="bi bi-people fs-4"></i>
            </div>
            <div>
              <span class="text-muted small d-block">Colaboradores no Sistema</span>
              <h3 class="fw-bold text-slate-900 mb-0 font-headline">{{ pagination.total || totalUsers }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-xs rounded-xl p-4 d-flex align-items-center gap-3">
            <div class="icon-badge bg-success-soft text-success p-3 rounded">
              <i class="bi bi-shield-check fs-4"></i>
            </div>
            <div>
              <span class="text-muted small d-block">Administradores Empresa</span>
              <h3 class="fw-bold text-slate-900 mb-0 font-headline">{{ adminCount }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-xs rounded-xl p-4 d-flex align-items-center gap-3">
            <div class="icon-badge bg-secondary-soft text-secondary p-3 rounded">
              <i class="bi bi-person fs-4"></i>
            </div>
            <div>
              <span class="text-muted small d-block">Operadores / Usuários Comuns</span>
              <h3 class="fw-bold text-slate-900 mb-0 font-headline">{{ userCount }}</h3>
            </div>
          </div>
        </div>
      </div>

      <!-- Barra de Filtros e Busca -->
      <div class="card border-0 shadow-xs mb-4 rounded-xl">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
          <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1" style="max-width: 600px;">
            <!-- Busca -->
            <div class="input-group flex-grow-1" style="min-width: 250px; max-width: 400px;">
              <span class="input-group-text bg-white border-end-0 text-muted">
                <i class="bi bi-search"></i>
              </span>
              <input
                v-model="search"
                type="text"
                class="form-control border-start-0 ps-0 rounded-end-lg"
                placeholder="Pesquisar por nome ou e-mail..."
              />
            </div>
          </div>

          <button
            @click="showForm = !showForm; if(!showForm) resetForm();"
            class="btn btn-primary fw-semibold rounded-lg d-flex align-items-center gap-2"
          >
            <i class="bi" :class="showForm ? 'bi-x-lg' : 'bi-plus-lg'"></i>
            {{ showForm ? 'Fechar Formulário' : 'Novo Usuário' }}
          </button>
        </div>
      </div>

      <!-- Formulário de Cadastro / Edição (Slide-over ou Slide-down card) -->
      <div v-if="showForm" class="card border-0 shadow-xs mb-4 rounded-xl border-top-slate">
        <div class="card-header bg-white border-0 pt-4 px-4">
          <h5 class="fw-bold text-slate-900 mb-0 font-headline">
            {{ isEditing ? 'Editar Perfil e Permissões do Usuário' : 'Cadastrar Novo Usuário Administrativo / Operacional' }}
          </h5>
        </div>
        <div class="card-body px-4 pb-4">
          <form @submit.prevent="handleSubmit">
            <div class="row">
              <!-- Nome -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Nome Completo</label>
                <input v-model="form.name" type="text" class="form-control rounded-lg" required placeholder="Ex: Roberto Silva" />
              </div>
              <!-- Email -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Endereço de E-mail (Acesso Único)</label>
                <input v-model="form.email" type="email" class="form-control rounded-lg" required placeholder="email@dominio.com" />
              </div>

              <!-- Senha -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">
                  Senha de Acesso {{ isEditing ? '(Deixe em branco para manter a atual)' : '' }}
                </label>
                <input v-model="form.password" type="password" class="form-control rounded-lg" :required="!isEditing" placeholder="Mínimo 6 caracteres" />
              </div>
              <!-- Perfil Painel Central -->
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Nível Administrativo Central</label>
                <select v-model="form.role" class="form-select rounded-lg" required>
                  <option value="user">Usuário Comum (Apenas leitura/módulos permitidos)</option>
                  <option value="admin">Administrador Oficina (Acesso total)</option>
                </select>
              </div>

              <!-- Permissões e Papéis Locais da Oficina (RBAC) -->
              <div class="col-12 mb-4 mt-2">
                <label class="form-label small fw-semibold text-slate-700 d-block mb-2">Papéis e Perfis Locais na Oficina (RBAC)</label>
                <div class="d-flex flex-wrap gap-3 p-3 bg-light rounded-lg border">
                  <div v-if="tenantRolesList.length === 0" class="text-muted small">
                    Nenhum papel local cadastrado. Você pode criá-los no módulo de RBAC.
                  </div>
                  <div v-else v-for="role in tenantRolesList" :key="role.id" class="form-check form-check-inline">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :id="'role_' + role.id"
                      :value="role.name"
                      v-model="form.roles"
                    />
                    <label class="form-check-label small fw-medium" :for="'role_' + role.id">
                      {{ role.name }} <span class="text-muted" style="font-size: 0.7rem;">({{ role.description || 'Sem descrição' }})</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
              <button type="button" @click="showForm = false; resetForm();" class="btn btn-light fw-semibold rounded-lg">
                Cancelar
              </button>
              <button type="submit" class="btn btn-primary fw-semibold rounded-lg" :disabled="submitLoading">
                <span v-if="submitLoading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                {{ isEditing ? 'Salvar Alterações' : 'Criar Usuário' }}
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Tabela de Usuários -->
      <div class="card border-0 shadow-xs rounded-xl overflow-hidden">
        <div class="card-body p-0">
          <div v-if="loading" class="text-center py-5">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2 small">Carregando usuários da empresa...</p>
          </div>

          <div v-else-if="users.length === 0" class="text-center py-5">
            <p class="text-muted mb-0">Nenhum usuário associado a esta empresa.</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="px-4 text-slate-700 fw-bold border-bottom-0">Colaborador / Identificação</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Nível Central</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Papéis Locais Oficina (RBAC)</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Status</th>
                  <th class="text-end px-4 text-slate-700 fw-bold border-bottom-0">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="user in users" :key="user.id" class="table-row-compact">
                  <td class="px-4">
                    <div class="d-flex align-items-center gap-3">
                      <!-- Avatar Iniciais -->
                      <div class="avatar bg-slate-900 text-white fw-bold d-flex align-items-center justify-content-center rounded-circle">
                        {{ getInitials(user.name) }}
                      </div>
                      <div>
                        <div class="fw-semibold text-slate-900">{{ user.name }}</div>
                        <small class="text-muted font-monospace" style="font-size: 0.75rem;">{{ user.email }}</small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span
                      class="badge"
                      :class="user.role === 'admin' || user.role === 'super_admin' ? 'bg-primary-soft text-primary' : 'bg-secondary-soft text-secondary'"
                      style="font-size: 0.65rem;"
                    >
                      {{ user.role === 'super_admin' ? 'Super Admin' : (user.role === 'admin' ? 'Administrador' : 'Usuário Comum') }}
                    </span>
                  </td>
                  <td>
                    <!-- Papéis Locais -->
                    <div class="d-flex flex-wrap gap-1">
                      <span v-if="!user.tenant_roles || user.tenant_roles.length === 0" class="text-muted small" style="font-size: 0.75rem;">
                        Nenhum papel atribuído
                      </span>
                      <span
                        v-else
                        v-for="role in user.tenant_roles"
                        :key="role"
                        class="badge bg-slate-900 text-white font-monospace"
                        style="font-size: 0.6rem;"
                      >
                        {{ role }}
                      </span>
                    </div>
                  </td>
                  <td>
                    <span
                      class="badge"
                      :class="user.is_active ? 'bg-success-soft text-success' : 'bg-danger-soft text-danger'"
                      style="font-size: 0.65rem;"
                    >
                      {{ user.is_active ? 'Ativo' : 'Inativo' }}
                    </span>
                  </td>
                  <td class="text-end px-4">
                    <button
                      @click="handleEdit(user)"
                      class="btn btn-outline-primary btn-sm me-2 fw-semibold rounded-lg py-1 px-2.5"
                      style="font-size: 0.75rem;"
                      :disabled="user.role === 'super_admin' && authStore.user?.role !== 'super_admin'"
                    >
                      Editar
                    </button>
                    <button
                      @click="handleDelete(user.id)"
                      class="btn btn-outline-danger btn-sm fw-semibold rounded-lg py-1 px-2.5"
                      style="font-size: 0.75rem;"
                      :disabled="user.id === authStore.user?.id || (user.role === 'super_admin' && authStore.user?.role !== 'super_admin')"
                    >
                      Revogar Acesso
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Rodapé de Paginação -->
        <div v-if="pagination.last_page > 1" class="card-footer bg-white border-0 d-flex justify-content-between align-items-center py-3 px-4 border-top">
          <span class="text-muted small">Total de {{ pagination.total }} usuários associados</span>
          <div class="d-flex gap-1">
            <button
              @click="fetchUsers(pagination.current_page - 1)"
              class="btn btn-outline-secondary btn-sm rounded-lg"
              :disabled="pagination.current_page === 1"
            >
              Anterior
            </button>
            <button
              @click="fetchUsers(pagination.current_page + 1)"
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
.avatar {
  width: 38px;
  height: 38px;
  font-size: 0.85rem;
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
