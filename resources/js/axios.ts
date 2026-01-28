import axios from 'axios';

// Use the explicit VITE_API_URL when provided (helps when Vite and Laravel are on different ports)
axios.defaults.baseURL = import.meta.env.VITE_API_URL || window.location.origin || 'http://127.0.0.1:8000';

// Send cookies for cross-site requests (necessary for Sanctum cookie/session auth)
axios.defaults.withCredentials = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add CSRF token from meta if present (blade layout provides this)
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
if (csrfToken) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
}

// Response interceptor to refresh CSRF cookie on 419 and retry once
axios.interceptors.response.use(
    response => response,
    async (error) => {
        const originalRequest = error?.config;
        if (!originalRequest) return Promise.reject(error);

        if (error.response?.status === 419 && !originalRequest._retry) {
            originalRequest._retry = true;
            try {
                await fetch(new URL('/sanctum/csrf-cookie', axios.defaults.baseURL).toString(), { credentials: 'include' });
                // read XSRF from cookie and set header if present
                const xsrfMatch = document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='));
                if (xsrfMatch) {
                    axios.defaults.headers.common['X-XSRF-TOKEN'] = decodeURIComponent(xsrfMatch.split('=')[1]);
                }
                return axios(originalRequest);
            } catch {
                return Promise.reject(error);
            }

        }

        return Promise.reject(error);
    }
);

export default axios;

