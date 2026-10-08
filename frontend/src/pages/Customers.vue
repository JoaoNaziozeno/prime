<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import Sidebar from '../components/Sidebar.vue';

const router = useRouter();
const authStore = useAuthStore();

// Estados da listagem e paginação
const customers = ref([]);
const pagination = ref({});
const search = ref('');
const loading = ref(false);
const error = ref('');

// Estados do modal e formulário
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitLoading = ref(false);
const formError = ref('');
const loadingCep = ref(false);
const numberInputRef = ref(null);

// Estados do Wizard Stepper (Passo a Passo)
const currentStep = ref(1);
const totalSteps = 4;
const maxVisitedStep = ref(1);

const form = ref({
  name: '',
  trade_name: '',
  email: '',
  phone: '',
  contact_name: '',
  cpf_cnpj: '',
  state_registration: '',
  type: 'individual',
  street: '',
  number: '',
  complement: '',
  neighborhood: '',
  city: '',
  state: '',
  zip_code: '',
  status: 'active',
  notes: ''
});

// Limpar e redefinir formulário
const resetForm = () => {
  form.value = {
    name: '',
    trade_name: '',
    email: '',
    phone: '',
    contact_name: '',
    cpf_cnpj: '',
    state_registration: '',
    type: 'individual',
    street: '',
    number: '',
    complement: '',
    neighborhood: '',
    city: '',
    state: '',
    zip_code: '',
    status: 'active',
    notes: ''
  };
  isEditing.value = false;
  editingId.value = null;
  formError.value = '';
  currentStep.value = 1;
  maxVisitedStep.value = 1;
};

// Abrir modal para novo cliente
const openCreateModal = () => {
  resetForm();
  currentStep.value = 1;
  maxVisitedStep.value = 1;
  showModal.value = true;
};

// Fechar modal
const closeModal = () => {
  showModal.value = false;
  resetForm();
};

// Fechar com a tecla ESC
const handleKeyDown = (e) => {
  if (e.key === 'Escape' && showModal.value) {
    closeModal();
  }
};

// Validação por etapa
const validateStep = (step) => {
  formError.value = '';
  if (step === 1) {
    if (!form.value.name || !form.value.name.trim()) {
      formError.value = form.value.type === 'company'
        ? 'Por favor, informe a Razão Social da empresa.'
        : 'Por favor, informe o Nome Completo do cliente.';
      return false;
    }
  }
  return true;
};

// Avançar etapa no Wizard
const nextStep = () => {
  if (validateStep(currentStep.value)) {
    if (currentStep.value < totalSteps) {
      currentStep.value++;
      if (currentStep.value > maxVisitedStep.value) {
        maxVisitedStep.value = currentStep.value;
      }
    }
  }
};

// Voltar etapa no Wizard
const prevStep = () => {
  formError.value = '';
  if (currentStep.value > 1) {
    currentStep.value--;
  }
};

// Ir para etapa específica (clique no stepper)
const goToStep = (step) => {
  if (isEditing.value || step <= maxVisitedStep.value) {
    if (step > currentStep.value) {
      if (!validateStep(currentStep.value)) return;
    }
    formError.value = '';
    currentStep.value = step;
  }
};

// Consulta automática de CEP via ViaCEP
const handleCepBlur = async () => {
  const cleanCep = (form.value.zip_code || '').replace(/\D/g, '');
  if (cleanCep.length === 8) {
    loadingCep.value = true;
    try {
      const response = await fetch(`https://viacep.com.br/ws/${cleanCep}/json/`);
      const data = await response.json();
      if (!data.erro) {
        form.value.street = data.logradouro || form.value.street;
        form.value.neighborhood = data.bairro || form.value.neighborhood;
        form.value.city = data.localidade || form.value.city;
        form.value.state = data.uf || form.value.state;
        setTimeout(() => {
          numberInputRef.value?.focus();
        }, 100);
      }
    } catch (e) {
      console.warn('Erro ao consultar ViaCEP:', e);
    } finally {
      loadingCep.value = false;
    }
  }
};

// Máscara dinâmica CPF / CNPJ com alternância 100% automática PF / PJ
const applyCpfCnpjMask = (event) => {
  let val = event.target.value.replace(/\D/g, '');
  if (val.length > 14) val = val.slice(0, 14);

  // Alternância automática: > 11 dígitos é CNPJ (PJ), <= 11 dígitos é CPF (PF)
  if (val.length > 11) {
    form.value.type = 'company';
  } else {
    form.value.type = 'individual';
  }

  if (val.length <= 11) {
    val = val.replace(/(\d{3})(\d)/, '$1.$2');
    val = val.replace(/(\d{3})(\d)/, '$1.$2');
    val = val.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  } else {
    val = val.replace(/^(\d{2})(\d)/, '$1.$2');
    val = val.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
    val = val.replace(/\.(\d{3})(\d)/, '.$1/$2');
    val = val.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
  }
  form.value.cpf_cnpj = val;
};

// Máscara de Telefone / Celular / WhatsApp
const applyPhoneMask = (event) => {
  let val = event.target.value.replace(/\D/g, '');
  if (val.length > 11) val = val.slice(0, 11);

  if (val.length > 10) {
    val = val.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
  } else if (val.length > 5) {
    val = val.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
  } else if (val.length > 2) {
    val = val.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
  } else if (val.length > 0) {
    val = val.replace(/^(\d*)$/, '($1');
  }
  form.value.phone = val;
};

// Máscara de CEP
const applyZipCodeMask = (event) => {
  let val = event.target.value.replace(/\D/g, '');
  if (val.length > 8) val = val.slice(0, 8);
  if (val.length > 5) {
    val = val.replace(/^(\d{5})(\d{1,3})$/, '$1-$2');
  }
  form.value.zip_code = val;
  if (val.replace(/\D/g, '').length === 8) {
    handleCepBlur();
  }
};

// Buscar clientes da API com paginação
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

// Submeter formulário (Criar ou Atualizar)
const handleSubmit = async () => {
  if (!validateStep(1)) {
    currentStep.value = 1;
    return;
  }
  submitLoading.value = true;
  formError.value = '';
  try {
    if (isEditing.value) {
      await api.put(`/customers/${editingId.value}`, form.value);
    } else {
      await api.post('/customers', form.value);
    }
    closeModal();
    fetchCustomers(pagination.value.current_page || 1);
  } catch (err) {
    formError.value = err.response?.data?.message || 'Erro ao salvar cliente. Verifique os dados informados.';
  } finally {
    submitLoading.value = false;
  }
};

// Carregar dados no modal para edição
const handleEdit = (customer) => {
  form.value = {
    name: customer.name || '',
    trade_name: customer.trade_name || '',
    email: customer.email || '',
    phone: customer.phone || '',
    contact_name: customer.contact_name || '',
    cpf_cnpj: customer.cpf_cnpj || '',
    state_registration: customer.state_registration || '',
    type: customer.type || 'individual',
    street: customer.street || '',
    number: customer.number || '',
    complement: customer.complement || '',
    neighborhood: customer.neighborhood || '',
    city: customer.city || '',
    state: customer.state || '',
    zip_code: customer.zip_code || '',
    status: customer.status || 'active',
    notes: customer.notes || ''
  };
  isEditing.value = true;
  editingId.value = customer.id;
  formError.value = '';
  currentStep.value = 1;
  maxVisitedStep.value = 4; // Na edição, permite livre acesso a todas as etapas
  showModal.value = true;
};

// Excluir cliente
const handleDelete = async (id) => {
  if (confirm('Tem certeza que deseja excluir este cliente?')) {
    try {
      await api.delete(`/customers/${id}`);
      fetchCustomers(pagination.value.current_page || 1);
    } catch (err) {
      alert('Erro ao excluir cliente. Verifique se ele possui ordens de serviço ativas.');
    }
  }
};

// Monitora campo de busca
watch(search, () => {
  fetchCustomers(1);
});

// Lifecycle
onMounted(() => {
  fetchCustomers(1);
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});
</script>

<template>
  <div class="d-flex">
    <!-- Sidebar Modular -->
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

      <!-- Alerta de Erro Geral -->
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
                placeholder="Pesquisar por ID, nome, e-mail ou documento..."
              />
            </div>
          </div>
          <button
            @click="openCreateModal"
            class="btn btn-primary fw-semibold rounded-lg d-flex align-items-center gap-2"
          >
            <i class="bi bi-plus-lg"></i>
            Novo Cliente
          </button>
        </div>
      </div>

      <!-- Modal Flutuante de Cadastro / Edição com Wizard Guiado -->
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content bg-white shadow-lg border-0 rounded-xl overflow-hidden">
            
            <!-- Cabeçalho do Modal -->
            <div class="modal-header bg-white px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-3">
                <div class="modal-icon-badge bg-primary-soft text-primary rounded-circle d-flex align-items-center justify-content-center">
                  <i class="bi" :class="isEditing ? 'bi-pencil-square' : 'bi-person-gear'"></i>
                </div>
                <div>
                  <h5 class="fw-bold text-slate-900 mb-0 font-headline d-flex align-items-center gap-2">
                    <span>{{ isEditing ? 'Editar Registro de Cliente' : 'Assistente de Cadastro de Clientes' }}</span>
                    <span v-if="isEditing" class="badge bg-light text-slate-700 border font-monospace px-2 py-0.5" style="font-size: 0.75rem;">
                      {{ editingId }}
                    </span>
                  </h5>
                  <small class="text-muted">
                    {{ isEditing ? 'Atualize os dados cadastrais navegando pelas etapas' : 'Siga o passo a passo para cadastrar clientes ou empresas parceiras' }}
                  </small>
                </div>
              </div>
              <div class="d-flex align-items-center gap-3">
                <span class="badge bg-light text-slate-700 border px-3 py-1.5 rounded-pill fw-semibold small">
                  Passo {{ currentStep }} de {{ totalSteps }}
                </span>
                <button type="button" class="btn-close" @click="closeModal" aria-label="Fechar"></button>
              </div>
            </div>

            <!-- Barra de Progresso / Stepper Interativo -->
            <div class="stepper-container px-4 py-3 border-bottom">
              <div class="stepper-wrapper position-relative d-flex justify-content-between align-items-center">
                <div class="stepper-line-bg"></div>
                <div class="stepper-line-active" :style="{ width: `${((currentStep - 1) / (totalSteps - 1)) * 100}%` }"></div>

                <!-- Etapa 1 -->
                <button
                  type="button"
                  @click="goToStep(1)"
                  class="stepper-step btn p-0 border-0 d-flex flex-column align-items-center"
                  :class="{ 'active': currentStep === 1, 'completed': currentStep > 1, 'disabled-step': !isEditing && maxVisitedStep < 1 }"
                >
                  <div class="step-circle d-flex align-items-center justify-content-center shadow-xs">
                    <i v-if="currentStep > 1" class="bi bi-check-lg fw-bold"></i>
                    <i v-else class="bi bi-person-fill"></i>
                  </div>
                  <span class="step-label mt-1 small fw-semibold">1. Identificação</span>
                </button>

                <!-- Etapa 2 -->
                <button
                  type="button"
                  @click="goToStep(2)"
                  class="stepper-step btn p-0 border-0 d-flex flex-column align-items-center"
                  :class="{ 'active': currentStep === 2, 'completed': currentStep > 2, 'disabled-step': !isEditing && maxVisitedStep < 2 }"
                >
                  <div class="step-circle d-flex align-items-center justify-content-center shadow-xs">
                    <i v-if="currentStep > 2" class="bi bi-check-lg fw-bold"></i>
                    <i v-else class="bi bi-telephone-fill"></i>
                  </div>
                  <span class="step-label mt-1 small fw-semibold">2. Contatos</span>
                </button>

                <!-- Etapa 3 -->
                <button
                  type="button"
                  @click="goToStep(3)"
                  class="stepper-step btn p-0 border-0 d-flex flex-column align-items-center"
                  :class="{ 'active': currentStep === 3, 'completed': currentStep > 3, 'disabled-step': !isEditing && maxVisitedStep < 3 }"
                >
                  <div class="step-circle d-flex align-items-center justify-content-center shadow-xs">
                    <i v-if="currentStep > 3" class="bi bi-check-lg fw-bold"></i>
                    <i v-else class="bi bi-geo-alt-fill"></i>
                  </div>
                  <span class="step-label mt-1 small fw-semibold">3. Endereço</span>
                </button>

                <!-- Etapa 4 -->
                <button
                  type="button"
                  @click="goToStep(4)"
                  class="stepper-step btn p-0 border-0 d-flex flex-column align-items-center"
                  :class="{ 'active': currentStep === 4, 'completed': currentStep > 4, 'disabled-step': !isEditing && maxVisitedStep < 4 }"
                >
                  <div class="step-circle d-flex align-items-center justify-content-center shadow-xs">
                    <i class="bi bi-check2-circle"></i>
                  </div>
                  <span class="step-label mt-1 small fw-semibold">4. Revisão</span>
                </button>
              </div>
            </div>

            <!-- Formulário com Rolagem Interna -->
            <form @submit.prevent="handleSubmit" class="d-flex flex-column flex-grow-1 overflow-hidden">
              <div class="modal-body bg-white px-4 py-4" style="max-height: calc(85vh - 190px); overflow-y: auto;">
                
                <!-- Alerta de Erro no Modal -->
                <div v-if="formError" class="alert alert-danger alert-dismissible fade show rounded-lg py-2 px-3 small mb-3">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  {{ formError }}
                </div>

                <!-- ================= ETAPA 1: IDENTIFICAÇÃO ================= -->
                <div v-show="currentStep === 1" class="wizard-step-content">
                  <div class="row g-3">
                    <!-- Razão Social / Nome Completo -->
                    <div class="col-md-7">
                      <label class="form-label small fw-semibold text-slate-700">
                        {{ form.type === 'company' ? 'Razão Social' : 'Nome Completo' }} <span class="text-danger">*</span>
                      </label>
                      <input
                        v-model="form.name"
                        type="text"
                        class="form-control rounded-lg"
                        required
                        :placeholder="form.type === 'company' ? 'Ex: Transportadora Santos Ltda' : 'Ex: Carlos Alberto da Silva'"
                      />
                    </div>

                    <!-- CPF / CNPJ com Alternância Automática -->
                    <div class="col-md-5">
                      <label class="form-label small fw-semibold text-slate-700 d-flex justify-content-between align-items-center">
                        <span>{{ form.type === 'company' ? 'CNPJ' : 'CPF' }}</span>
                        <span
                          class="badge"
                          :class="form.type === 'company' ? 'bg-primary-soft text-primary' : 'bg-secondary-soft text-secondary'"
                          style="font-size: 0.68rem;"
                        >
                          {{ form.type === 'company' ? 'Pessoa Jurídica' : 'Pessoa Física' }}
                        </span>
                      </label>
                      <input
                        :value="form.cpf_cnpj"
                        @input="applyCpfCnpjMask"
                        type="text"
                        class="form-control rounded-lg font-monospace"
                        :placeholder="form.type === 'company' ? '00.000.000/0000-00' : '000.000.000-00'"
                      />
                    </div>

                    <!-- Nome Fantasia (visível para PJ) -->
                    <div v-if="form.type === 'company'" class="col-md-5">
                      <label class="form-label small fw-semibold text-slate-700">Nome Fantasia</label>
                      <input
                        v-model="form.trade_name"
                        type="text"
                        class="form-control rounded-lg"
                        placeholder="Ex: TransSantos Logística"
                      />
                    </div>

                    <!-- Inscrição Estadual (visível para PJ) -->
                    <div v-if="form.type === 'company'" class="col-md-4">
                      <label class="form-label small fw-semibold text-slate-700">Inscrição Estadual (IE)</label>
                      <input
                        v-model="form.state_registration"
                        type="text"
                        class="form-control rounded-lg"
                        placeholder="Ex: 123.456.789.110 ou ISENTO"
                      />
                    </div>

                    <!-- Status Cadastral -->
                    <div :class="form.type === 'company' ? 'col-md-3' : 'col-md-5'">
                      <label class="form-label small fw-semibold text-slate-700">Status Cadastral</label>
                      <select v-model="form.status" class="form-select rounded-lg">
                        <option value="active">Ativo</option>
                        <option value="inactive">Inativo</option>
                        <option value="suspended">Suspenso</option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- ================= ETAPA 2: CONTATOS ================= -->
                <div v-show="currentStep === 2" class="wizard-step-content">
                  <!-- Card Conversacional Informativo -->
                  <div class="alert alert-primary bg-primary-soft border-primary border-opacity-25 rounded-xl d-flex align-items-start gap-3 p-3 mb-4">
                    <div class="p-2 bg-primary text-white rounded-lg lh-1">
                      <i class="bi bi-telephone-inbound-fill fs-5"></i>
                    </div>
                    <div>
                      <h6 class="fw-bold text-slate-900 mb-1 small">Canais de Comunicação Direta</h6>
                      <p class="mb-0 text-muted small">
                        Por onde a sua oficina enviará orçamentos, laudos técnicos de ordem de serviço e avisos de veículos prontos?
                      </p>
                    </div>
                  </div>

                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold text-slate-700">Telefone / WhatsApp Principal</label>
                      <div class="input-group">
                        <span class="input-group-text bg-white text-primary border-end-0"><i class="bi bi-whatsapp"></i></span>
                        <input
                          :value="form.phone"
                          @input="applyPhoneMask"
                          type="text"
                          class="form-control rounded-end-lg border-start-0 ps-0 font-monospace"
                          placeholder="(00) 00000-0000"
                        />
                      </div>
                      <small class="text-muted d-block mt-1">
                        Canal ágil para alertas em tempo real sobre ordens de serviço.
                      </small>
                    </div>

                    <div class="col-md-6">
                      <label class="form-label small fw-semibold text-slate-700">E-mail para Faturamento & XML</label>
                      <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                        <input
                          v-model="form.email"
                          type="email"
                          class="form-control rounded-end-lg border-start-0 ps-0"
                          placeholder="faturamento@empresa.com.br"
                        />
                      </div>
                      <small class="text-muted d-block mt-1">
                        Para envio de notas fiscais eletrônicas (NF-e), boletos e relatórios mensais.
                      </small>
                    </div>

                    <div class="col-12">
                      <label class="form-label small fw-semibold text-slate-700">Nome do Contato Autorizado / Gestor de Frota</label>
                      <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-person-badge"></i></span>
                        <input
                          v-model="form.contact_name"
                          type="text"
                          class="form-control rounded-end-lg border-start-0 ps-0"
                          placeholder="Ex: Sr. Roberto Silva (Gerente de Manutenção)"
                        />
                      </div>
                      <small class="text-muted d-block mt-1">
                        A pessoa autorizada na empresa a aprovar orçamentos e liberar serviços na oficina.
                      </small>
                    </div>
                  </div>
                </div>

                <!-- ================= ETAPA 3: ENDEREÇO ================= -->
                <div v-show="currentStep === 3" class="wizard-step-content">
                  <!-- Card Conversacional Informativo -->
                  <div class="alert alert-primary bg-primary-soft border-primary border-opacity-25 rounded-xl d-flex align-items-start gap-3 p-3 mb-4">
                    <div class="p-2 bg-primary text-white rounded-lg lh-1">
                      <i class="bi bi-geo-alt-fill fs-5"></i>
                    </div>
                    <div>
                      <h6 class="fw-bold text-slate-900 mb-1 small">Localização e Endereço da Empresa</h6>
                      <p class="mb-0 text-muted small">
                        Basta digitar o CEP de 8 dígitos que o sistema autocompleta logradouro, bairro, cidade e estado via ViaCEP.
                      </p>
                    </div>
                  </div>

                  <div class="row g-3">
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold text-slate-700 d-flex justify-content-between">
                        <span>CEP</span>
                        <span v-if="loadingCep" class="spinner-border spinner-border-sm text-primary" role="status"></span>
                      </label>
                      <div class="input-group">
                        <input
                          :value="form.zip_code"
                          @input="applyZipCodeMask"
                          @blur="handleCepBlur"
                          type="text"
                          class="form-control font-monospace rounded-start-lg"
                          placeholder="00000-000"
                          maxlength="9"
                        />
                        <button class="btn btn-outline-secondary" type="button" @click="handleCepBlur" title="Buscar CEP">
                          <i class="bi bi-search"></i>
                        </button>
                      </div>
                    </div>

                    <div class="col-md-8">
                      <label class="form-label small fw-semibold text-slate-700">Logradouro / Rua / Avenida</label>
                      <input v-model="form.street" type="text" class="form-control rounded-lg" placeholder="Ex: Av. das Nações Unidas" />
                    </div>

                    <div class="col-md-3">
                      <label class="form-label small fw-semibold text-slate-700">Número</label>
                      <input ref="numberInputRef" v-model="form.number" type="text" class="form-control rounded-lg" placeholder="Ex: 1500" />
                    </div>

                    <div class="col-md-3">
                      <label class="form-label small fw-semibold text-slate-700">Complemento</label>
                      <input v-model="form.complement" type="text" class="form-control rounded-lg" placeholder="Ex: Galpão B / Sala 12" />
                    </div>

                    <div class="col-md-3">
                      <label class="form-label small fw-semibold text-slate-700">Bairro</label>
                      <input v-model="form.neighborhood" type="text" class="form-control rounded-lg" placeholder="Ex: Distrito Industrial" />
                    </div>

                    <div class="col-md-2">
                      <label class="form-label small fw-semibold text-slate-700">Cidade</label>
                      <input v-model="form.city" type="text" class="form-control rounded-lg" placeholder="Ex: São Paulo" />
                    </div>

                    <div class="col-md-1">
                      <label class="form-label small fw-semibold text-slate-700">UF</label>
                      <input v-model="form.state" type="text" class="form-control rounded-lg text-uppercase font-monospace text-center" maxlength="2" placeholder="SP" />
                    </div>
                  </div>
                </div>

                <!-- ================= ETAPA 4: REVISÃO & OBSERVAÇÕES ================= -->
                <div v-show="currentStep === 4" class="wizard-step-content">
                  <!-- Card Conversacional Informativo -->
                  <div class="alert alert-dark bg-slate-900 text-white rounded-xl d-flex align-items-start gap-3 p-3 mb-4 border-0">
                    <div class="p-2 bg-primary text-white rounded-lg lh-1">
                      <i class="bi bi-check2-circle fs-5"></i>
                    </div>
                    <div>
                      <h6 class="fw-bold text-white mb-1 small">Tudo pronto! Revise antes de finalizar</h6>
                      <p class="mb-0 text-slate-300 small">
                        Confira a síntese do cadastro e registre observações particulares sobre condições comerciais ou frota do cliente.
                      </p>
                    </div>
                  </div>

                  <!-- Card de Conferência Resumida -->
                  <div class="card bg-light border rounded-xl p-3 mb-4">
                    <div class="row g-2 small">
                      <div class="col-md-6 border-bottom pb-2">
                        <span class="text-muted d-block">Cliente / Razão Social:</span>
                        <strong class="text-slate-900">{{ form.name || 'Não informado' }}</strong>
                        <span v-if="form.trade_name" class="text-muted small ms-1">({{ form.trade_name }})</span>
                      </div>
                      <div class="col-md-6 border-bottom pb-2">
                        <span class="text-muted d-block">Documento & IE:</span>
                        <strong class="text-slate-900 font-monospace">{{ form.cpf_cnpj || 'Sem documento' }}</strong>
                        <span v-if="form.state_registration" class="text-muted small ms-2">IE: {{ form.state_registration }}</span>
                      </div>
                      <div class="col-md-6 border-bottom pb-2 pt-1">
                        <span class="text-muted d-block">Contato & WhatsApp:</span>
                        <span class="text-slate-900 font-semibold">{{ form.contact_name || 'Sem contato específico' }}</span>
                        <span v-if="form.phone" class="text-muted small ms-2 font-monospace">
                          <i class="bi bi-whatsapp text-primary me-1"></i>{{ form.phone }}
                        </span>
                      </div>
                      <div class="col-md-6 border-bottom pb-2 pt-1">
                        <span class="text-muted d-block">E-mail:</span>
                        <span class="text-slate-900 font-monospace">{{ form.email || 'Não informado' }}</span>
                      </div>
                      <div class="col-12 pt-1">
                        <span class="text-muted d-block">Endereço de Localização:</span>
                        <span class="text-slate-900" v-if="form.street || form.city">
                          {{ form.street }}{{ form.number ? ', ' + form.number : '' }}
                          {{ form.complement ? ' - ' + form.complement : '' }}
                          {{ form.neighborhood ? ' - ' + form.neighborhood : '' }}
                          {{ form.city ? ' - ' + form.city : '' }}
                          {{ form.state ? '/' + form.state : '' }}
                          {{ form.zip_code ? ' (CEP: ' + form.zip_code + ')' : '' }}
                        </span>
                        <span v-else class="text-muted fst-italic">Endereço não informado</span>
                      </div>
                    </div>
                  </div>

                  <!-- Observações Internas da Oficina -->
                  <div>
                    <label class="form-label small fw-semibold text-slate-700">Observações Internas da Oficina</label>
                    <textarea
                      v-model="form.notes"
                      class="form-control rounded-lg"
                      rows="3"
                      placeholder="Observações sobre faturamento a prazo (ex: 30 DDL), veículos da frota, particularidades de pagamento ou autorizações..."
                    ></textarea>
                  </div>
                </div>

              </div>

              <!-- Rodapé de Ações com Navegação Guiada -->
              <div class="modal-footer bg-light px-4 py-3 border-top d-flex justify-content-between align-items-center">
                <div>
                  <button
                    v-if="currentStep > 1"
                    type="button"
                    @click="prevStep"
                    class="btn btn-outline-secondary fw-semibold rounded-lg px-3 d-flex align-items-center gap-2"
                  >
                    <i class="bi bi-arrow-left"></i>
                    Voltar
                  </button>
                </div>

                <div class="d-flex align-items-center gap-2">
                  <button type="button" @click="closeModal" class="btn btn-outline-secondary fw-semibold rounded-lg px-3">
                    Cancelar
                  </button>

                  <!-- Avançar (Passos 1 a 3) -->
                  <button
                    v-if="currentStep < totalSteps"
                    type="button"
                    @click="nextStep"
                    class="btn btn-primary fw-semibold rounded-lg px-4 d-flex align-items-center gap-2"
                  >
                    <span>Próximo Passo</span>
                    <i class="bi bi-arrow-right"></i>
                  </button>

                  <!-- Salvar / Concluir (Passo 4) -->
                  <button
                    v-else
                    type="submit"
                    class="btn btn-primary fw-semibold rounded-lg px-4 d-flex align-items-center gap-2 shadow-sm"
                    :disabled="submitLoading"
                  >
                    <span v-if="submitLoading" class="spinner-border spinner-border-sm" role="status"></span>
                    <i v-else class="bi" :class="isEditing ? 'bi-check-lg' : 'bi-check2-circle'"></i>
                    {{ isEditing ? 'Atualizar Cliente' : 'Concluir e Salvar' }}
                  </button>
                </div>
              </div>
            </form>

          </div>
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
                  <th class="ps-4 text-slate-700 fw-bold border-bottom-0" style="width: 80px;">Contrato</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Nome / Localização</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">CPF / CNPJ</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Contatos</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Tipo</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Status</th>
                  <th class="text-end px-4 text-slate-700 fw-bold border-bottom-0">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="customer in customers" :key="customer.id" class="table-row-compact">
                  <td class="ps-4">
                    <span class="badge bg-light text-slate-700 border font-monospace px-2 py-1" style="font-size: 0.72rem;">
                      {{ customer.id }}
                    </span>
                  </td>
                  <td>
                    <div class="fw-semibold text-slate-900">{{ customer.name }}</div>
                    <div v-if="customer.trade_name" class="small text-muted font-monospace" style="font-size: 0.72rem;">
                      {{ customer.trade_name }}
                    </div>
                    <small class="text-muted text-uppercase font-monospace" style="font-size: 0.65rem;" v-if="customer.city">
                      <span v-if="customer.neighborhood">{{ customer.neighborhood }}, </span>{{ customer.city }} - {{ customer.state }}
                    </small>
                  </td>
                  <td class="font-monospace small text-slate-700">
                    <div>{{ customer.cpf_cnpj || 'Não informado' }}</div>
                    <small v-if="customer.state_registration" class="text-muted font-monospace" style="font-size: 0.65rem;">
                      IE: {{ customer.state_registration }}
                    </small>
                  </td>
                  <td class="small text-slate-700">
                    <div v-if="customer.phone" class="font-monospace">
                      <i class="bi bi-whatsapp text-success me-1"></i>{{ customer.phone }}
                    </div>
                    <div v-if="customer.email" class="text-muted" style="font-size: 0.75rem;">
                      <i class="bi bi-envelope me-1"></i>{{ customer.email }}
                    </div>
                    <small v-if="customer.contact_name" class="text-muted font-monospace" style="font-size: 0.68rem;">
                      <i class="bi bi-person me-1"></i>{{ customer.contact_name }}
                    </small>
                  </td>
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
  background-color: rgba(13, 110, 253, 0.08);
}
.bg-secondary-soft {
  background-color: rgba(108, 117, 125, 0.1);
}
.bg-success-soft {
  background-color: rgba(40, 167, 69, 0.08);
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

/* Estilos do Modal Flutuante */
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
  max-width: 860px;
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

/* Estilos do Stepper Wizard */
.stepper-container {
  background-color: #f8fafc;
}

.stepper-wrapper {
  max-width: 600px;
  margin: 0 auto;
}

.stepper-line-bg {
  position: absolute;
  top: 18px;
  left: 35px;
  right: 35px;
  height: 3px;
  background-color: #e2e8f0;
  z-index: 1;
}

.stepper-line-active {
  position: absolute;
  top: 18px;
  left: 35px;
  height: 3px;
  background-color: #0f172a;
  z-index: 2;
  transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.stepper-step {
  position: relative;
  z-index: 3;
  background: transparent;
  text-decoration: none;
  cursor: pointer;
  outline: none;
}

.stepper-step.disabled-step {
  cursor: not-allowed;
  opacity: 0.6;
}

.step-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: #ffffff;
  color: #64748b;
  font-weight: 600;
  font-size: 0.85rem;
  border: 2px solid #cbd5e1;
  transition: all 0.2s ease;
}

.stepper-step.active .step-circle {
  background-color: #0f172a;
  color: #ffffff;
  border-color: #0f172a;
  box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.2);
}

.stepper-step.completed .step-circle {
  background-color: #0d6efd;
  color: #ffffff;
  border-color: #0d6efd;
}

.step-label {
  font-size: 0.72rem;
  color: #64748b;
  transition: color 0.2s ease;
}

.stepper-step.active .step-label {
  color: #0f172a;
  font-weight: 700 !important;
}

.stepper-step.completed .step-label {
  color: #0d6efd;
  font-weight: 600;
}

.type-card {
  cursor: pointer;
  transition: all 0.15s ease-in-out;
}

.type-card:hover {
  transform: translateY(-2px);
}

.hover-border-slate:hover {
  border-color: #94a3b8 !important;
}

@keyframes stepFadeIn {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}

.wizard-step-content {
  animation: stepFadeIn 0.2s ease-out;
}
</style>\n