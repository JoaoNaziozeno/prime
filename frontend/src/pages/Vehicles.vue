<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import Sidebar from '../components/Sidebar.vue';

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();

// Estados da lista
const vehicles = ref([]);
const pagination = ref({});
const search = ref('');
const filterStatus = ref('');
const filterType = ref('');
const filterCustomerId = ref(null);
const loading = ref(false);
const error = ref('');

// Lista de clientes para vínculo
const customersList = ref([]);
const loadingCustomers = ref(false);
const customerSearch = ref('');

// Estados do modal e formulário
const showModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const submitLoading = ref(false);
const formError = ref('');

// Estados do Wizard Stepper (Passo a Passo)
const currentStep = ref(1);
const totalSteps = 4;
const maxVisitedStep = ref(1);

const form = ref({
  customer_id: null,
  plate: '',
  fleet_number: '',
  model: '',
  brand: '',
  year: new Date().getFullYear(),
  type: 'truck',
  vin: '',
  color: '',
  fuel_type: 'Diesel S10',
  engine_type: '',
  axles: '6x2 (Trucado)',
  body_type: 'Cavalo Mecânico',
  capacity_tons: '',
  renavam: '',
  license_expiration: '',
  status: 'active',
  odometer: ''
});

// Reset do formulário
const resetForm = () => {
  form.value = {
    customer_id: null,
    plate: '',
    fleet_number: '',
    model: '',
    brand: '',
    year: new Date().getFullYear(),
    type: 'truck',
    vin: '',
    color: '',
    fuel_type: 'Diesel S10',
    engine_type: '',
    axles: '6x2 (Trucado)',
    body_type: 'Cavalo Mecânico',
    capacity_tons: '',
    renavam: '',
    license_expiration: '',
    status: 'active',
    odometer: ''
  };
  isEditing.value = false;
  editingId.value = null;
  formError.value = '';
  currentStep.value = 1;
  maxVisitedStep.value = 1;
  customerSearch.value = '';
};

// Abrir modal de criação
const openCreateModal = (preselectedCustomerId = null) => {
  resetForm();
  if (preselectedCustomerId) {
    form.value.customer_id = preselectedCustomerId;
  } else if (filterCustomerId.value) {
    form.value.customer_id = filterCustomerId.value;
  }
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

// Carregar clientes para o seletor
const fetchCustomersList = async () => {
  loadingCustomers.value = true;
  try {
    const res = await api.get('/customers', { params: { per_page: 200 } });
    customersList.value = res.data.data || [];
  } catch (err) {
    console.error('Erro ao buscar lista de clientes:', err);
  } finally {
    loadingCustomers.value = false;
  }
};

// Clientes filtrados para busca no modal
const filteredCustomers = computed(() => {
  if (!customerSearch.value) return customersList.value;
  const term = customerSearch.value.toLowerCase().trim();
  return customersList.value.filter(c =>
    c.name.toLowerCase().includes(term) ||
    (c.trade_name && c.trade_name.toLowerCase().includes(term)) ||
    (c.cpf_cnpj && c.cpf_cnpj.includes(term))
  );
});

// Cliente selecionado no formulário
const selectedCustomer = computed(() => {
  if (!form.value.customer_id) return null;
  return customersList.value.find(c => c.id === form.value.customer_id) || null;
});

// Cliente do filtro ativo da página
const currentFilterCustomer = computed(() => {
  if (!filterCustomerId.value) return null;
  return customersList.value.find(c => c.id === filterCustomerId.value) || null;
});

// Carregar veículos da API
const fetchVehicles = async (page = 1) => {
  loading.value = true;
  error.value = '';
  try {
    const params = {
      search: search.value,
      status: filterStatus.value,
      type: filterType.value,
      page
    };
    if (filterCustomerId.value) {
      params.customer_id = filterCustomerId.value;
    }
    const response = await api.get('/vehicles', { params });
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

// Limpar filtro de cliente
const clearCustomerFilter = () => {
  filterCustomerId.value = null;
  router.replace({ query: { ...route.query, customer_id: undefined } });
  fetchVehicles(1);
};

// Estatísticas simples da frota
const stats = computed(() => {
  const total = pagination.value.total || vehicles.value.length || 0;
  let activeCount = 0;
  let maintenanceCount = 0;
  let inactiveCount = 0;

  vehicles.value.forEach(v => {
    if (v.status === 'active') activeCount++;
    else if (v.status === 'maintenance') maintenanceCount++;
    else inactiveCount++;
  });

  const scale = total > 0 && vehicles.value.length > 0 ? total / vehicles.value.length : 1;
  return {
    total,
    active: total > 0 ? Math.round(activeCount * scale) : 0,
    maintenance: total > 0 ? Math.round(maintenanceCount * scale) : 0,
    inactive: total > 0 ? Math.round(inactiveCount * scale) : 0
  };
});

// Formatação e máscara da Placa
const handlePlateInput = (e) => {
  let val = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
  if (val.length > 7) val = val.substring(0, 7);
  form.value.plate = val;
};

// Validação dos passos do Wizard
const validateCurrentStep = () => {
  formError.value = '';
  if (currentStep.value === 1) {
    if (!form.value.plate || form.value.plate.trim().length < 7) {
      formError.value = 'Informe uma placa válida com 7 caracteres (padrão Mercosul ou cinza).';
      return false;
    }
  }
  if (currentStep.value === 2) {
    if (!form.value.brand || !form.value.brand.trim()) {
      formError.value = 'Informe a marca ou fabricante do veículo (ex: Scania, Volvo, Mercedes-Benz).';
      return false;
    }
    if (!form.value.model || !form.value.model.trim()) {
      formError.value = 'Informe o modelo do veículo (ex: FH 540, Actros 2651, R 450).';
      return false;
    }
    if (!form.value.year || form.value.year < 1950) {
      formError.value = 'Informe um ano de fabricação válido.';
      return false;
    }
  }
  return true;
};

// Navegação entre passos
const goToStep = (step) => {
  if (step < currentStep.value || isEditing.value || step <= maxVisitedStep.value) {
    currentStep.value = step;
  }
};

const nextStep = () => {
  if (!validateCurrentStep()) return;
  if (currentStep.value < totalSteps) {
    currentStep.value++;
    if (currentStep.value > maxVisitedStep.value) {
      maxVisitedStep.value = currentStep.value;
    }
  }
};

const prevStep = () => {
  formError.value = '';
  if (currentStep.value > 1) {
    currentStep.value--;
  }
};

// Submissão do formulário
const handleSubmit = async () => {
  if (!validateCurrentStep()) return;
  submitLoading.value = true;
  formError.value = '';
  try {
    const payload = { ...form.value };
    payload.plate = payload.plate.toUpperCase().trim();
    if (payload.vin) payload.vin = payload.vin.toUpperCase().trim();
    if (payload.renavam) payload.renavam = payload.renavam.trim();
    if (payload.capacity_tons === '' || payload.capacity_tons === null) {
      payload.capacity_tons = null;
    } else {
      payload.capacity_tons = Number(payload.capacity_tons);
    }
    if (payload.odometer === '' || payload.odometer === null) {
      payload.odometer = null;
    } else {
      payload.odometer = parseInt(payload.odometer, 10);
    }
    if (!payload.license_expiration) {
      payload.license_expiration = null;
    }

    if (isEditing.value) {
      await api.put(`/vehicles/${editingId.value}`, payload);
    } else {
      await api.post('/vehicles', payload);
    }
    closeModal();
    fetchVehicles(pagination.value.current_page || 1);
  } catch (err) {
    const msg = err.response?.data?.message || 'Erro ao salvar veículo. Verifique se a placa já não está cadastrada.';
    formError.value = msg;
  } finally {
    submitLoading.value = false;
  }
};

// Editar veículo
const handleEdit = (vehicle) => {
  form.value = {
    customer_id: vehicle.customer_id || null,
    plate: vehicle.plate || '',
    fleet_number: vehicle.fleet_number || '',
    model: vehicle.model || '',
    brand: vehicle.brand || '',
    year: vehicle.year || new Date().getFullYear(),
    type: vehicle.type || 'truck',
    vin: vehicle.vin || '',
    color: vehicle.color || '',
    fuel_type: vehicle.fuel_type || 'Diesel S10',
    engine_type: vehicle.engine_type || '',
    axles: vehicle.axles || '6x2 (Trucado)',
    body_type: vehicle.body_type || 'Cavalo Mecânico',
    capacity_tons: vehicle.capacity_tons || '',
    renavam: vehicle.renavam || '',
    license_expiration: vehicle.license_expiration ? vehicle.license_expiration.split('T')[0] : '',
    status: vehicle.status || 'active',
    odometer: vehicle.odometer || ''
  };
  isEditing.value = true;
  editingId.value = vehicle.id;
  currentStep.value = 1;
  maxVisitedStep.value = totalSteps;
  showModal.value = true;
};

// Excluir veículo
const handleDelete = async (id) => {
  if (confirm('Tem certeza que deseja excluir este veículo da frota?')) {
    try {
      await api.delete(`/vehicles/${id}`);
      fetchVehicles(pagination.value.current_page || 1);
    } catch (err) {
      alert('Erro ao excluir veículo. Verifique se ele possui ordens de serviço ativas.');
    }
  }
};

// Monitoramento de filtros
watch([search, filterStatus, filterType, filterCustomerId], () => {
  fetchVehicles(1);
});

// Inicialização
onMounted(() => {
  if (route.query.customer_id) {
    filterCustomerId.value = Number(route.query.customer_id);
  }
  if (route.query.search) {
    search.value = String(route.query.search);
  }
  fetchCustomersList();
  fetchVehicles(1);
  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});

// Helper para label de tipo
const getTypeLabel = (type) => {
  const types = {
    truck: 'Caminhão',
    van: 'Van / Utilitário',
    car: 'Carro de Passeio',
    motorcycle: 'Motocicleta',
    trailer: 'Carreta / Implemento'
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
          <h1 class="h3 fw-bold text-slate-900 mb-0 font-headline">Gestão de Veículos & Frota</h1>
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

      <!-- Alerta Informativo de Filtro por Cliente Ativo -->
      <div v-if="filterCustomerId && currentFilterCustomer" class="alert alert-primary bg-primary-soft border-primary border-opacity-25 rounded-xl d-flex align-items-center justify-content-between p-3 mb-4">
        <div class="d-flex align-items-center gap-2.5">
          <i class="bi bi-filter-circle-fill fs-5 text-primary"></i>
          <div>
            <span class="text-muted small d-block">Exibindo exclusivamente a frota de:</span>
            <strong class="text-slate-900 fs-6">{{ currentFilterCustomer.name }}</strong>
            <span v-if="currentFilterCustomer.trade_name" class="text-muted small ms-1">({{ currentFilterCustomer.trade_name }})</span>
            <span class="badge bg-light text-slate-700 border ms-2 font-monospace" style="font-size: 0.72rem;">
              {{ currentFilterCustomer.type === 'company' ? 'Pessoa Jurídica' : 'Pessoa Física' }}
            </span>
          </div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button @click="openCreateModal(filterCustomerId)" class="btn btn-sm btn-primary rounded-lg fw-semibold d-flex align-items-center gap-1">
            <i class="bi bi-plus-lg"></i> Novo Veículo para esta Frota
          </button>
          <button @click="clearCustomerFilter" class="btn btn-sm btn-outline-secondary rounded-lg fw-semibold d-flex align-items-center gap-1">
            <i class="bi bi-x-lg"></i> Limpar Filtro
          </button>
        </div>
      </div>

      <!-- Grid de Estatísticas -->
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
                  <p class="text-muted small fw-semibold mb-1">Em Operação / Prontos</p>
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
                  <p class="text-muted small fw-semibold mb-1">Em Manutenção / Box</p>
                  <h3 class="fw-bold text-warning font-headline mb-0">{{ stats.maintenance }}</h3>
                </div>
                <div class="icon-circle bg-warning-soft text-warning">
                  <i class="bi bi-wrench-adjustable fs-4"></i>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Fora de Operação -->
        <div class="col-md-3 mb-3">
          <div class="card border-slate-200 shadow-xs rounded-xl overflow-hidden position-relative group cursor-pointer">
            <div class="vertical-bar bg-secondary"></div>
            <div class="card-body p-4 ps-5">
              <div class="d-flex align-items-center justify-content-between">
                <div>
                  <p class="text-muted small fw-semibold mb-1">Inativos / Garagem</p>
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
          <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1" style="max-width: 900px;">
            <!-- Busca Geral -->
            <div class="input-group flex-grow-1" style="min-width: 260px; max-width: 380px;">
              <span class="input-group-text bg-white border-end-0 text-muted">
                <i class="bi bi-search"></i>
              </span>
              <input
                v-model="search"
                type="text"
                class="form-control border-start-0 ps-0 rounded-end-lg"
                placeholder="Placa, modelo, frota # ou cliente..."
              />
            </div>

            <!-- Filtro de Tipo -->
            <select v-model="filterType" class="form-select rounded-lg" style="width: auto; min-width: 160px;">
              <option value="">Todos os tipos</option>
              <option value="truck">Caminhões</option>
              <option value="trailer">Carretas / Reboques</option>
              <option value="van">Vans / Utilitários</option>
              <option value="car">Carros</option>
              <option value="motorcycle">Motos</option>
            </select>

            <!-- Filtro de Status -->
            <select v-model="filterStatus" class="form-select rounded-lg" style="width: auto; min-width: 160px;">
              <option value="">Todos os status</option>
              <option value="active">Ativo / Operação</option>
              <option value="maintenance">Em Manutenção</option>
              <option value="inactive">Inativo</option>
            </select>

            <!-- Filtro por Cliente (Select rápido) -->
            <select v-model="filterCustomerId" class="form-select rounded-lg" style="width: auto; min-width: 190px;">
              <option :value="null">Todos os clientes</option>
              <option v-for="c in customersList" :key="c.id" :value="c.id">
                {{ c.name }} {{ c.trade_name ? `(${c.trade_name})` : '' }}
              </option>
            </select>
          </div>

          <button
            @click="openCreateModal()"
            class="btn btn-primary fw-semibold rounded-lg d-flex align-items-center gap-2 shadow-xs"
          >
            <i class="bi bi-plus-lg"></i>
            Novo Veículo
          </button>
        </div>
      </div>

      <!-- Modal Flutuante de Cadastro / Edição de Veículo (Prime ERP Pattern) -->
      <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
          <div class="modal-content bg-white shadow-lg border-0 rounded-xl overflow-hidden">
            
            <!-- Cabeçalho do Modal -->
            <div class="modal-header bg-white px-4 py-3 border-bottom d-flex justify-content-between align-items-center">
              <div class="d-flex align-items-center gap-3">
                <div class="modal-icon-badge bg-primary-soft text-primary rounded-circle d-flex align-items-center justify-content-center">
                  <i class="bi" :class="isEditing ? 'bi-pencil-square' : 'bi-truck'"></i>
                </div>
                <div>
                  <h5 class="fw-bold text-slate-900 mb-0 font-headline d-flex align-items-center gap-2">
                    <span>{{ isEditing ? 'Editar Registro de Veículo' : 'Assistente de Cadastro de Veículo & Frota' }}</span>
                    <span v-if="isEditing" class="badge bg-light text-slate-700 border font-monospace px-2 py-0.5" style="font-size: 0.75rem;">
                      {{ form.plate }}
                    </span>
                  </h5>
                  <small class="text-muted">
                    {{ isEditing ? 'Atualize as especificações e vínculo de propriedade do veículo' : 'Cadastre veículos vinculando-os ao cliente autônomo ou empresa de transporte' }}
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

            <!-- Stepper Wizard -->
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
                    <i v-else class="bi bi-person-badge-fill"></i>
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
                    <i v-else class="bi bi-truck-flatbed"></i>
                  </div>
                  <span class="step-label mt-1 small fw-semibold">2. Ficha Técnica</span>
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
                    <i v-else class="bi bi-speedometer2"></i>
                  </div>
                  <span class="step-label mt-1 small fw-semibold">3. Odômetro & Docs</span>
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

            <!-- Corpo do Formulário -->
            <form @submit.prevent="handleSubmit" class="d-flex flex-column flex-grow-1 overflow-hidden">
              <div class="modal-body bg-white px-4 py-4" style="max-height: calc(85vh - 190px); overflow-y: auto;">
                
                <!-- Alerta de Erro -->
                <div v-if="formError" class="alert alert-danger alert-dismissible fade show rounded-lg py-2 px-3 small mb-3">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  {{ formError }}
                </div>

                <!-- ================= ETAPA 1: CLIENTE & IDENTIFICAÇÃO ================= -->
                <div v-show="currentStep === 1" class="wizard-step-content">
                  <div class="alert alert-primary bg-primary-soft border-primary border-opacity-25 rounded-xl d-flex align-items-start gap-3 p-3 mb-4">
                    <div class="p-2 bg-primary text-white rounded-lg lh-1">
                      <i class="bi bi-person-check-fill fs-5"></i>
                    </div>
                    <div>
                      <h6 class="fw-bold text-slate-900 mb-1 small">Propriedade do Veículo</h6>
                      <p class="mb-0 text-muted small">
                        Vincule este caminhão ou veículo ao cliente proprietário (seja um motorista autônomo ou uma transportadora frotista).
                      </p>
                    </div>
                  </div>

                  <div class="row g-3">
                    <!-- Cliente Proprietário -->
                    <div class="col-12">
                      <label class="form-label small fw-semibold text-slate-700 d-flex justify-content-between align-items-center">
                        <span>Cliente / Transportadora Proprietária</span>
                        <span v-if="selectedCustomer" class="badge bg-success-soft text-success font-monospace" style="font-size: 0.68rem;">
                          {{ selectedCustomer.type === 'company' ? 'Empresa / Frota' : 'Autônomo' }}
                        </span>
                      </label>
                      <div class="row g-2">
                        <div class="col-md-5">
                          <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                            <input
                              v-model="customerSearch"
                              type="text"
                              class="form-control border-start-0 ps-0 rounded-end-lg"
                              placeholder="Filtrar por nome ou CNPJ..."
                            />
                          </div>
                        </div>
                        <div class="col-md-7">
                          <select v-model="form.customer_id" class="form-select rounded-lg">
                            <option :value="null">-- Selecione o Cliente Proprietário --</option>
                            <option v-for="c in filteredCustomers" :key="c.id" :value="c.id">
                              {{ c.name }} {{ c.trade_name ? `(${c.trade_name})` : '' }} - {{ c.cpf_cnpj || 'Sem doc' }}
                            </option>
                          </select>
                        </div>
                      </div>

                      <!-- Card do Cliente Selecionado -->
                      <div v-if="selectedCustomer" class="card bg-light border rounded-xl p-3 mt-2">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 small">
                          <div>
                            <span class="text-muted d-block">Proprietário Confirmado:</span>
                            <strong class="text-slate-900 fs-6">{{ selectedCustomer.name }}</strong>
                            <span v-if="selectedCustomer.trade_name" class="text-muted ms-1">({{ selectedCustomer.trade_name }})</span>
                          </div>
                          <div class="d-flex align-items-center gap-3">
                            <span class="font-monospace text-slate-700">Doc: {{ selectedCustomer.cpf_cnpj || 'N/A' }}</span>
                            <span v-if="selectedCustomer.phone" class="font-monospace text-success">
                              <i class="bi bi-whatsapp me-1"></i>{{ selectedCustomer.phone }}
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Placa -->
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold text-slate-700">
                        Placa do Veículo <span class="text-danger">*</span>
                      </label>
                      <div class="input-group">
                        <span class="input-group-text bg-slate-100 font-monospace fw-bold text-slate-700 border-end-0">BR</span>
                        <input
                          :value="form.plate"
                          @input="handlePlateInput"
                          type="text"
                          class="form-control font-monospace fw-bold text-uppercase rounded-end-lg border-start-0 fs-5 text-slate-900"
                          placeholder="ABC1D23"
                          maxlength="7"
                          required
                        />
                      </div>
                      <small class="text-muted d-block mt-1">Padrão Mercosul ou cinza clássico.</small>
                    </div>

                    <!-- Nº da Frota do Cliente -->
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold text-slate-700">
                        Nº da Frota Interna (Opcional)
                      </label>
                      <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-tag"></i></span>
                        <input
                          v-model="form.fleet_number"
                          type="text"
                          class="form-control rounded-end-lg border-start-0 ps-0 font-monospace"
                          placeholder="Ex: Cavalo 102, Caminhão 04"
                        />
                      </div>
                      <small class="text-muted d-block mt-1">Código interno de identificação do cliente.</small>
                    </div>

                    <!-- Tipo de Veículo -->
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold text-slate-700">Tipo de Veículo</label>
                      <select v-model="form.type" class="form-select rounded-lg">
                        <option value="truck">Caminhão / Cavalo Mecânico</option>
                        <option value="trailer">Carreta / Implemento</option>
                        <option value="van">Van / Utilitário Diesel</option>
                        <option value="car">Carro de Passeio</option>
                        <option value="motorcycle">Motocicleta</option>
                      </select>
                    </div>

                    <!-- Status Operacional -->
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold text-slate-700">Status Operacional</label>
                      <select v-model="form.status" class="form-select rounded-lg">
                        <option value="active">Ativo / Operação Regular</option>
                        <option value="maintenance">Em Manutenção / Box</option>
                        <option value="inactive">Inativo / Desativado</option>
                      </select>
                    </div>

                    <!-- Cor -->
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold text-slate-700">Cor Predominante</label>
                      <input
                        v-model="form.color"
                        type="text"
                        class="form-control rounded-lg"
                        placeholder="Ex: Branco, Prata, Vermelho, Azul"
                      />
                    </div>
                  </div>
                </div>

                <!-- ================= ETAPA 2: FICHA TÉCNICA PESADA ================= -->
                <div v-show="currentStep === 2" class="wizard-step-content">
                  <div class="alert alert-secondary bg-light border rounded-xl d-flex align-items-start gap-3 p-3 mb-4">
                    <div class="p-2 bg-slate-900 text-white rounded-lg lh-1">
                      <i class="bi bi-gear-wide-connected fs-5"></i>
                    </div>
                    <div>
                      <h6 class="fw-bold text-slate-900 mb-1 small">Especificações Técnicas da Linha Pesada</h6>
                      <p class="mb-0 text-muted small">
                        Dados cruciais para orçamento correto de peças, filtros de motor, óleos lubrificantes e capacidade de carga.
                      </p>
                    </div>
                  </div>

                  <div class="row g-3">
                    <!-- Marca / Fabricante -->
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold text-slate-700">
                        Marca / Fabricante <span class="text-danger">*</span>
                      </label>
                      <input
                        v-model="form.brand"
                        list="brandOptions"
                        type="text"
                        class="form-control rounded-lg"
                        required
                        placeholder="Ex: Scania, Volvo, Mercedes-Benz"
                      />
                      <datalist id="brandOptions">
                        <option value="Scania" />
                        <option value="Volvo" />
                        <option value="Mercedes-Benz" />
                        <option value="Volkswagen" />
                        <option value="DAF" />
                        <option value="Iveco" />
                        <option value="Ford" />
                        <option value="Randon" />
                        <option value="Guerra" />
                        <option value="Facchini" />
                      </datalist>
                    </div>

                    <!-- Modelo -->
                    <div class="col-md-5">
                      <label class="form-label small fw-semibold text-slate-700">
                        Modelo do Veículo <span class="text-danger">*</span>
                      </label>
                      <input
                        v-model="form.model"
                        type="text"
                        class="form-control rounded-lg"
                        required
                        placeholder="Ex: FH 540, Actros 2651, R 450, Constellation 24.280"
                      />
                    </div>

                    <!-- Ano Fabricação -->
                    <div class="col-md-3">
                      <label class="form-label small fw-semibold text-slate-700">
                        Ano Fabricação <span class="text-danger">*</span>
                      </label>
                      <input
                        v-model="form.year"
                        type="number"
                        min="1950"
                        :max="new Date().getFullYear() + 1"
                        class="form-control rounded-lg"
                        required
                        placeholder="2022"
                      />
                    </div>

                    <!-- Configuração de Eixos -->
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold text-slate-700">Configuração de Eixos</label>
                      <input
                        v-model="form.axles"
                        list="axleOptions"
                        type="text"
                        class="form-control rounded-lg"
                        placeholder="Ex: 6x2 (Trucado), 6x4 (Traçado)"
                      />
                      <datalist id="axleOptions">
                        <option value="4x2 (Toco)" />
                        <option value="6x2 (Trucado)" />
                        <option value="6x4 (Traçado)" />
                        <option value="8x2 (Bi-truck)" />
                        <option value="8x4" />
                        <option value="Carreta 2 Eixos" />
                        <option value="Carreta 3 Eixos" />
                        <option value="Bitrem (7 Eixos)" />
                        <option value="Rodotrem (9 Eixos)" />
                      </datalist>
                    </div>

                    <!-- Tipo de Carroceria / Implemento -->
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold text-slate-700">Tipo de Carroceria / Implemento</label>
                      <input
                        v-model="form.body_type"
                        list="bodyOptions"
                        type="text"
                        class="form-control rounded-lg"
                        placeholder="Ex: Cavalo Mecânico, Baú Sider, Caçamba"
                      />
                      <datalist id="bodyOptions">
                        <option value="Cavalo Mecânico" />
                        <option value="Baú Carga Seca" />
                        <option value="Baú Sider" />
                        <option value="Baú Frigorífico" />
                        <option value="Caçamba Basculante" />
                        <option value="Graneleiro" />
                        <option value="Tanque" />
                        <option value="Plataforma Guincho" />
                        <option value="Prancha Carrega-Tudo" />
                        <option value="Gaiola Boiadeira" />
                        <option value="Chassi Aberto" />
                      </datalist>
                    </div>

                    <!-- Combustível -->
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold text-slate-700">Combustível</label>
                      <select v-model="form.fuel_type" class="form-select rounded-lg">
                        <option value="Diesel S10">Diesel S10 (Euro 5/6)</option>
                        <option value="Diesel Comum / S500">Diesel Comum (S500)</option>
                        <option value="Arla 32">Arla 32 / Aditivado</option>
                        <option value="Flex (Gasolina/Etanol)">Flex (Gasolina/Etanol)</option>
                        <option value="GNV">GNV</option>
                        <option value="Elétrico / Híbrido">Elétrico / Híbrido</option>
                      </select>
                    </div>

                    <!-- Motorização -->
                    <div class="col-md-5">
                      <label class="form-label small fw-semibold text-slate-700">Motorização / Modelo do Motor</label>
                      <input
                        v-model="form.engine_type"
                        type="text"
                        class="form-control rounded-lg"
                        placeholder="Ex: Volvo D13C, Scania DC13, OM 457 LA, Cummins ISL"
                      />
                    </div>

                    <!-- Capacidade de Carga -->
                    <div class="col-md-3">
                      <label class="form-label small fw-semibold text-slate-700">Capacidade (Toneladas)</label>
                      <input
                        v-model="form.capacity_tons"
                        type="number"
                        step="0.1"
                        min="0"
                        class="form-control rounded-lg"
                        placeholder="Ex: 45.0"
                      />
                    </div>
                  </div>
                </div>

                <!-- ================= ETAPA 3: ODÔMETRO & DOCS ================= -->
                <div v-show="currentStep === 3" class="wizard-step-content">
                  <div class="alert alert-warning bg-warning-soft border-warning border-opacity-25 rounded-xl d-flex align-items-start gap-3 p-3 mb-4">
                    <div class="p-2 bg-warning text-dark rounded-lg lh-1">
                      <i class="bi bi-speedometer2 fs-5"></i>
                    </div>
                    <div>
                      <h6 class="fw-bold text-slate-900 mb-1 small">Controle de Odômetro & Documentação Legal</h6>
                      <p class="mb-0 text-muted small">
                        O odômetro é a base para o plano de manutenção preventiva periódica (troca de óleo, filtros e pastilhas).
                      </p>
                    </div>
                  </div>

                  <div class="row g-3">
                    <!-- Odômetro Atual (KM) -->
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold text-slate-700">
                        Odômetro Atual (Quilometragem KM)
                      </label>
                      <div class="input-group">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-speedometer2"></i></span>
                        <input
                          v-model="form.odometer"
                          type="number"
                          min="0"
                          class="form-control rounded-end-lg border-start-0 ps-0 font-monospace fs-5 text-slate-900 fw-bold"
                          placeholder="Ex: 145000"
                        />
                      </div>
                      <small class="text-muted d-block mt-1">
                        Utilizado para avisos automáticos de revisão preventiva nas ordens de serviço.
                      </small>
                    </div>

                    <!-- Chassi (VIN) -->
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold text-slate-700">Chassi / VIN (17 Dígitos)</label>
                      <input
                        v-model="form.vin"
                        type="text"
                        class="form-control rounded-lg font-monospace text-uppercase"
                        placeholder="Ex: 9BWCA11108P000000"
                        maxlength="17"
                      />
                      <small class="text-muted d-block mt-1">Fundamental para cotação exata de peças no catálogo do fabricante.</small>
                    </div>

                    <!-- RENAVAM -->
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold text-slate-700">Código RENAVAM</label>
                      <input
                        v-model="form.renavam"
                        type="text"
                        class="form-control rounded-lg font-monospace"
                        placeholder="Ex: 12345678901"
                      />
                    </div>

                    <!-- Vencimento do Licenciamento -->
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold text-slate-700">Vencimento do Licenciamento / CRLV</label>
                      <input
                        v-model="form.license_expiration"
                        type="date"
                        class="form-control rounded-lg font-monospace"
                      />
                      <small class="text-muted d-block mt-1">Alerta a oficina sobre documentos a vencer na frota.</small>
                    </div>
                  </div>
                </div>

                <!-- ================= ETAPA 4: REVISÃO & CONFIRMAÇÃO ================= -->
                <div v-show="currentStep === 4" class="wizard-step-content">
                  <div class="alert alert-dark bg-slate-900 text-white rounded-xl d-flex align-items-start gap-3 p-3 mb-4 border-0">
                    <div class="p-2 bg-primary text-white rounded-lg lh-1">
                      <i class="bi bi-check2-circle fs-5"></i>
                    </div>
                    <div>
                      <h6 class="fw-bold text-white mb-1 small">Confira a Ficha Cadastral do Veículo</h6>
                      <p class="mb-0 text-slate-300 small">
                        Revise os dados técnicos e o proprietário vinculado antes de salvar na base da oficina.
                      </p>
                    </div>
                  </div>

                  <!-- Card Síntese -->
                  <div class="card bg-light border rounded-xl p-3 mb-3">
                    <div class="row g-3 small">
                      <div class="col-md-6 border-bottom pb-2">
                        <span class="text-muted d-block">Cliente Proprietário:</span>
                        <div v-if="selectedCustomer">
                          <strong class="text-slate-900 fs-6">{{ selectedCustomer.name }}</strong>
                          <span v-if="selectedCustomer.trade_name" class="text-muted ms-1">({{ selectedCustomer.trade_name }})</span>
                          <span class="badge bg-secondary-soft text-secondary ms-2">{{ selectedCustomer.type === 'company' ? 'PJ' : 'PF' }}</span>
                        </div>
                        <div v-else class="text-warning fst-italic">
                          Nenhum cliente vinculado (avulso)
                        </div>
                      </div>

                      <div class="col-md-6 border-bottom pb-2">
                        <span class="text-muted d-block">Placa & Frota:</span>
                        <span class="font-monospace fw-bold text-slate-900 fs-5">{{ form.plate || '---' }}</span>
                        <span v-if="form.fleet_number" class="badge bg-slate-900 text-white ms-2 font-monospace">
                          Frota #{{ form.fleet_number }}
                        </span>
                      </div>

                      <div class="col-md-6 border-bottom pb-2">
                        <span class="text-muted d-block">Veículo & Marca:</span>
                        <strong class="text-slate-900">{{ form.brand }} {{ form.model }}</strong>
                        <span class="text-muted ms-1">({{ form.year }})</span>
                        <div class="text-muted">{{ getTypeLabel(form.type) }} • {{ form.color || 'Cor não inf.' }}</div>
                      </div>

                      <div class="col-md-6 border-bottom pb-2">
                        <span class="text-muted d-block">Eixos & Carroceria:</span>
                        <span class="text-slate-900 font-semibold">{{ form.axles || 'Não informado' }}</span>
                        <div class="text-muted">{{ form.body_type || 'Carroceria padrão' }}</div>
                      </div>

                      <div class="col-md-6 border-bottom pb-2">
                        <span class="text-muted d-block">Combustível & Motor:</span>
                        <span class="text-slate-900">{{ form.fuel_type }}</span>
                        <span v-if="form.engine_type" class="text-muted ms-1">({{ form.engine_type }})</span>
                        <span v-if="form.capacity_tons" class="text-muted d-block">Capacidade: {{ form.capacity_tons }} Toneladas</span>
                      </div>

                      <div class="col-md-6 border-bottom pb-2">
                        <span class="text-muted d-block">Odômetro Atual:</span>
                        <strong class="font-monospace text-slate-900 fs-6" v-if="form.odometer">
                          {{ Number(form.odometer).toLocaleString('pt-BR') }} KM
                        </strong>
                        <span v-else class="text-muted fst-italic">Não informado</span>
                      </div>

                      <div class="col-12">
                        <span class="text-muted d-block">Chassi & Licenciamento:</span>
                        <span class="font-monospace text-slate-700 me-3" v-if="form.vin">VIN: {{ form.vin }}</span>
                        <span class="font-monospace text-slate-700 me-3" v-if="form.renavam">RENAVAM: {{ form.renavam }}</span>
                        <span class="font-monospace text-slate-700" v-if="form.license_expiration">Venc. CRLV: {{ formatDate(form.license_expiration) }}</span>
                        <span v-if="!form.vin && !form.renavam && !form.license_expiration" class="text-muted fst-italic">Sem dados complementares</span>
                      </div>
                    </div>
                  </div>
                </div>

              </div>

              <!-- Rodapé de Ações do Modal -->
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
                    {{ isEditing ? 'Atualizar Veículo' : 'Concluir e Salvar' }}
                  </button>
                </div>
              </div>
            </form>

          </div>
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
            <div class="mb-3 text-muted">
              <i class="bi bi-truck fs-1 opacity-50"></i>
            </div>
            <h6 class="fw-bold text-slate-700">Nenhum veículo encontrado</h6>
            <p class="text-muted small mb-3">Nenhum veículo corresponde aos filtros selecionados ou cadastrado na frota.</p>
            <button @click="openCreateModal()" class="btn btn-primary btn-sm rounded-lg fw-semibold">
              <i class="bi bi-plus-lg me-1"></i> Cadastrar Primeiro Veículo
            </button>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4 text-slate-700 fw-bold border-bottom-0">Veículo / Tipo</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Placa & Frota</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Cliente Proprietário</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Configuração Técnica</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Odômetro</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Status</th>
                  <th class="text-end px-4 text-slate-700 fw-bold border-bottom-0">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="vehicle in vehicles"
                  :key="vehicle.id"
                  @click="handleEdit(vehicle)"
                  class="table-row-compact cursor-pointer vehicle-table-row"
                  title="Clique para editar este veículo"
                >
                  <td class="ps-4">
                    <div class="d-flex align-items-center gap-2.5">
                      <div class="icon-badge bg-slate-100 text-slate-700 px-2.5 py-2 rounded-lg">
                        <i class="bi" :class="getTypeIcon(vehicle.type)"></i>
                      </div>
                      <div>
                        <div class="fw-semibold text-slate-900">{{ vehicle.brand }} {{ vehicle.model }}</div>
                        <small class="text-muted text-uppercase" style="font-size: 0.65rem;">
                          {{ getTypeLabel(vehicle.type) }} • {{ vehicle.year }}
                          <span v-if="vehicle.color"> • {{ vehicle.color }}</span>
                        </small>
                      </div>
                    </div>
                  </td>
                  <td>
                    <div class="font-monospace fw-bold text-slate-900 fs-6">{{ vehicle.plate }}</div>
                    <div v-if="vehicle.fleet_number" class="mt-0.5">
                      <span class="badge bg-slate-100 text-slate-800 border font-monospace" style="font-size: 0.68rem;">
                        <i class="bi bi-tag-fill me-1 text-primary"></i>Frota #{{ vehicle.fleet_number }}
                      </span>
                    </div>
                  </td>
                  <td>
                    <div v-if="vehicle.customer">
                      <div class="fw-semibold text-slate-900 d-flex align-items-center gap-1.5">
                        <i class="bi" :class="vehicle.customer.type === 'company' ? 'bi-building text-primary' : 'bi-person text-secondary'"></i>
                        <span>{{ vehicle.customer.name }}</span>
                      </div>
                      <div v-if="vehicle.customer.trade_name" class="small text-muted font-monospace" style="font-size: 0.72rem;">
                        {{ vehicle.customer.trade_name }}
                      </div>
                      <div class="d-flex align-items-center gap-2 mt-0.5">
                        <span class="badge bg-light text-slate-700 border font-monospace" style="font-size: 0.65rem;">
                          {{ vehicle.customer.type === 'company' ? 'PJ' : 'PF' }}
                        </span>
                        <a
                          v-if="vehicle.customer.phone"
                          :href="`https://wa.me/55${vehicle.customer.phone.replace(/\D/g, '')}`"
                          target="_blank"
                          @click.stop
                          class="text-success small text-decoration-none font-monospace d-inline-flex align-items-center gap-1"
                          style="font-size: 0.7rem;"
                          title="Conversar no WhatsApp"
                        >
                          <i class="bi bi-whatsapp"></i>
                          <span>{{ vehicle.customer.phone }}</span>
                        </a>
                      </div>
                    </div>
                    <div v-else class="text-muted small fst-italic">
                      Sem proprietário vinculado
                    </div>
                  </td>
                  <td class="small text-slate-700">
                    <div v-if="vehicle.axles || vehicle.body_type" class="fw-medium">
                      <span>{{ vehicle.axles || '-' }}</span>
                      <span v-if="vehicle.body_type"> • {{ vehicle.body_type }}</span>
                    </div>
                    <div class="text-muted font-monospace" style="font-size: 0.7rem;" v-if="vehicle.fuel_type || vehicle.engine_type">
                      <i class="bi bi-fuel-pump text-primary me-1"></i>
                      <span>{{ vehicle.fuel_type || 'Diesel' }}</span>
                      <span v-if="vehicle.engine_type"> ({{ vehicle.engine_type }})</span>
                    </div>
                    <div v-if="!vehicle.axles && !vehicle.body_type && !vehicle.fuel_type" class="text-muted fst-italic">
                      Padrão
                    </div>
                  </td>
                  <td class="small font-monospace fw-semibold text-slate-800">
                    <div v-if="vehicle.odometer">
                      <i class="bi bi-speedometer2 text-muted me-1"></i>
                      {{ Number(vehicle.odometer).toLocaleString('pt-BR') }} KM
                    </div>
                    <div v-else class="text-muted fst-italic fw-normal">
                      Não informado
                    </div>
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
                    <button @click.stop="handleEdit(vehicle)" class="btn btn-outline-primary btn-sm me-2 fw-semibold rounded-lg py-1 px-2.5" style="font-size: 0.75rem;">
                      Editar
                    </button>
                    <button @click.stop="handleDelete(vehicle.id)" class="btn btn-outline-danger btn-sm fw-semibold rounded-lg py-1 px-2.5" style="font-size: 0.75rem;">
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
.text-slate-800 {
  color: #1e293b;
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
  height: 52px;
}
.vehicle-table-row:hover {
  background-color: #f8fafc;
}
.cursor-pointer {
  cursor: pointer;
}
.form-control:focus, .form-select:focus {
  border-color: #0f172a;
  box-shadow: 0 0 0 0.2rem rgba(15, 23, 42, 0.15);
}

/* Modal flutuante Prime ERP */
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

/* Stepper Wizard */
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
  background-color: #0d6efd;
  z-index: 2;
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stepper-step {
  position: relative;
  z-index: 3;
  background: transparent;
  cursor: pointer;
}

.step-circle {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background-color: #ffffff;
  border: 2px solid #cbd5e1;
  color: #64748b;
  font-size: 0.9rem;
  transition: all 0.25s ease;
}

.step-label {
  font-size: 0.75rem;
  color: #64748b;
  transition: color 0.25s ease;
}

.stepper-step.active .step-circle {
  border-color: #0d6efd;
  background-color: #0d6efd;
  color: #ffffff;
  box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.2);
}

.stepper-step.active .step-label {
  color: #0d6efd;
  font-weight: 700 !important;
}

.stepper-step.completed .step-circle {
  border-color: #198754;
  background-color: #198754;
  color: #ffffff;
}

.stepper-step.completed .step-label {
  color: #198754;
}

.disabled-step {
  opacity: 0.6;
  cursor: not-allowed;
}

.wizard-step-content {
  animation: fadeInStep 0.2s ease-out;
}

@keyframes fadeInStep {
  from { opacity: 0; transform: translateY(6px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
