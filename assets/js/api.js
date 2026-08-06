/* ==========================================================================
   PAMS — API Client
   Centralized AJAX helper for all CRUD operations.
   ========================================================================== */
const API_BASE = apiBase();

async function apiGet(module, action, params = {}) {
    const qs = new URLSearchParams({ module, action, ...params }).toString();
    const res = await fetch(`${API_BASE}?${qs}`);
    return res.json();
}

async function apiPost(module, action, data = {}) {
    const isForm = typeof FormData !== 'undefined' && data instanceof FormData;
    const res = await fetch(`${API_BASE}?module=${module}&action=${action}`, {
        method: 'POST',
        headers: isForm ? {} : { 'Content-Type': 'application/json' },
        body: isForm ? data : JSON.stringify(data)
    });
    return res.json();
}

async function apiDelete(module, action, id) {
    const res = await fetch(`${API_BASE}?module=${module}&action=${action}&id=${id}`, { method: 'POST' });
    return res.json();
}
