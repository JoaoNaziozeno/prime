import { defineStore } from 'pinia';
import api from '../services/api';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: JSON.parse(localStorage.getItem('auth_user')) || null,
        token: localStorage.getItem('auth_token') || null,
        tenantSlug: localStorage.getItem('tenant_slug') || null,
        tenantName: localStorage.getItem('tenant_name') || null,
    }),

    getters: {
        isAuthenticated: (state) => !!state.token,
        isAdmin: (state) => state.user?.role === 'admin' || state.user?.role === 'super_admin',
    },

    actions: {
        async login(email, password) {
            try {
                // Faz a requisição de login usando a instância configurada do Axios
                const response = await api.post('/auth/login', { email, password });
                
                const { access_token, user, tenant } = response.data;

                // Atualiza o estado reativo da aplicação
                this.token = access_token;
                this.user = user;
                
                if (tenant) {
                    this.tenantSlug = tenant.slug;
                    this.tenantName = tenant.name;
                }

                // Salva no localStorage para persistência entre recarregamentos de página
                localStorage.setItem('auth_token', access_token);
                localStorage.setItem('auth_user', JSON.stringify(user));
                
                if (tenant) {
                    localStorage.setItem('tenant_slug', tenant.slug);
                    localStorage.setItem('tenant_name', tenant.name);
                }

                return response.data;
            } catch (error) {
                this.clearAuth();
                throw error;
            }
        },

        logout() {
            this.clearAuth();
        },

        clearAuth() {
            // Limpa o estado
            this.token = null;
            this.user = null;
            this.tenantSlug = null;
            this.tenantName = null;

            // Limpa o armazenamento local do navegador
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            localStorage.removeItem('tenant_slug');
            localStorage.removeItem('tenant_name');
        }
    }
});
