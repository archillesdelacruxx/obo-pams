/* ==========================================================================
   PAMS — Generic search / filter helper for tables and lists
   ========================================================================== */
function attachSearch(inputEl, getRows, matchFn, onFilter){
  if (!inputEl) return;
  const run = debounce((val) => {
    const q = val.trim().toLowerCase();
    const rows = getRows();
    let visible = 0;
    rows.forEach(row => {
      const show = q === '' || matchFn(row, q);
      row.el.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (onFilter) onFilter(visible, q);
  }, 180);
  inputEl.addEventListener('input', (e) => run(e.target.value));
}
