import axios from 'axios';
import { errorAlert } from './utils/swal';

/**
 * Axios instance for the Laravel API
 *  - Bearer token (Sanctum)
 *  - 401 => back to login
 *  - 422 / 4xx / 5xx => SweetAlert (unless config.silent = true)
 */
const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) config.headers.Authorization = `Bearer ${token}`;
    return config;
});

api.interceptors.response.use(
    (res) => res,
    (error) => {
        if (axios.isCancel(error)) return Promise.reject(error);
        const status = error.response?.status;
        const data = error.response?.data || {};

        if (status === 401 && !error.config?.url?.endsWith('/login')) {
            localStorage.removeItem('token');
            if (!location.pathname.startsWith('/login')) location.href = '/login';
            return Promise.reject(error);
        }
        if (!error.config?.silent) errorAlert(data, status);
        return Promise.reject(error);
    },
);

export const isCancel = axios.isCancel;
export default api;
