/* ==========================================================================
   PAMS — API Client
   Centralized AJAX helper for all CRUD operations.
   Every state-changing call includes a CSRF token for browser sessions.
   ========================================================================== */
const API_BASE = apiBase();

function csrfToken() {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? (m.getAttribute('content') || '') : '';
}

async function apiGet(module, action, params = {}) {
    const qs = new URLSearchParams({ module, action, ...params }).toString();
    const res = await fetch(`${API_BASE}?${qs}`);
    return res.json();
}

async function apiPost(module, action, data = {}) {
    const isForm = typeof FormData !== 'undefined' && data instanceof FormData;
    const token = csrfToken();
    let body;
    if (isForm) {
        if (token) data.append('_csrf_token', token);
        body = data;
    } else {
        body = JSON.stringify(Object.assign({}, data, token ? { _csrf_token: token } : {}));
    }
    const res = await fetch(`${API_BASE}?module=${module}&action=${action}`, {
        method: 'POST',
        headers: isForm ? {} : { 'Content-Type': 'application/json' },
        body
    });
    return res.json();
}

async function apiDelete(module, action, id) {
    return apiPost(module, action, { id });
}