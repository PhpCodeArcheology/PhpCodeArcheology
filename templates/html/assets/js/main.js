(function() {
  const ROWS_PER_PAGE = 50;

  // --- Sort icon management ---
  const ths = document.querySelectorAll('.sortable th');
  ths.forEach(th => {
    th.addEventListener('click', event => {
      if (th.classList.contains('no-sort')) return;

      ths.forEach(tmpTh => {
        const icon = tmpTh.querySelector('.sort-icon');
        if (icon) icon.classList.add('opacity-0');
      });

      const sortIcon = th.querySelector('.sort-icon');
      if (sortIcon) {
        sortIcon.classList.remove('opacity-0');
        if (th.getAttribute('aria-sort') === 'ascending') {
          sortIcon.classList.remove('rotate-180');
        } else {
          sortIcon.classList.add('rotate-180');
        }
      }

      // Re-apply pagination after sort
      setTimeout(() => initPagination(), 50);
    });
  });

  // --- Auto-click first column to sort ---
  window.addEventListener('load', function () {
    document.querySelectorAll('.sortable th:first-child').forEach(firstHead => {
      firstHead.click();
    });
  });

  // --- Combined Filter Setup (Name + Problem Level + CSV Export) ---
  document.querySelectorAll('.filters').forEach(container => {
    const table = container.nextElementSibling?.querySelector('table')
      || container.parentElement.querySelector('table.sortable');
    if (!table) return;

    // NOTE: sortable.min.js replaces <tbody> on every sort (cloneNode + replaceChild),
    // so we must NEVER cache the tbody reference. Always re-query via table.querySelector('tbody').

    const nameFilter = container.querySelector('.namefilter');
    const resetButton = container.querySelector('.button-reset');

    // Compute problem level per row from CSS classes
    function tagProblemLevels() {
      const tbody = table.querySelector('tbody');
      if (!tbody) return;
      tbody.querySelectorAll('tr').forEach(tr => {
        if (tr.dataset.problemLevel !== undefined) return; // already tagged
        let maxLevel = 0;
        tr.querySelectorAll('span').forEach(span => {
          if (span.classList.contains('bg-red-900')) maxLevel = Math.max(maxLevel, 3);
          else if (span.classList.contains('bg-yellow-900')) maxLevel = Math.max(maxLevel, 2);
        });
        tr.dataset.problemLevel = maxLevel;
      });
    }
    tagProblemLevels();

    // Create problem level dropdown
    const select = document.createElement('select');
    select.className = 'problem-filter text-black rounded px-2 py-0.5';
    select.style.cssText = 'font-size:0.875rem;margin-right:0.5rem;';
    select.innerHTML = '<option value="all">All</option>'
      + '<option value="3">Errors</option>'
      + '<option value="2">Warnings</option>'
      + '<option value="1">With issues</option>'
      + '<option value="0">No issues</option>';

    // Create CSV export button
    const csvBtn = document.createElement('button');
    csvBtn.textContent = 'Export CSV';
    csvBtn.className = 'button-reset';
    csvBtn.style.cssText = 'margin-left:0.5rem;';

    const filterDiv = container.querySelector('div') || container;
    filterDiv.prepend(select);
    filterDiv.appendChild(csvBtn);

    // Combined filter function — re-queries tbody every time
    const applyFilters = () => {
      tagProblemLevels(); // re-tag after sort replaces tbody
      const tbody = table.querySelector('tbody');
      if (!tbody) return;

      const level = select.value;
      const searchText = nameFilter ? nameFilter.value.toUpperCase() : '';

      tbody.querySelectorAll('tr').forEach(tr => {
        let visible = true;

        // Problem level filter
        if (level !== 'all') {
          const rowLevel = parseInt(tr.dataset.problemLevel);
          if (level === '1') {
            visible = rowLevel > 0;
          } else if (level === '0') {
            visible = rowLevel === 0;
          } else {
            visible = rowLevel >= parseInt(level);
          }
        }

        // Name filter
        if (visible && searchText) {
          const filterTd = tr.querySelector('.filter-name');
          if (filterTd) {
            const tdValue = (filterTd.dataset.filter || filterTd.textContent).toUpperCase();
            visible = tdValue.indexOf(searchText) > -1;
          }
        }

        tr.classList.toggle('hidden', !visible);
      });
      initPagination();
    };

    select.addEventListener('change', applyFilters);
    if (nameFilter) nameFilter.addEventListener('keyup', applyFilters);
    if (resetButton) {
      resetButton.addEventListener('click', () => {
        if (nameFilter) nameFilter.value = '';
        select.value = 'all';
        applyFilters();
      });
    }

    // CSV Export — also re-queries tbody
    csvBtn.addEventListener('click', () => {
      const tbody = table.querySelector('tbody');
      if (!tbody) return;

      const headers = [];
      table.querySelectorAll('thead th').forEach(th => {
        const label = th.querySelector('.label-name');
        headers.push(label ? label.textContent.trim() : th.textContent.trim());
      });

      const rows = [];
      tbody.querySelectorAll('tr').forEach(tr => {
        if (tr.classList.contains('hidden') || tr.style.display === 'none') return;
        const cells = [];
        tr.querySelectorAll('td').forEach(td => {
          const val = td.dataset.sort !== undefined ? td.dataset.sort : td.textContent.trim().replace(/\s+/g, ' ');
          cells.push('"' + val.replace(/"/g, '""') + '"');
        });
        rows.push(cells.join(','));
      });

      const csv = headers.map(h => '"' + h.replace(/"/g, '""') + '"').join(',') + '\n' + rows.join('\n');
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = (document.title.replace(/[^a-z0-9]/gi, '_') + '.csv').toLowerCase();
      a.click();
      URL.revokeObjectURL(url);
    });
  });

  // --- Pagination ---
  function initPagination() {
    document.querySelectorAll('table.sortable').forEach(table => {
      const tbody = table.querySelector('tbody');
      if (!tbody) return;
      const allRows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.classList.contains('hidden'));
      if (allRows.length <= ROWS_PER_PAGE) {
        // Remove existing pagination controls if fewer rows than page size
        const existing = table.parentElement.querySelector('.pagination-controls');
        if (existing) existing.remove();
        allRows.forEach(r => r.style.display = '');
        return;
      }

      let currentPage = 1;
      const totalPages = Math.ceil(allRows.length / ROWS_PER_PAGE);

      function showPage(page) {
        currentPage = page;
        allRows.forEach((row, i) => {
          const start = (page - 1) * ROWS_PER_PAGE;
          const end = start + ROWS_PER_PAGE;
          row.style.display = (i >= start && i < end) ? '' : 'none';
        });
        renderControls();
      }

      function renderControls() {
        let controls = table.parentElement.querySelector('.pagination-controls');
        if (!controls) {
          controls = document.createElement('div');
          controls.className = 'pagination-controls';
          controls.style.cssText = 'display:flex;justify-content:center;align-items:center;gap:0.5rem;padding:0.75rem 0;font-size:0.8rem;';
          table.parentElement.appendChild(controls);
        }

        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);
        if (endPage - startPage < maxVisible - 1) startPage = Math.max(1, endPage - maxVisible + 1);

        let html = '';
        html += '<span style="color:rgba(255,255,255,0.4)">' + allRows.length + ' items</span>';
        html += '<button data-page="' + Math.max(1, currentPage-1) + '" style="padding:0.25rem 0.5rem;border-radius:0.25rem;background:rgba(8,51,68,0.5);color:rgba(255,255,255,0.6);border:1px solid rgba(6,182,212,0.15);cursor:pointer;">&laquo;</button>';

        for (let p = startPage; p <= endPage; p++) {
          const active = p === currentPage;
          html += '<button data-page="' + p + '" style="padding:0.25rem 0.5rem;border-radius:0.25rem;'
            + (active ? 'background:rgb(6,182,212);color:#000;font-weight:600;' : 'background:rgba(8,51,68,0.5);color:rgba(255,255,255,0.6);')
            + 'border:1px solid rgba(6,182,212,' + (active ? '0.8' : '0.15') + ');cursor:pointer;">' + p + '</button>';
        }

        html += '<button data-page="' + Math.min(totalPages, currentPage+1) + '" style="padding:0.25rem 0.5rem;border-radius:0.25rem;background:rgba(8,51,68,0.5);color:rgba(255,255,255,0.6);border:1px solid rgba(6,182,212,0.15);cursor:pointer;">&raquo;</button>';

        controls.innerHTML = html;
        controls.querySelectorAll('button').forEach(btn => {
          btn.addEventListener('click', () => showPage(parseInt(btn.dataset.page)));
        });
      }

      showPage(1);
    });
  }

  window.addEventListener('load', () => setTimeout(initPagination, 100));
})();
