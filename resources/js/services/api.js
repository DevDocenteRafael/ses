import axios from 'axios';

/**
 * Instância central do Axios.
 * Todos os outros services (authService, adminService, etc.) importam
 * esta instância em vez de criar uma nova — assim a configuração de
 * baseURL, headers e interceptors fica em um único lugar.
 */
const api = axios.create({
    baseURL: '/api',
    headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
    },
});

/**
 * Interceptor de REQUEST: anexa o token de autenticação (se existir)
 * em toda chamada à API automaticamente.
 */
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('ses_token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

/**
 * Interceptor de RESPONSE: trata erros comuns de forma centralizada.
 * - 401 (não autenticado/token expirado): limpa sessão e manda pro login.
 * - Demais erros: apenas repassa para quem chamou tratar (ex: exibir
 *   mensagem de validação no formulário).
 */
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('ses_token');
            localStorage.removeItem('ses_pessoa');
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export default api;