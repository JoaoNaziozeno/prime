import axios from "axios";

// Essa constante e so para iniciar o axios apontando para o backend laravel
const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL ||
        'http://localhost/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
});


// Interceptor para anexar automaticamente o Token e o Tenant
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token');
        const tenant = localStorage.getItem('tenant_slug');

        // Se houver um token salvo, adiciona o cabeçalho de autorização
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }

        // Se houver um tenant ativo, adiciona o cabeçalho de multitenancy
        if (tenant) {
            config.headers['X-Tenant'] = tenant;
        }

        return config;
    },

    (error) => {
        return Promise.reject(error);
    }
);

// Interceptor de resposta para redirecionar em caso de 401 (Unauthenticated)
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('auth_user');
            localStorage.removeItem('tenant_slug');
            localStorage.removeItem('tenant_name');

            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export default api;