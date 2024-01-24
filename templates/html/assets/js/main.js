(function() {
  window.addEventListener('load', function () {
    const firstHeads = document.querySelectorAll('.sortable th:first-child')
    firstHeads.forEach(firstHead => {
      firstHead.click();
    });
  });

  const ths = document.querySelectorAll('.sortable th');
  ths.forEach(th => {
    th.addEventListener('click', event => {
      ths.forEach(tmpTh => {
        tmpTh.querySelector('.sort-icon').classList.add('opacity-0');
      });

      const sortIcon = th.querySelector('.sort-icon');
      sortIcon.classList.remove('opacity-0');

      if (th.getAttribute('aria-sort') === 'ascending') {
        sortIcon.classList.remove('rotate-180');
      }
      else {
        sortIcon.classList.add('rotate-180');
      }
    });
  });

  const nameFilters = document.querySelectorAll('.namefilter');

  const handleSearch = nameFilter => {
    const table = nameFilter.parentElement.parentElement.nextElementSibling.querySelector('table');
    const tBody = table.querySelector('tbody');
    const trs = tBody.querySelectorAll('tr');

    if (nameFilter.value === '') {
      trs.forEach(tr => {
        tr.classList.remove('hidden');
      });

      return;
    }

    trs.forEach(tr => {
      const filterTd = tr.querySelector('.filter-name');
      const tdValue = filterTd.dataset.filter;

      if (tdValue.toUpperCase().indexOf(nameFilter.value.toUpperCase()) > -1) {
        tr.classList.remove('hidden');
      } else {
        tr.classList.add('hidden');
      }
    });
  };

  nameFilters.forEach(nameFilter => {
    const resetButton = nameFilter.nextElementSibling;

    resetButton.addEventListener('click', event => {
      nameFilter.value = '';
      handleSearch(nameFilter);
    });

    nameFilter.addEventListener('keyup', event => {
      handleSearch(nameFilter);
    });
  });
})();