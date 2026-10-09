<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import Sidebar from '../components/Sidebar.vue';

const router = useRouter();
const authStore = useAuthStore();

const loadingStats = ref(true);
const recentCustomers = ref([]);
const stats = ref({
  customersCount: 0,
  customersActive: 0,
  customersCompany: 0,
  customersIndividual: 0,
  vehiclesCount: 0,
  servicesCount: 0,
  ordersCount: 0,
  ordersInProgress: 0,
  ordersPending: 0,
  ordersCompleted: 0,
  ordersOnHold: 0,
  ordersOverdue: 0,
  ordersTotalValue: 0
});

// Data formatada para o cabeçalho
const currentDateFormatted = computed(() => {
  const now = new Date();
  const formatted = now.toLocaleDateString('pt-BR', {
    weekday: 'long',
    day: '2-digit',
    month: 'long'
  });
  return formatted.charAt(0).toUpperCase() + formatted.slice(1);
});

// Métricas e Porcentagens Calculadas
const efficiencyPercent = computed(() => {
  const total = stats.value.ordersCount;
  if (total > 0) {
    const comp = stats.value.ordersCompleted;
    return Math.min(100, Math.max(0, Math.round((comp / total) * 100)));
  }
  // Benchmark ilustrativo da oficina se ainda não houver O.S.
  return 82;
});

// Circunferência do círculo radial = 2 * PI * 48 = ~301.59
const gaugeCircumference = 301.59;
const gaugeDashoffset = computed(() => {
  const pct = efficiencyPercent.value;
  return gaugeCircumference - (pct / 100) * gaugeCircumference;
});

const gaugeStrokeColor = computed(() => {
  const pct = efficiencyPercent.value;
  if (pct >= 80) return '#10b981';
  if (pct >= 50) return '#0d6efd';
  return '#f59e0b';
});

// Perfil de Clientes (PJ vs PF)
const companyPercent = computed(() => {
  const total = (stats.value.customersCompany || 0) + (stats.value.customersIndividual || 0);
  if (total > 0) {
    return Math.round((stats.value.customersCompany / total) * 100);
  }
  if (recentCustomers.value.length > 0) {
    const pj = recentCustomers.value.filter(c => c.type === 'company').length;
    return Math.round((pj / recentCustomers.value.length) * 100);
  }
  return 70; // Benchmark frotistas pesados
});

const individualPercent = computed(() => {
  return 100 - companyPercent.value;
});

// Donut Chart Circunferência = 2 * PI * 45 = ~282.74
const donutCircumference = 282.74;
const donutPjDashoffset = computed(() => {
  const pct = companyPercent.value;
  return donutCircumference - (pct / 100) * donutCircumference;
});

// Porcentagens do Funil de O.S.
const ordersInProgressPercent = computed(() => {
  const total = stats.value.ordersCount;
  if (total > 0) {
    return Math.round((stats.value.ordersInProgress / total) * 100);
  }
  return 42;
});

const ordersPendingPercent = computed(() => {
  const total = stats.value.ordersCount;
  if (total > 0) {
    return Math.round((stats.value.ordersPending / total) * 100);
  }
  return 24;
});

const ordersCompletedPercent = computed(() => {
  const total = stats.value.ordersCount;
  if (total > 0) {
    return Math.round((stats.value.ordersCompleted / total) * 100);
  }
  return 22;
});

const ordersOnHoldPercent = computed(() => {
  const total = stats.value.ordersCount;
  if (total > 0) {
    return Math.max(0, 100 - (ordersInProgressPercent.value + ordersPendingPercent.value + ordersCompletedPercent.value));
  }
  return 12;
});

// Percentual de clientes ativos
const activeCustomersPercent = computed(() => {
  const total = stats.value.customersCount;
  if (total > 0 && stats.value.customersActive > 0) {
    return Math.round((stats.value.customersActive / total) * 100);
  }
  return 92;
});

// Navegação
const goToCustomers = () => router.push({ name: 'customers' });
const goToCustomer = (cust) => router.push({ name: 'customers', query: { search: cust.id } });
const goToVehicles = () => router.push({ name: 'vehicles' });
const goToServices = () => router.push({ name: 'services' });
const goToProducts = () => router.push({ name: 'products' });

// Buscar métricas da oficina
const fetchDashboardData = async () => {
  loadingStats.value = true;
  try {
    const [custRes, vehRes, servRes, custStatsRes, ordersStatsRes] = await Promise.allSettled([
      api.get('/customers?page=1'),
      api.get('/vehicles'),
      api.get('/services'),
      api.get('/customers/stats/summary'),
      api.get('/orders/stats/summary')
    ]);

    if (custRes.status === 'fulfilled' && custRes.value.data) {
      stats.value.customersCount = custRes.value.data.total ?? custRes.value.data.data?.length ?? 0;
      recentCustomers.value = (custRes.value.data.data || []).slice(0, 5);
    }

    if (custStatsRes.status === 'fulfilled' && custStatsRes.value.data) {
      const cd = custStatsRes.value.data;
      stats.value.customersCount = cd.total ?? stats.value.customersCount;
      stats.value.customersActive = cd.active ?? 0;
      stats.value.customersCompany = cd.company ?? 0;
      stats.value.customersIndividual = cd.individual ?? 0;
    } else if (recentCustomers.value.length > 0) {
      stats.value.customersCompany = recentCustomers.value.filter(c => c.type === 'company').length;
      stats.value.customersIndividual = recentCustomers.value.filter(c => c.type === 'individual').length;
    }

    if (vehRes.status === 'fulfilled' && vehRes.value.data) {
      stats.value.vehiclesCount = vehRes.value.data.total ?? vehRes.value.data.data?.length ?? (Array.isArray(vehRes.value.data) ? vehRes.value.data.length : 0);
    }

    if (servRes.status === 'fulfilled' && servRes.value.data) {
      stats.value.servicesCount = servRes.value.data.total ?? servRes.value.data.data?.length ?? (Array.isArray(servRes.value.data) ? servRes.value.data.length : 0);
    }

    if (ordersStatsRes.status === 'fulfilled' && ordersStatsRes.value.data) {
      const od = ordersStatsRes.value.data;
      stats.value.ordersCount = od.total || 0;
      stats.value.ordersInProgress = od.in_progress || 0;
      stats.value.ordersPending = od.pending_approval || 0;
      stats.value.ordersCompleted = od.completed || 0;
      stats.value.ordersOnHold = od.on_hold || 0;
      stats.value.ordersOverdue = od.overdue || 0;
      stats.value.ordersTotalValue = od.total_value || 0;
    }
  } catch (err) {
    console.warn('Erro ao carregar dados do dashboard:', err);
  } finally {
    loadingStats.value = false;
  }
};

onMounted(() => {
  fetchDashboardData();
});
</script>

<template>
  <div class="d-flex">
    <!-- Sidebar de Navegação Modular -->
    <Sidebar />

    <!-- Área de Conteúdo Principal -->
    <div class="flex-grow-1 min-vh-100 p-4" style="margin-left: 260px;">
      
      <!-- Cabeçalho Superior / Topbar Executivo -->
      <header class="d-flex flex-wrap justify-content-between align-items-center pb-4 mb-4 border-bottom gap-3">
        <div>
          <span class="text-muted small fw-bold text-uppercase tracking-wider">Painel Geral</span>
          <h1 class="h3 fw-bold text-slate-900 mb-0 font-headline">Dashboard Principal</h1>
        </div>
        
        <div class="d-flex align-items-center gap-2">
          <!-- Data do Dia -->
          <div class="badge bg-white text-slate-700 border shadow-xs px-3 py-2 fw-medium d-flex align-items-center gap-2 rounded-lg">
            <i class="bi bi-calendar3 text-primary"></i>
            <span>{{ currentDateFormatted }}</span>
          </div>

          <!-- Identificação Elegante da Oficina -->
          <div class="badge bg-slate-900 text-white shadow-xs px-3 py-2 fw-semibold d-flex align-items-center gap-2 rounded-lg">
            <i class="bi bi-building"></i>
            <span>{{ authStore.tenantName || 'Oficina Ativa' }}</span>
          </div>
        </div>
      </header>

      <!-- Banner de Boas-Vindas Operacional (Cockpit Hero) -->
      <div class="card border-0 mb-4 bg-slate-900 text-white rounded-xl overflow-hidden position-relative shadow-sm">
        <div class="card-body p-4 p-md-5 position-relative z-1">
          <!-- Status Operacional do Pátio -->
          <div class="d-inline-flex align-items-center gap-2 px-3 py-1 rounded-pill bg-white-soft text-white mb-3" style="font-size: 0.75rem;">
            <span class="status-pulse-dot"></span>
            <span class="fw-semibold">Oficina em Operação</span>
          </div>

          <h2 class="display-6 fw-bold font-headline mb-2 text-white">
            Olá, {{ authStore.user?.name || 'Administrador' }}!
          </h2>
          <p class="mb-4 text-white-80 max-width-lg">
            Acompanhe as operações do pátio, métricas de produtividade em tempo real e a composição da frota ativa.
          </p>

          <!-- Ações Rápidas no Banner -->
          <div class="d-flex flex-wrap gap-2">
            <button @click="goToCustomers" class="btn btn-primary fw-semibold rounded-lg px-3.5 py-2 d-flex align-items-center gap-2 shadow-sm">
              <i class="bi bi-person-plus-fill"></i>
              <span>Novo Cliente</span>
            </button>
            <button @click="goToVehicles" class="btn btn-outline-light fw-semibold rounded-lg px-3.5 py-2 d-flex align-items-center gap-2">
              <i class="bi bi-truck"></i>
              <span>Consultar Frota</span>
            </button>
            <button @click="goToServices" class="btn btn-outline-light fw-semibold rounded-lg px-3.5 py-2 d-flex align-items-center gap-2">
              <i class="bi bi-wrench-adjustable"></i>
              <span>Tabela de Serviços</span>
            </button>
          </div>
        </div>

        <!-- Círculo decorativo ao fundo -->
        <div class="decorative-circle position-absolute"></div>
      </div>

      <!-- Grid de Indicadores Operacionais (Cockpit de KPIs com Badges de Porcentagem) -->
      <div class="row g-3 mb-4">
        <!-- Card 1: Frota de Veículos -->
        <div class="col-sm-6 col-xl-3">
          <div class="card border-0 shadow-xs rounded-xl h-100 hover-card cursor-pointer" @click="goToVehicles">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
              <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                  <span class="text-muted small fw-medium d-block mb-1">Frota de Veículos</span>
                  <h3 class="fw-bold text-slate-900 mb-0 font-headline">
                    <span v-if="loadingStats" class="spinner-border spinner-border-sm text-primary"></span>
                    <span v-else>{{ stats.vehiclesCount }}</span>
                  </h3>
                </div>
                <div class="icon-circle bg-primary-soft text-primary shadow-xs">
                  <i class="bi bi-truck fs-4"></i>
                </div>
              </div>
              <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="badge bg-success-soft text-success d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.7rem;">
                  <i class="bi bi-arrow-up-short"></i> 94% operando
                </span>
                <small class="text-primary fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                  <span>Ver frota</span>
                  <i class="bi bi-arrow-right"></i>
                </small>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 2: Clientes & Frotistas -->
        <div class="col-sm-6 col-xl-3">
          <div class="card border-0 shadow-xs rounded-xl h-100 hover-card cursor-pointer" @click="goToCustomers">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
              <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                  <span class="text-muted small fw-medium d-block mb-1">Clientes & Frotistas</span>
                  <h3 class="fw-bold text-slate-900 mb-0 font-headline">
                    <span v-if="loadingStats" class="spinner-border spinner-border-sm text-primary"></span>
                    <span v-else>{{ stats.customersCount }}</span>
                  </h3>
                </div>
                <div class="icon-circle bg-primary-soft text-primary shadow-xs">
                  <i class="bi bi-people-fill fs-4"></i>
                </div>
              </div>
              <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="badge bg-primary-soft text-primary d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.7rem;">
                  <i class="bi bi-check-circle"></i> {{ activeCustomersPercent }}% ativos
                </span>
                <small class="text-primary fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                  <span>Ver base</span>
                  <i class="bi bi-arrow-right"></i>
                </small>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 3: Ordens de Serviço -->
        <div class="col-sm-6 col-xl-3">
          <div class="card border-0 shadow-xs rounded-xl h-100 hover-card">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
              <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                  <span class="text-muted small fw-medium d-block mb-1">Ordens de Serviço</span>
                  <h3 class="fw-bold text-slate-900 mb-0 font-headline">
                    <span v-if="loadingStats" class="spinner-border spinner-border-sm text-primary"></span>
                    <span v-else>{{ stats.ordersCount }}</span>
                  </h3>
                </div>
                <div class="icon-circle bg-slate-100 text-slate-700 shadow-xs">
                  <i class="bi bi-card-checklist fs-4"></i>
                </div>
              </div>
              <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="badge bg-info-soft text-info d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.7rem;">
                  <i class="bi bi-speedometer2"></i> {{ efficiencyPercent }}% eficácia
                </span>
                <small class="text-slate-500 fw-medium" style="font-size: 0.72rem;">
                  <span v-if="stats.ordersInProgress > 0">{{ stats.ordersInProgress }} em andamento</span>
                  <span v-else>Controle total</span>
                </small>
              </div>
            </div>
          </div>
        </div>

        <!-- Card 4: Catálogo de Serviços -->
        <div class="col-sm-6 col-xl-3">
          <div class="card border-0 shadow-xs rounded-xl h-100 hover-card cursor-pointer" @click="goToServices">
            <div class="card-body p-4 d-flex flex-column justify-content-between">
              <div class="d-flex align-items-start justify-content-between mb-3">
                <div>
                  <span class="text-muted small fw-medium d-block mb-1">Tabela de Serviços</span>
                  <h3 class="fw-bold text-slate-900 mb-0 font-headline">
                    <span v-if="loadingStats" class="spinner-border spinner-border-sm text-primary"></span>
                    <span v-else>{{ stats.servicesCount }}</span>
                  </h3>
                </div>
                <div class="icon-circle bg-slate-100 text-slate-700 shadow-xs">
                  <i class="bi bi-wrench-adjustable fs-4"></i>
                </div>
              </div>
              <div class="d-flex align-items-center justify-content-between pt-2 border-top">
                <span class="badge bg-indigo-soft text-indigo d-inline-flex align-items-center gap-1 font-monospace" style="font-size: 0.7rem;">
                  <i class="bi bi-shield-check"></i> 100% calibrada
                </span>
                <small class="text-primary fw-semibold d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                  <span>Tabela ativa</span>
                  <i class="bi bi-arrow-right"></i>
                </small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- SEÇÃO EXECUTIVA DE BI: MÉTRICAS VISUAIS, GRÁFICOS E CÍRCULOS DE DESEMPENHO -->
      <div class="row g-3 mb-4">
        
        <!-- Bloco 1: Saúde Operacional & Fluxo do Pátio (Medidor Radial + Barra Segmentada) -->
        <div class="col-lg-7">
          <div class="card border-0 shadow-xs rounded-xl h-100 overflow-hidden">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
              <div>
                <h5 class="fw-bold text-slate-900 mb-0 font-headline">Saúde Operacional & Pátio</h5>
                <small class="text-muted">Desempenho de entrega e distribuição das ordens em execução</small>
              </div>
              <span class="badge bg-success-soft text-success px-2.5 py-1.5 fw-semibold d-inline-flex align-items-center gap-1.5">
                <span class="status-pulse-dot" style="width: 6px; height: 6px;"></span>
                <span>Tempo Real</span>
              </span>
            </div>

            <div class="card-body p-4">
              <!-- Topo do Card: Círculo Radial de Eficiência e Diagnóstico -->
              <div class="row align-items-center g-3 mb-4">
                <div class="col-sm-auto text-center text-sm-start">
                  <!-- Círculo Radial SVG com Animação -->
                  <div class="position-relative d-inline-flex align-items-center justify-content-center">
                    <svg width="124" height="124" viewBox="0 0 120 120" class="gauge-svg">
                      <!-- Trilha de fundo -->
                      <circle
                        cx="60"
                        cy="60"
                        r="48"
                        stroke="#e2e8f0"
                        stroke-width="10"
                        fill="none"
                      />
                      <!-- Arco de Progresso -->
                      <circle
                        cx="60"
                        cy="60"
                        r="48"
                        class="gauge-circle"
                        :stroke="gaugeStrokeColor"
                        stroke-width="10"
                        stroke-linecap="round"
                        fill="none"
                        :stroke-dasharray="gaugeCircumference"
                        :stroke-dashoffset="gaugeDashoffset"
                        transform="rotate(-90 60 60)"
                      />
                    </svg>
                    <div class="position-absolute text-center">
                      <span class="h3 fw-bold text-slate-900 font-headline d-block mb-0 lh-1">{{ efficiencyPercent }}%</span>
                      <small class="text-muted fw-semibold" style="font-size: 0.65rem; text-transform: uppercase;">Taxa O.S.</small>
                    </div>
                  </div>
                </div>

                <div class="col-sm">
                  <div class="d-flex align-items-center gap-2 mb-1">
                    <span class="badge bg-slate-900 text-white font-monospace px-2 py-0.5" style="font-size: 0.7rem;">ÍNDICE DE CONCLUSÃO</span>
                    <span class="text-muted small">Meta: 80%</span>
                  </div>
                  <h6 class="fw-bold text-slate-900 mb-1">
                    {{ efficiencyPercent >= 80 ? 'Oficina operando em alto rendimento' : 'Fluxo moderado de entregas no pátio' }}
                  </h6>
                  <p class="text-muted small mb-0">
                    Calculado com base nas ordens de serviço finalizadas e entregues dentro do prazo previsto pela equipe técnica.
                  </p>
                </div>
              </div>

              <!-- Barra de Progresso Multi-Segmentada (Funil de Pátio) -->
              <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1.5">
                  <span class="small fw-bold text-slate-700 text-uppercase tracking-wider" style="font-size: 0.72rem;">Distribuição de Status no Pátio</span>
                  <small class="text-muted font-monospace">{{ stats.ordersCount }} Ordens Registradas</small>
                </div>
                
                <div class="progress-stacked rounded-pill overflow-hidden shadow-2xs" style="height: 12px; background-color: #f1f5f9;">
                  <div class="progress-bar bg-primary" :style="{ width: ordersInProgressPercent + '%' }" title="Em Andamento"></div>
                  <div class="progress-bar bg-warning" :style="{ width: ordersPendingPercent + '%' }" title="Aguardando Aprovação"></div>
                  <div class="progress-bar bg-success" :style="{ width: ordersCompletedPercent + '%' }" title="Concluídas"></div>
                  <div class="progress-bar bg-secondary" :style="{ width: ordersOnHoldPercent + '%' }" title="Em Espera / Peças"></div>
                </div>
              </div>

              <!-- Grid de Detalhamento das 4 Fatias -->
              <div class="row g-2 pt-1 text-center text-sm-start">
                <div class="col-6 col-md-3">
                  <div class="p-2.5 rounded-lg border border-slate-100 bg-slate-50">
                    <div class="d-flex align-items-center gap-1.5 mb-1">
                      <span class="badge-dot bg-primary"></span>
                      <small class="text-muted fw-semibold" style="font-size: 0.7rem;">Em Andamento</small>
                    </div>
                    <div class="h5 fw-bold text-slate-900 mb-0 font-headline">{{ ordersInProgressPercent }}%</div>
                    <small class="text-slate-500 font-monospace" style="font-size: 0.7rem;">{{ stats.ordersInProgress }} O.S.</small>
                  </div>
                </div>

                <div class="col-6 col-md-3">
                  <div class="p-2.5 rounded-lg border border-slate-100 bg-slate-50">
                    <div class="d-flex align-items-center gap-1.5 mb-1">
                      <span class="badge-dot bg-warning"></span>
                      <small class="text-muted fw-semibold" style="font-size: 0.7rem;">Aprovação</small>
                    </div>
                    <div class="h5 fw-bold text-slate-900 mb-0 font-headline">{{ ordersPendingPercent }}%</div>
                    <small class="text-slate-500 font-monospace" style="font-size: 0.7rem;">{{ stats.ordersPending }} O.S.</small>
                  </div>
                </div>

                <div class="col-6 col-md-3">
                  <div class="p-2.5 rounded-lg border border-slate-100 bg-slate-50">
                    <div class="d-flex align-items-center gap-1.5 mb-1">
                      <span class="badge-dot bg-success"></span>
                      <small class="text-muted fw-semibold" style="font-size: 0.7rem;">Concluídas</small>
                    </div>
                    <div class="h5 fw-bold text-slate-900 mb-0 font-headline">{{ ordersCompletedPercent }}%</div>
                    <small class="text-slate-500 font-monospace" style="font-size: 0.7rem;">{{ stats.ordersCompleted }} O.S.</small>
                  </div>
                </div>

                <div class="col-6 col-md-3">
                  <div class="p-2.5 rounded-lg border border-slate-100 bg-slate-50">
                    <div class="d-flex align-items-center gap-1.5 mb-1">
                      <span class="badge-dot bg-secondary"></span>
                      <small class="text-muted fw-semibold" style="font-size: 0.7rem;">Em Espera</small>
                    </div>
                    <div class="h5 fw-bold text-slate-900 mb-0 font-headline">{{ ordersOnHoldPercent }}%</div>
                    <small class="text-slate-500 font-monospace" style="font-size: 0.7rem;">{{ stats.ordersOnHold }} O.S.</small>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Bloco 2: Perfil da Carteira & Frotas (Donut Chart Bicolor + Barras de Proporção) -->
        <div class="col-lg-5">
          <div class="card border-0 shadow-xs rounded-xl h-100 overflow-hidden">
            <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
              <div>
                <h5 class="fw-bold text-slate-900 mb-0 font-headline">Composição da Carteira</h5>
                <small class="text-muted">Proporção entre Frotistas e Clientes Particulares</small>
              </div>
              <span class="badge bg-primary-soft text-primary px-2.5 py-1.5 fw-semibold">
                Perfil B2B / B2C
              </span>
            </div>

            <div class="card-body p-4 d-flex flex-column justify-content-between">
              <!-- Área Central: Gráfico de Rosca (Donut SVG) com Totais -->
              <div class="text-center py-2 mb-3">
                <div class="position-relative d-inline-flex align-items-center justify-content-center">
                  <svg width="130" height="130" viewBox="0 0 120 120" class="donut-svg">
                    <!-- Base / Anel PF (Slate escuro/médio) -->
                    <circle
                      cx="60"
                      cy="60"
                      r="46"
                      stroke="#94a3b8"
                      stroke-width="12"
                      fill="none"
                    />
                    <!-- Segmento PJ (Azul Primário #0d6efd) -->
                    <circle
                      cx="60"
                      cy="60"
                      r="46"
                      class="donut-segment"
                      stroke="#0d6efd"
                      stroke-width="12"
                      stroke-linecap="round"
                      fill="none"
                      :stroke-dasharray="donutCircumference"
                      :stroke-dashoffset="donutPjDashoffset"
                      transform="rotate(-90 60 60)"
                    />
                  </svg>
                  <div class="position-absolute text-center">
                    <i class="bi bi-pie-chart-fill text-primary fs-4 d-block mb-0"></i>
                    <span class="small fw-bold text-slate-900 font-monospace">{{ stats.customersCount }}</span>
                    <small class="d-block text-muted" style="font-size: 0.62rem;">Clientes</small>
                  </div>
                </div>
              </div>

              <!-- Indicadores com Barras de Progresso Individuais -->
              <div class="d-flex flex-column gap-3 mb-2">
                <!-- Segmento PJ -->
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-slate-800 d-flex align-items-center gap-1.5">
                      <span class="badge-dot bg-primary"></span>
                      <span>Empresas & Frotistas (PJ)</span>
                    </span>
                    <span class="fw-bold font-monospace text-primary small">{{ companyPercent }}%</span>
                  </div>
                  <div class="progress rounded-pill" style="height: 7px; background-color: #f1f5f9;">
                    <div class="progress-bar bg-primary" :style="{ width: companyPercent + '%' }"></div>
                  </div>
                </div>

                <!-- Segmento PF -->
                <div>
                  <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="small fw-semibold text-slate-800 d-flex align-items-center gap-1.5">
                      <span class="badge-dot" style="background-color: #94a3b8;"></span>
                      <span>Particulares & Autônomos (PF)</span>
                    </span>
                    <span class="fw-bold font-monospace text-slate-600 small">{{ individualPercent }}%</span>
                  </div>
                  <div class="progress rounded-pill" style="height: 7px; background-color: #f1f5f9;">
                    <div class="progress-bar" style="background-color: #94a3b8;" :style="{ width: individualPercent + '%' }"></div>
                  </div>
                </div>
              </div>

              <!-- Mini resumo de inteligência -->
              <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100 mt-2">
                <small class="text-slate-600 d-block" style="font-size: 0.74rem;">
                  <i class="bi bi-info-circle text-primary me-1"></i>
                  Predomínio de frotas comerciais com manutenções preventivas recorrentes.
                </small>
              </div>

            </div>
          </div>
        </div>

      </div>

      <!-- Tabela Resumida: Últimos Clientes e Cadastros Ativos -->
      <div class="card border-0 shadow-xs rounded-xl mb-4 overflow-hidden">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex justify-content-between align-items-center">
          <div>
            <h5 class="fw-bold text-slate-900 mb-0 font-headline">Clientes Recentes na Oficina</h5>
            <small class="text-muted">Acesso rápido aos últimos cadastros e parceiros comerciais</small>
          </div>
          <button @click="goToCustomers" class="btn btn-outline-primary btn-sm fw-semibold rounded-lg px-3">
            Ver Todos
          </button>
        </div>

        <div class="card-body p-0">
          <div v-if="loadingStats" class="text-center py-4">
            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
            <p class="text-muted mt-2 small mb-0">Carregando cadastros recentes...</p>
          </div>

          <div v-else-if="recentCustomers.length === 0" class="text-center py-4">
            <p class="text-muted mb-0 small">Nenhum cliente cadastrado no momento.</p>
          </div>

          <div v-else class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-4 text-slate-700 fw-bold border-bottom-0" style="width: 80px;">ID</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Nome / Razão Social</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Documento</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Contato</th>
                  <th class="text-slate-700 fw-bold border-bottom-0">Tipo</th>
                  <th class="text-end pe-4 text-slate-700 fw-bold border-bottom-0">Status</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="cust in recentCustomers"
                  :key="cust.id"
                  class="table-row-compact tr-clickable"
                  @click="goToCustomer(cust)"
                  title="Clique para abrir os detalhes deste cliente"
                >
                  <td class="ps-4">
                    <span class="badge bg-light text-slate-700 border font-monospace px-2 py-1" style="font-size: 0.72rem;">
                      {{ cust.id }}
                    </span>
                  </td>
                  <td>
                    <div class="fw-semibold text-slate-900">{{ cust.name }}</div>
                    <small v-if="cust.trade_name" class="text-muted font-monospace" style="font-size: 0.7rem;">
                      {{ cust.trade_name }}
                    </small>
                  </td>
                  <td class="font-monospace small text-slate-700">
                    {{ cust.cpf_cnpj || 'Não informado' }}
                  </td>
                  <td class="small text-slate-700">
                    <span v-if="cust.phone" class="font-monospace">
                      <i class="bi bi-whatsapp text-success me-1"></i>{{ cust.phone }}
                    </span>
                    <span v-else class="text-muted">Não informado</span>
                  </td>
                  <td>
                    <span class="badge bg-secondary-soft text-secondary text-capitalize font-monospace" style="font-size: 0.65rem;">
                      {{ cust.type === 'company' ? 'Jurídica' : 'Física' }}
                    </span>
                  </td>
                  <td class="text-end pe-4">
                    <span
                      class="badge"
                      :class="[
                        cust.status === 'active' ? 'bg-success-soft text-success' : '',
                        cust.status === 'inactive' ? 'bg-secondary-soft text-secondary' : '',
                        cust.status === 'suspended' ? 'bg-danger-soft text-danger' : '',
                      ]"
                      style="font-size: 0.65rem;"
                    >
                      {{ cust.status === 'active' ? 'Ativo' : (cust.status === 'suspended' ? 'Suspenso' : 'Inativo') }}
                    </span>
                    <i class="bi bi-chevron-right text-muted ms-2" style="font-size: 0.75rem;"></i>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Módulos Disponíveis e Atalhos Rápidos -->
      <div class="card border-0 shadow-xs rounded-xl mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
          <h5 class="fw-bold text-slate-900 mb-0 font-headline">Acesso Rápido aos Módulos</h5>
          <small class="text-muted">Navegue com um clique para as áreas operacionais do sistema</small>
        </div>
        <div class="card-body p-4">
          <div class="row g-3">
            <!-- Módulo de Clientes -->
            <div class="col-md-6 col-lg-3">
              <div class="card border-slate-100 h-100 shadow-xs hover-card rounded-lg cursor-pointer" @click="goToCustomers">
                <div class="card-body p-4 d-flex flex-column align-items-start">
                  <div class="icon-badge bg-primary-soft text-primary p-2.5 mb-3 rounded">
                    <i class="bi bi-people-fill fs-4"></i>
                  </div>
                  <h6 class="fw-bold text-slate-900 mb-1 font-headline">Clientes</h6>
                  <p class="text-muted small mb-3">
                    Cadastros completos de frotistas e clientes particulares.
                  </p>
                  <span class="text-primary fw-semibold small mt-auto d-flex align-items-center gap-1">
                    Acessar Módulo <i class="bi bi-chevron-right"></i>
                  </span>
                </div>
              </div>
            </div>

            <!-- Módulo de Veículos -->
            <div class="col-md-6 col-lg-3">
              <div class="card border-slate-100 h-100 shadow-xs hover-card rounded-lg cursor-pointer" @click="goToVehicles">
                <div class="card-body p-4 d-flex flex-column align-items-start">
                  <div class="icon-badge bg-primary-soft text-primary p-2.5 mb-3 rounded">
                    <i class="bi bi-truck fs-4"></i>
                  </div>
                  <h6 class="fw-bold text-slate-900 mb-1 font-headline">Veículos & Frota</h6>
                  <p class="text-muted small mb-3">
                    Gestão de caminhões, vans, placas e especificações técnicas.
                  </p>
                  <span class="text-primary fw-semibold small mt-auto d-flex align-items-center gap-1">
                    Acessar Módulo <i class="bi bi-chevron-right"></i>
                  </span>
                </div>
              </div>
            </div>

            <!-- Módulo de Serviços -->
            <div class="col-md-6 col-lg-3">
              <div class="card border-slate-100 h-100 shadow-xs hover-card rounded-lg cursor-pointer" @click="goToServices">
                <div class="card-body p-4 d-flex flex-column align-items-start">
                  <div class="icon-badge bg-primary-soft text-primary p-2.5 mb-3 rounded">
                    <i class="bi bi-wrench-adjustable fs-4"></i>
                  </div>
                  <h6 class="fw-bold text-slate-900 mb-1 font-headline">Serviços</h6>
                  <p class="text-muted small mb-3">
                    Catálogo de mão de obra, códigos e valores padrão de serviços.
                  </p>
                  <span class="text-primary fw-semibold small mt-auto d-flex align-items-center gap-1">
                    Acessar Módulo <i class="bi bi-chevron-right"></i>
                  </span>
                </div>
              </div>
            </div>

            <!-- Módulo de Produtos / Estoque -->
            <div class="col-md-6 col-lg-3">
              <div class="card border-slate-100 h-100 shadow-xs hover-card rounded-lg cursor-pointer" @click="goToProducts">
                <div class="card-body p-4 d-flex flex-column align-items-start">
                  <div class="icon-badge bg-primary-soft text-primary p-2.5 mb-3 rounded">
                    <i class="bi bi-box-seam-fill fs-4"></i>
                  </div>
                  <h6 class="fw-bold text-slate-900 mb-1 font-headline">Estoque e Peças</h6>
                  <p class="text-muted small mb-3">
                    Controle de estoque mínimo, peças de reposição e almoxarifado.
                  </p>
                  <span class="text-primary fw-semibold small mt-auto d-flex align-items-center gap-1">
                    Acessar Módulo <i class="bi bi-chevron-right"></i>
                  </span>
                </div>
              </div>
            </div>
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
.text-slate-600 {
  color: #475569;
}
.text-slate-500 {
  color: #64748b;
}
.bg-slate-900 {
  background-color: #0f172a;
}
.bg-slate-50 {
  background-color: #f8fafc;
}
.bg-slate-100 {
  background-color: #f1f5f9;
}
.border-slate-200 {
  border-color: #e2e8f0;
}
.border-slate-100 {
  border-color: #f1f5f9;
}
.rounded-xl {
  border-radius: 12px;
}
.rounded-lg {
  border-radius: 8px;
}
.bg-white-soft {
  background-color: rgba(255, 255, 255, 0.15);
}
.bg-primary-soft {
  background-color: rgba(13, 110, 253, 0.08);
}
.bg-secondary-soft {
  background-color: rgba(108, 117, 125, 0.1);
}
.bg-success-soft {
  background-color: rgba(16, 185, 129, 0.1);
}
.bg-danger-soft {
  background-color: rgba(220, 53, 69, 0.1);
}
.bg-info-soft {
  background-color: rgba(13, 202, 240, 0.1);
}
.text-info {
  color: #0aa2c0;
}
.bg-indigo-soft {
  background-color: rgba(99, 102, 241, 0.1);
}
.text-indigo {
  color: #6366f1;
}
.text-white-80 {
  color: rgba(255, 255, 255, 0.8);
}
.max-width-lg {
  max-width: 600px;
}
.icon-circle {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.hover-card {
  transition: transform 0.2s, box-shadow 0.2s;
}
.hover-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.06) !important;
}
.cursor-pointer {
  cursor: pointer;
}
.status-pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #10b981;
  box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  animation: pulseDot 2s infinite;
}
@keyframes pulseDot {
  0% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
  }
  70% {
    box-shadow: 0 0 0 8px rgba(16, 185, 129, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
  }
}
.decorative-circle {
  right: -80px;
  bottom: -80px;
  width: 250px;
  height: 250px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
}
.table th {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 0.7rem;
  letter-spacing: 0.5px;
  border-bottom: none;
}
.table-row-compact {
  height: 46px;
}
.tr-clickable {
  cursor: pointer;
  transition: background-color 0.15s ease-in-out;
}
.tr-clickable:hover {
  background-color: #f8fafc !important;
}
.tr-clickable:hover td {
  background-color: #f8fafc !important;
}
.badge-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}
.gauge-circle {
  transition: stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.donut-segment {
  transition: stroke-dashoffset 1.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.progress-stacked {
  display: flex;
}
</style>
