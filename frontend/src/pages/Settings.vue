<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import api from '../services/api';
import Sidebar from '../components/Sidebar.vue';

const authStore = useAuthStore();

const settings = ref({
  company_name: '',
  company_email: '',
  company_phone: '',
  company_address: '',
  service_tax: '0',
  company_logo: ''
});

const loading = ref(false);
const submitLoading = ref(false);
const error = ref('');
const successMessage = ref('');
const logoFile = ref(null);
const logoUploading = ref(false);

// Carregar configurações
const fetchSettings = async () => {
  loading.value = true;
  error.value = '';
  try {
    const response = await api.get('/settings');
    const data = response.data;
    
    // Mapeia do formato do controller { key: { value } } para nosso objeto reativo local
    if (data) {
      if (data.company_name) settings.value.company_name = data.company_name.value || '';
      if (data.company_email) settings.value.company_email = data.company_email.value || '';
      if (data.company_phone) settings.value.company_phone = data.company_phone.value || '';
      if (data.company_address) settings.value.company_address = data.company_address.value || '';
      if (data.service_tax) settings.value.service_tax = data.service_tax.value || '0';
      if (data.company_logo) settings.value.company_logo = data.company_logo.value || '';
    }
  } catch (err) {
    error.value = 'Falha ao buscar configurações do servidor. Inicializando valores padrão.';
  } finally {
    loading.value = false;
  }
};

// Salvar configurações
const handleSave = async () => {
  submitLoading.value = true;
  error.value = '';
  successMessage.value = '';
  try {
    // Formata o payload para o bulk update do Laravel
    const payload = {
      settings: [
        { key: 'company_name', value: settings.value.company_name, type: 'string', group: 'general' },
        { key: 'company_email', value: settings.value.company_email, type: 'string', group: 'general' },
        { key: 'company_phone', value: settings.value.company_phone, type: 'string', group: 'general' },
        { key: 'company_address', value: settings.value.company_address, type: 'string', group: 'general' },
        { key: 'service_tax', value: settings.value.service_tax.toString(), type: 'float', group: 'finance' },
        { key: 'company_logo', value: settings.value.company_logo, type: 'string', group: 'general' }
      ]
    };
    
    await api.post('/settings', payload);
    successMessage.value = 'Configurações atualizadas com sucesso!';
    
    // Atualiza nome da oficina no authStore se alterado
    if (settings.value.company_name) {
      authStore.tenantName = settings.value.company_name;
    }
  } catch (err) {
    error.value = err.response?.data?.message || 'Erro ao salvar configurações do sistema.';
  } finally {
    submitLoading.value = false;
  }
};

// Upload do logotipo
const handleLogoChange = (e) => {
  logoFile.value = e.target.files[0];
};

const handleUploadLogo = async () => {
  if (!logoFile.value) return;
  logoUploading.value = true;
  error.value = '';
  successMessage.value = '';
  try {
    const formData = new FormData();
    formData.append('logo', logoFile.value);
    
    const response = await api.post('/settings/logo', formData, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    });
    
    settings.value.company_logo = response.data.logo_url;
    successMessage.value = 'Logotipo enviado e salvo com sucesso!';
    logoFile.value = null;
  } catch (err) {
    error.value = err.response?.data?.message || 'Falha ao enviar logotipo para o servidor.';
  } finally {
    logoUploading.value = false;
  }
};

onMounted(() => {
  fetchSettings();
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
          <span class="text-muted small fw-bold text-uppercase tracking-wider">Painel Geral</span>
          <h1 class="h3 fw-bold text-slate-900 mb-0 font-headline">Configurações da Oficina</h1>
        </div>
      </header>

      <!-- Alertas de Status -->
      <div v-if="error" class="alert alert-danger alert-dismissible fade show rounded-lg shadow-sm" role="alert">
        {{ error }}
      </div>
      <div v-if="successMessage" class="alert alert-success alert-dismissible fade show rounded-lg shadow-sm" role="alert">
        {{ successMessage }}
      </div>

      <!-- Configurações Corporativas -->
      <div class="row">
        <!-- Detalhes da Oficina -->
        <div class="col-lg-8 mb-4">
          <div class="card border-0 shadow-xs rounded-xl">
            <div class="card-header bg-white border-0 pt-4 px-4">
              <h5 class="fw-bold text-slate-900 mb-0 font-headline">Dados da Empresa / Oficina</h5>
              <p class="text-muted small mb-0">Gerencie as informações públicas e fiscais exibidas nos relatórios e faturamento.</p>
            </div>
            
            <div class="card-body p-4">
              <div v-if="loading" class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="text-muted mt-2 small">Carregando configurações...</p>
              </div>

              <form v-else @submit.prevent="handleSave">
                <div class="row">
                  <!-- Nome da Oficina -->
                  <div class="col-md-12 mb-3">
                    <label class="form-label small fw-semibold text-slate-700">Nome Oficial da Oficina / Razão Social</label>
                    <input v-model="settings.company_name" type="text" class="form-control rounded-lg" required />
                  </div>

                  <!-- E-mail de Contato -->
                  <div class="col-md-6 mb-3">
                    <label class="form-label small fw-semibold text-slate-700">E-mail Corporativo</label>
                    <input v-model="settings.company_email" type="email" class="form-control rounded-lg" />
                  </div>

                  <!-- Telefone de Contato -->
                  <div class="col-md-6 mb-3">
                    <label class="form-label small fw-semibold text-slate-700">Telefone / WhatsApp</label>
                    <input v-model="settings.company_phone" type="text" class="form-control rounded-lg" />
                  </div>

                  <!-- Endereço -->
                  <div class="col-md-12 mb-3">
                    <label class="form-label small fw-semibold text-slate-700">Endereço Físico Completo</label>
                    <input v-model="settings.company_address" type="text" class="form-control rounded-lg" placeholder="Rua, Número, Bairro, Cidade - UF" />
                  </div>

                  <!-- Alíquota de ISS / Taxa O.S. -->
                  <div class="col-md-6 mb-4">
                    <label class="form-label small fw-semibold text-slate-700">Taxa Administrativa / ISS Padrão (%)</label>
                    <input v-model="settings.service_tax" type="number" step="0.01" min="0" max="100" class="form-control rounded-lg" />
                  </div>
                </div>

                <div class="d-flex justify-content-end pt-3 border-top">
                  <button type="submit" class="btn btn-primary fw-semibold rounded-lg px-4" :disabled="submitLoading">
                    <span v-if="submitLoading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                    Salvar Alterações
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- Gerenciamento de Identidade Visual (Logo) -->
        <div class="col-lg-4 mb-4">
          <div class="card border-0 shadow-xs rounded-xl h-100">
            <div class="card-header bg-white border-0 pt-4 px-4">
              <h5 class="fw-bold text-slate-900 mb-0 font-headline">Identidade Visual</h5>
              <p class="text-muted small mb-0">Adicione o logotipo da sua oficina.</p>
            </div>

            <div class="card-body p-4 d-flex flex-column align-items-center text-center">
              <!-- Visualizador de Logo -->
              <div class="logo-preview-box border rounded-xl bg-light d-flex align-items-center justify-content-center mb-4 overflow-hidden position-relative">
                <img v-if="settings.company_logo" :src="settings.company_logo" alt="Logotipo da Oficina" class="logo-image object-contain" />
                <div v-else class="text-muted d-flex flex-column align-items-center">
                  <i class="bi bi-image fs-1 mb-2"></i>
                  <span class="small">Sem logotipo</span>
                </div>
              </div>

              <!-- Input Upload -->
              <div class="w-100 mb-3">
                <label class="form-label small fw-semibold text-slate-700">Upload de Imagem (JPG/PNG)</label>
                <input type="file" @change="handleLogoChange" class="form-control rounded-lg" accept="image/*" />
              </div>

              <button
                @click="handleUploadLogo"
                class="btn btn-dark w-100 fw-semibold rounded-lg d-flex align-items-center justify-content-center gap-2"
                :disabled="!logoFile || logoUploading"
              >
                <span v-if="logoUploading" class="spinner-border spinner-border-sm me-2" role="status"></span>
                <i class="bi bi-cloud-upload"></i>
                Enviar Logotipo
              </button>

              <div class="mt-4 pt-3 border-top w-100 text-start">
                <small class="text-muted d-block mb-1">
                  <strong>Identificação Fiscal do Inquilino:</strong>
                </small>
                <span class="badge bg-secondary-soft text-secondary font-monospace w-100 py-2 fs-6">
                  ID: {{ authStore.tenantSlug }}
                </span>
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
.bg-secondary-soft {
  background-color: rgba(108, 117, 125, 0.1);
}
.logo-preview-box {
  width: 100%;
  height: 150px;
}
.logo-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.form-control:focus, .form-select:focus {
  border-color: #0f172a;
  box-shadow: 0 0 0 0.2rem rgba(15, 23, 42, 0.15);
}
</style>
