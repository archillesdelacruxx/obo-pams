/* ==========================================================================
   PAMS — Table helpers: simple client-side pagination
   ========================================================================== */
function paginate(items, page, perPage){
  const start = (page - 1) * perPage;
  return items.slice(start, start + perPage);
}

function renderPagination(container, totalItems, perPage, currentPage, onChange){
  const totalPages = Math.max(1, Math.ceil(totalItems / perPage));
  let html = `<button data-page="${Math.max(1, currentPage - 1)}" ${currentPage === 1 ? 'disabled' : ''} aria-label="Previous">${icon('chevron')}</button>`;
  for (let p = 1; p <= totalPages; p++){
    html += `<button data-page="${p}" class="${p === currentPage ? 'active' : ''}">${p}</button>`;
  }
  html += `<button data-page="${Math.min(totalPages, currentPage + 1)}" ${currentPage === totalPages ? 'disabled' : ''} aria-label="Next" style="transform:rotate(180deg);">${icon('chevron')}</button>`;
  container.innerHTML = html;
  $$('button', container).forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.disabled) return;
      onChange(parseInt(btn.getAttribute('data-page'), 10));
    });
  });
}
