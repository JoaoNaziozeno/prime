<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import Sidebar from '../components/Sidebar.vue';

const router = useRouter();
const authStore = useAuthStore();

const goToCustomers = () => {
  router.push({ name: 'customers' });
};

const goToVehicles = () => {
  router.push({ name: 'vehicles' });
};

const goToServices = () => {
  router.push({ name: 'services' });
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
          <span class="text-muted small fw-bold text-uppercase tracking-wider">Painel Geral</span>
          <h1 class="h3 fw-bold text-slate-900 mb-0 font-headline">Dashboard Principal</h1>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="badge bg-slate-900 text-white px-3 py-2 fw-semibold">
            Oficina: {{ authStore.tenantName || 'Carregando...' }}
          </span>
        </div>
      </header>

      <!-- Banner de Boas-Vindas Principal -->
      <div class="card border-0 mb-4 bg-slate-900 text-white rounded-xl overflow-hidden position-relative shadow-sm">
        <div class="card-body p-5 position-relative z-1">
          <span class="badge bg-white-soft text-white mb-2 px-2.5 py-1.5 fw-medium font-monospace" style="font-size: 0.7rem;">SISTEMA ONLINE</span>
          <h2 class="display-6 fw-bold font-headline mb-3 text-white">Olá, {{ authStore.user?.name || 'Super Admin' }}!</h2>
          <p class="mb-0 text-white-80 max-width-md">
            Bem-vindo de volta ao PRIME ERP. Monitore as atividades da oficina, controle o fluxo de ordens de serviço, gerencie a frota de veículos e mantenha o cadastro de clientes atualizado com precisão técnica.
          </p>
        </div>
        <!-- Círculo decorativo ao fundo -->
        <div class="decorative-circle position-absolute"></div>
      </div>

      <!-- Grid de Informações Técnicas e Perfil -->
      <div class="row mb-4">
        <!-- Card Perfil -->
        <div class="col-md-6 mb-3">
          <div class="card border-slate-200 h-100 shadow-xs rounded-lg">
            <div class="card-body p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="icon-circle bg-slate-100 text-slate-700">
                  <i class="bi bi-person-fill fs-5"></i>
                </div>
                <h5 class="fw-bold mb-0 text-slate-900 font-headline">Seu Perfil de Acesso</h5>
              </div>
              <ul class="list-unstyled mb-0 small text-muted font-monospace">
                <li class="py-2 border-bottom d-flex justify-content-between">
                  <span>Nome do Usuário:</span> <span class="text-dark fw-semibold">{{ authStore.user?.name || 'N/A' }}</span>
                </li>
                <li class="py-2 border-bottom d-flex justify-content-between">
                  <span>E-mail Corporativo:</span> <span class="text-dark fw-semibold">{{ authStore.user?.email || 'N/A' }}</span>
                </li>
                <li class="py-2 d-flex justify-content-between align-items-center">
                  <span>Nível de Acesso:</span> 
                  <span class="badge bg-secondary-soft text-secondary text-capitalize font-monospace">
                    {{ authStore.user?.role || 'User' }}
                  </span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Card Informações Técnicas (Tenant) -->
        <div class="col-md-6 mb-3">
          <div class="card border-slate-200 h-100 shadow-xs rounded-lg">
            <div class="card-body p-4">
              <div class="d-flex align-items-center gap-3 mb-3">
                <div class="icon-circle bg-slate-100 text-slate-700">
                  <i class="bi bi-hdd-network-fill fs-5"></i>
                </div>
                <h5 class="fw-bold mb-0 text-slate-900 font-headline">Identificação de Conexão</h5>
              </div>
              <ul class="list-unstyled mb-0 small text-muted font-monospace">
                <li class="py-2 border-bottom d-flex justify-content-between">
                  <span>Identificador (Slug):</span> <span class="text-dark fw-semibold">{{ authStore.tenantSlug }}</span>
                </li>
                <li class="py-2 border-bottom d-flex justify-content-between">
                  <span>Banco de Dados Físico:</span> <span class="text-dark fw-semibold">prime_tenant_{{ authStore.tenantSlug }}</span>
                </li>
                <li class="py-2 d-flex justify-content-between align-items-center">
                  <span>Status do Tenant:</span> <span class="badge bg-success-soft text-success">Ativo / Saudável</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </div>

      <!-- Módulos Disponíveis e Atalhos -->
      <div class="card border-0 shadow-xs rounded-xl mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
          <h5 class="fw-bold text-slate-900 mb-0 font-headline">Acesso Rápido a Módulos</h5>
        </div>
        <div class="card-body p-4">
          <div class="row">
            <!-- Módulo de Clientes (Ativo) -->
            <div class="col-md-4 mb-3">
              <div class="card border-slate-100 h-100 shadow-xs hover-card rounded-lg">
                <div class="card-body p-4 d-flex flex-column align-items-start">
                  <div class="icon-badge bg-primary-soft text-primary p-2.5 mb-3 rounded">
                    <i class="bi bi-people-fill fs-4"></i>
                  </div>
                  <h5 class="fw-bold text-slate-900 mb-2 font-headline">Clientes</h5>
                  <p class="text-muted small mb-4">
                    Gerencie o cadastro de clientes, dados de contato corporativo/pessoal, endereços físicos e observações.
                  </p>
                  <button @click="goToCustomers" class="btn btn-primary w-100 fw-semibold mt-auto rounded-lg">
                    Acessar Cadastro
                  </button>
                </div>
              </div>
            </div>

            <!-- Módulo de Veículos -->
            <div class="col-md-4 mb-3">
              <div class="card border-slate-100 h-100 shadow-xs hover-card rounded-lg">
                <div class="card-body p-4 d-flex flex-column align-items-start">
                  <div class="icon-badge bg-primary-soft text-primary p-2.5 mb-3 rounded">
                    <i class="bi bi-truck fs-4"></i>
                  </div>
                  <h5 class="fw-bold text-slate-900 mb-2 font-headline">Veículos</h5>
                  <p class="text-muted small mb-4">
                    Acompanhe a frota vinculada aos seus clientes, incluindo histórico de quilometragem e placas.
                  </p>
                  <button @click="goToVehicles" class="btn btn-primary w-100 fw-semibold mt-auto rounded-lg">
                    Acessar Frota
                  </button>
                </div>
              </div>
            </div>

            <!-- Módulo de Serviços -->
            <div class="col-md-4 mb-3">
              <div class="card border-slate-100 h-100 shadow-xs hover-card rounded-lg">
                <div class="card-body p-4 d-flex flex-column align-items-start">
                  <div class="icon-badge bg-primary-soft text-primary p-2.5 mb-3 rounded">
                    <i class="bi bi-wrench-adjustable fs-4"></i>
                  </div>
                  <h5 class="fw-bold text-slate-900 mb-2 font-headline">Serviços</h5>
                  <p class="text-muted small mb-4">
                    Mapeie a tabela de serviços da oficina e defina a precificação padrão para faturar as O.S.
                  </p>
                  <button @click="goToServices" class="btn btn-primary w-100 fw-semibold mt-auto rounded-lg">
                    Acessar Serviços
                  </button>
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
.bg-slate-900 {
  background-color: #0f172a;
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
  background-color: rgba(13, 110, 253, 0.1);
}
.bg-secondary-soft {
  background-color: rgba(108, 117, 125, 0.1);
}
.bg-success-soft {
  background-color: rgba(40, 167, 69, 0.1);
}
.text-white-80 {
  color: rgba(255, 255, 255, 0.8);
}
.max-width-md {
  max-width: 500px;
}
.icon-circle {
  width: 42px;
  height: 42px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
}
.bg-slate-100 {
  background-color: #f1f5f9;
}
.text-slate-700 {
  color: #334155;
}
.hover-card {
  transition: transform 0.2s, box-shadow 0.2s;
}
.hover-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.05) !important;
}
.decorative-circle {
  right: -80px;
  bottom: -80px;
  width: 250px;
  height: 250px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0) 70%);
}
</style>
