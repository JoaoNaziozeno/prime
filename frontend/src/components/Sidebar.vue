<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();

const activeRoute = computed(() => route.name);

const handleLogout = () => {
  authStore.logout();
  router.push({ name: 'login' });
};

const navigationItems = [
  { name: 'dashboard', label: 'Dashboard', icon: 'bi-grid-1x2-fill', disabled: false },
  { name: 'customers', label: 'Clientes', icon: 'bi-people-fill', disabled: false },
  { name: 'vehicles', label: 'Veículos', icon: 'bi-truck', disabled: false },
  { name: 'services', label: 'Serviços', icon: 'bi-wrench-adjustable', disabled: false },
  { name: 'products', label: 'Estoque e Produtos', icon: 'bi-box-seam-fill', disabled: false },
  { name: 'employees', label: 'Funcionários', icon: 'bi-person-badge', disabled: false },
  { name: 'settings', label: 'Configurações', icon: 'bi-gear-fill', disabled: false },
];

const navigateTo = (item) => {
  if (item.disabled) return;
  router.push({ name: item.name });
};
</script>

<template>
  <div class="sidebar d-flex flex-column bg-white border-end shadow-sm">
    <!-- Header / Logo -->
    <div class="sidebar-header d-flex align-items-center px-4 py-4 border-bottom">
      <div class="d-flex align-items-center gap-2">
        <div class="logo-box bg-dark text-white d-flex align-items-center justify-content-center rounded">
          <i class="bi bi-cpu fs-5"></i>
        </div>
        <span class="logo-text fw-bold text-dark letter-spacing-sm fs-5 font-headline">PRIME ERP</span>
      </div>
    </div>

    <!-- Tenant Info -->
    <div class="px-4 py-3 bg-light-soft border-bottom d-flex align-items-center justify-content-between">
      <div class="text-truncate">
        <small class="text-muted d-block text-uppercase fw-bold letter-spacing-xs" style="font-size: 0.65rem;">Oficina Ativa</small>
        <span class="fw-semibold text-dark text-truncate d-block small" :title="authStore.tenantName">
          {{ authStore.tenantName || 'Nenhuma' }}
        </span>
      </div>
      <span class="badge bg-primary-soft text-primary px-2 py-1 rounded font-monospace" style="font-size: 0.7rem;">
        {{ authStore.tenantSlug }}
      </span>
    </div>

    <!-- Navigation Menu -->
    <ul class="nav nav-pills flex-column mb-auto py-3 px-2 gap-1">
      <li v-for="item in navigationItems" :key="item.name" class="nav-item">
        <a
          href="#"
          @click.prevent="navigateTo(item)"
          :class="[
            'nav-link d-flex align-items-center justify-content-between py-2.5 px-3 rounded transition-all',
            activeRoute === item.name ? 'active-link' : '',
            item.disabled ? 'disabled-link text-muted opacity-50 cursor-not-allowed' : 'text-secondary hover-link'
          ]"
        >
          <div class="d-flex align-items-center gap-3">
            <i :class="['bi', item.icon, 'fs-5']"></i>
            <span class="fw-medium small">{{ item.label }}</span>
          </div>
          <span v-if="item.disabled" class="badge bg-secondary-soft text-secondary rounded" style="font-size: 0.6rem;">Breve</span>
        </a>
      </li>
    </ul>

    <!-- User Profile & Footer -->
    <div class="sidebar-footer p-3 border-top bg-light-soft">
      <div class="d-flex align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2 text-truncate">
          <!-- Avatar Placeholder -->
          <div class="avatar bg-slate-200 text-slate-700 fw-bold d-flex align-items-center justify-content-center rounded-circle">
            {{ authStore.user?.name ? authStore.user.name.charAt(0).toUpperCase() : 'U' }}
          </div>
          <div class="text-truncate">
            <span class="fw-semibold text-dark d-block small text-truncate">{{ authStore.user?.name || 'Usuário' }}</span>
            <span class="badge bg-secondary-soft text-secondary text-capitalize font-monospace" style="font-size: 0.65rem;">
              {{ authStore.user?.role || 'user' }}
            </span>
          </div>
        </div>
        <button @click="handleLogout" class="btn btn-outline-danger btn-sm rounded p-2 d-flex align-items-center justify-content-center" title="Sair do sistema">
          <i class="bi bi-box-arrow-right"></i>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.sidebar {
  width: 260px;
  height: 100vh;
  position: fixed;
  top: 0;
  left: 0;
  z-index: 100;
}
.logo-box {
  width: 32px;
  height: 32px;
}
.bg-light-soft {
  background-color: rgba(248, 249, 250, 0.8);
}
.bg-primary-soft {
  background-color: rgba(13, 110, 253, 0.1);
}
.bg-secondary-soft {
  background-color: rgba(108, 117, 125, 0.1);
}
.bg-slate-200 {
  background-color: #e2e8f0;
}
.text-slate-700 {
  color: #334155;
}
.letter-spacing-sm {
  letter-spacing: 0.5px;
}
.letter-spacing-xs {
  letter-spacing: 1px;
}
.transition-all {
  transition: all 0.2s ease-in-out;
}
.avatar {
  width: 36px;
  height: 36px;
  font-size: 0.9rem;
  flex-shrink: 0;
}

/* Link inativo */
.text-secondary {
  color: #475569 !important;
}

/* Hover Link */
.hover-link:hover {
  background-color: rgba(15, 23, 42, 0.04);
  color: #0f172a !important;
}

/* Link Ativo - Baseado no design do Stitch */
.active-link {
  background-color: rgba(15, 23, 42, 0.06);
  color: #0f172a !important;
  font-weight: 600;
  position: relative;
  border-radius: 0 6px 6px 0 !important;
}
.active-link::before {
  content: "";
  position: absolute;
  left: 0;
  top: 15%;
  height: 70%;
  width: 3.5px;
  background-color: #0f172a; /* Cor primária do Stitch */
  border-radius: 0 4px 4px 0;
}

.disabled-link {
  pointer-events: none;
}
</style>
