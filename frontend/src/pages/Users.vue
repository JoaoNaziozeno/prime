<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
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

// Estados do modal e formulário
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitLoading = ref(false);
const formError = ref('');

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
  formError.value = '';
};

// Abrir modal de criação
const openCreateModal = () => {
  resetForm();
  formError.value = '';
  showModal.value = true;
};

// Fechar modal
const closeModal = () => {
  showModal.value = false;
  resetForm();
  formError.value = '';
};

// Fechar com ESC
const handleKeyDown = (e) => {
  if (e.key === 'Escape' && showModal.value) {
    closeModal();
  }
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
  formError.value = '';
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
    closeModal();
    fetchUsers();
  } catch (err) {
    formError.value = err.response?.data?.message || err.response?.data?.error || 'Erro ao processar requisição de usuário.';
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
  formError.value = '';
  showModal.value = true;
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

// Lifecycle
onMounted(async () => {
  window.addEventListener('keydown', handleKeyDown);
  await fetchTenantRoles();
  await fetchUsers(1);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
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
          <span class="badge bg-slate-900 text-white px-3 py-2 fw-semibold rounded-lg shadow-xs">
            <i class="bi bi-building me-1"></i> Oficina: {{ authStore.tenantName || 'Carregando...' }}
          </span>
        </div>
      </header>

      <!-- Alertas de Status -->
      <div v-if="error" class="alert alert-danger alert-dismissible fade show rounded-lg shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ error }}
      </div>
      <div v-if="successMessage" class="alert alert-success alert-dismissible fade show rounded-lg shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> {{ successMessage }}
      </div>

      <!-- Cards de Métricas Rápidas -->
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card border-0 shadow-xs rounded-xl p-4 d-flex flex-row align-items-center gap-3">
            <div class="icon-badge bg-primary-soft text-primary p-3 rounded-lg">
              <i class="bi bi-people fs-4"></i>
            </div>
            <div>
              <span class="text-muted small d-block">Colaboradores no Sistema</span>
              <h3 class="fw-bold text-slate-900 mb-0 font-headline">{{ pagination.total || totalUsers }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-xs rounded-xl p-4 d-flex flex-row align-items-center gap-3">
            <div class="icon-badge bg-success-soft text-success p-3 rounded-lg">
              <i class="bi bi-shield-check fs-4"></i>
            </div>
            <div>
              <span class="text-muted small d-block">Administradores Empresa</span>
              <h3 class="fw-bold text-slate-900 mb-0 font-headline">{{ adminCount }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-xs rounded-xl p-4 d-flex flex-row align-items-center gap-3">
            <div class="icon-badge bg-secondary-soft text-secondary p-3 rounded-lg">
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
            @click="openCreateModal"
            class="btn btn-primary fw-semibold rounded-lg d-flex align-items-center gap-2 shadow-sm px-3.5 py-2"
          >
            <i class="bi bi-plus-lg"></i>
            <span>Novo Usuário</span>
          </button>
        </div>
      </div>

      <!-- Tabela de Usuários -->
      <div class="card border-0 shadow-xs rounded-xl overflow-hidden mb-4">
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
                      <div class="avatar bg-slate-900 text-white fw-bold d-flex align-items-center justify-content-center rounded-circle shadow-xs">
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

      <!-- MODAL FLUTUANTE DE CADASTRO / EDIÇÃO DE USUÁRIO -->
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content bg-white shadow-lg border-0 rounded-xl overflow-hidden">
            
            <!-- Cabeçalho do Modal -->
            <div class="modal-header bg-white px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-3">
                <div class="modal-icon-badge bg-primary-soft text-primary rounded-circle d-flex align-items-center justify-content-center">
                  <i class="bi" :class="isEditing ? 'bi-pencil-square' : 'bi-person-plus-fill'"></i>
                </div>
                <div>
                  <h5 class="fw-bold text-slate-900 mb-0 font-headline d-flex align-items-center gap-2">
                    <span>{{ isEditing ? 'Editar Perfil e Permissões do Usuário' : 'Novo Usuário do Sistema' }}</span>
                    <span v-if="isEditing" class="badge bg-light text-slate-700 border font-monospace px-2 py-0.5" style="font-size: 0.72rem;">
                      #{{ editingId }}
                    </span>
                  </h5>
                  <small class="text-muted">
                    {{ isEditing ? 'Atualize as credenciais e níveis de acesso deste colaborador' : 'Cadastre um novo colaborador e defina seu perfil e permissões de acesso' }}
                  </small>
                </div>
              </div>
              <button type="button" class="btn-close" @click="closeModal" aria-label="Fechar"></button>
            </div>

            <!-- Formulário com Rolagem Interna -->
            <form @submit.prevent="handleSubmit" class="d-flex flex-column flex-grow-1 overflow-hidden">
              <div class="modal-body bg-white px-4 py-4" style="max-height: calc(85vh - 140px); overflow-y: auto;">
                
                <!-- Alerta de Erro no Modal -->
                <div v-if="formError" class="alert alert-danger alert-dismissible fade show rounded-lg py-2 px-3 small mb-3">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  {{ formError }}
                </div>

                <div class="row g-3">
                  <!-- Nome Completo -->
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold text-slate-700">
                      Nome Completo <span class="text-danger">*</span>
                    </label>
                    <input
                      v-model="form.name"
                      type="text"
                      class="form-control rounded-lg"
                      required
                      placeholder="Ex: Roberto Silva"
                    />
                  </div>

                  <!-- Email -->
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold text-slate-700">
                      Endereço de E-mail (Acesso Único) <span class="text-danger">*</span>
                    </label>
                    <input
                      v-model="form.email"
                      type="email"
                      class="form-control rounded-lg"
                      required
                      placeholder="email@dominio.com"
                    />
                  </div>

                  <!-- Senha -->
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold text-slate-700 d-flex justify-content-between align-items-center">
                      <span>Senha de Acesso {{ !isEditing ? '*' : '' }}</span>
                      <span v-if="isEditing" class="text-muted fw-normal" style="font-size: 0.7rem;">(Deixe em branco para manter a atual)</span>
                    </label>
                    <input
                      v-model="form.password"
                      type="password"
                      class="form-control rounded-lg"
                      :required="!isEditing"
                      placeholder="Mínimo 6 caracteres"
                    />
                  </div>

                  <!-- Nível Administrativo Central -->
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold text-slate-700">
                      Nível Administrativo Central <span class="text-danger">*</span>
                    </label>
                    <select v-model="form.role" class="form-select rounded-lg" required>
                      <option value="user">Usuário Comum (Apenas leitura/módulos permitidos)</option>
                      <option value="admin">Administrador Oficina (Acesso total)</option>
                    </select>
                  </div>

                  <!-- Papéis e Perfis Locais na Oficina (RBAC) -->
                  <div class="col-12 mt-3">
                    <label class="form-label small fw-semibold text-slate-700 d-block mb-2">
                      Papéis e Perfis Locais na Oficina (RBAC)
                    </label>
                    <div class="p-3 bg-light rounded-lg border">
                      <div v-if="tenantRolesList.length === 0" class="text-muted small d-flex align-items-center gap-2">
                        <i class="bi bi-info-circle text-primary"></i>
                        <span>Nenhum papel local cadastrado. Você pode criá-los no módulo de RBAC.</span>
                      </div>
                      <div v-else class="row g-2">
                        <div
                          v-for="role in tenantRolesList"
                          :key="role.id"
                          class="col-sm-6"
                        >
                          <div class="form-check p-2.5 bg-white rounded-lg border">
                            <input
                              class="form-check-input ms-1 me-2"
                              type="checkbox"
                              :id="'role_' + role.id"
                              :value="role.name"
                              v-model="form.roles"
                            />
                            <label class="form-check-label small fw-medium" :for="'role_' + role.id">
                              {{ role.name }}
                              <span class="text-muted d-block" style="font-size: 0.7rem;">
                                {{ role.description || 'Sem descrição cadastrada' }}
                              </span>
                            </label>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

              <!-- Rodapé de Ações do Modal -->
              <div class="modal-footer bg-light px-4 py-3 border-top d-flex justify-content-end align-items-center gap-2">
                <button type="button" @click="closeModal" class="btn btn-outline-secondary fw-semibold rounded-lg px-3">
                  Cancelar
                </button>
                <button
                  type="submit"
                  class="btn btn-primary fw-semibold rounded-lg px-4 d-flex align-items-center gap-2 shadow-sm"
                  :disabled="submitLoading"
                >
                  <span v-if="submitLoading" class="spinner-border spinner-border-sm" role="status"></span>
                  <span>{{ isEditing ? 'Salvar Alterações' : 'Criar Usuário' }}</span>
                </button>
              </div>
            </form>

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
.rounded-xl {
  border-radius: 12px;
}
.rounded-lg {
  border-radius: 8px;
}
.bg-primary-soft {
  background-color: rgba(13, 110, 253, 0.08);
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

/* Estilos do Modal Flutuante (Padrão Prime ERP) */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(4px);
  z-index: 1050;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  animation: modalFadeIn 0.2s ease-out;
}

.modal-dialog {
  width: 100%;
  max-width: 820px;
  margin: auto;
  animation: modalSlideDown 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.modal-content {
  background-color: #ffffff !important;
}

@keyframes modalFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes modalSlideDown {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.modal-icon-badge {
  width: 38px;
  height: 38px;
  font-size: 1.1rem;
}
</style>
