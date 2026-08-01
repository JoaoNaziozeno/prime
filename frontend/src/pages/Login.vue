<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const authStore = useAuthStore();

const email = ref('');
const password = ref('');
const loading = ref(false);
const errorMessage = ref('');

const handleLogin = async () => {
  if (!email.value || !password.value) {
    errorMessage.value = 'Por favor, preencha todos os campos.';
    return;
  }

  loading.value = true;
  errorMessage.value = '';

  try {
    await authStore.login(email.value, password.value);
    // Redireciona para o dashboard após o login bem-sucedido
    router.push({ name: 'dashboard' });
  } catch (error) {
    // Trata os erros de validação e credenciais retornados pela API Laravel
    errorMessage.value = error.response?.data?.message || 'Ocorreu um erro ao tentar fazer login. Tente novamente.';
  } finally {
    loading.value = false;
  }
};
</script>

<template>
  <div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
      <div class="col-md-5 col-lg-4">
        <div class="card border-0 shadow-sm rounded-xl">
          <div class="card-body p-5">
            <!-- Cabeçalho do Login -->
            <div class="text-center mb-4 d-flex flex-column align-items-center">
              <div class="logo-box bg-dark text-white d-flex align-items-center justify-content-center rounded mb-3">
                <i class="bi bi-cpu fs-4"></i>
              </div>
              <h3 class="fw-bold text-slate-900 font-headline mb-1">PRIME ERP</h3>
              <p class="text-muted small">Entre com suas credenciais de acesso</p>
            </div>

            <!-- Alerta de Erro -->
            <div v-if="errorMessage" class="alert alert-danger alert-dismissible fade show small rounded-lg" role="alert">
              {{ errorMessage }}
            </div>

            <!-- Formulário -->
            <form @submit.prevent="handleLogin">
              <!-- E-mail -->
              <div class="mb-3">
                <label for="email" class="form-label small fw-semibold text-slate-700">E-mail</label>
                <div class="input-group">
                  <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-envelope"></i>
                  </span>
                  <input
                    v-model="email"
                    type="email"
                    id="email"
                    class="form-control border-start-0 ps-0 rounded-end-lg"
                    placeholder="exemplo@prime.com"
                    required
                    :disabled="loading"
                  />
                </div>
              </div>

              <!-- Senha -->
              <div class="mb-4">
                <label for="password" class="form-label small fw-semibold text-slate-700">Senha</label>
                <div class="input-group">
                  <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-lock"></i>
                  </span>
                  <input
                    v-model="password"
                    type="password"
                    id="password"
                    class="form-control border-start-0 ps-0 rounded-end-lg"
                    placeholder="••••••••"
                    required
                    :disabled="loading"
                  />
                </div>
              </div>

              <!-- Botão Conectar -->
              <button
                type="submit"
                class="btn btn-primary w-100 py-2.5 fw-semibold rounded-lg bg-slate-900 border-0"
                :disabled="loading"
              >
                <span v-if="loading" class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                {{ loading ? 'Conectando...' : 'Entrar' }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.min-vh-100 {
  min-height: 100vh;
}
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
.logo-box {
  width: 42px;
  height: 42px;
}
.form-control {
  padding: 10px 12px;
}
.form-control:focus {
  border-color: #0f172a;
  box-shadow: 0 0 0 0.2rem rgba(15, 23, 42, 0.15);
}
</style>
