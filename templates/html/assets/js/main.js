(function() {
  const sortableTables = document.querySelectorAll('table.sortable');

  sortableTables.forEach(table => {
    const sortableHeaders = table.querySelectorAll('thead th[data-sortable]');
    const sortIcons = table.querySelectorAll('.sort-icon');
    const tBody = table.querySelector('tbody');

    let currentSort = table.dataset.currentsort.split(':');

    const sortTable = (a, b) => {
      const column = parseInt(currentSort[0])
      const dir = currentSort[1];

      const td1 = a.children;
      const td2 = b.children;

      const sortIcon = sortIcons[column];
      sortIcons.forEach(icon => {
        icon.classList.add('opacity-0');
      })
      sortIcon.classList.remove('opacity-0');

      let value1 = td1[column].dataset.sort;
      let value2 = td2[column].dataset.sort;

      switch (currentSort[2]) {
        case 'number':
          value1 = parseFloat(value1);
          value2 = parseFloat(value2);
          break;

        case 'string':
          value1 = value1.toLowerCase();
          value2 = value2.toLowerCase();
          break;
      }

      if (value1 === value2) {
        return 0;
      }

      if (dir === 'asc') {
        sortIcon.classList.remove('rotate-180');
        return value1 > value2 ? 1 : -1;
      }

      sortIcon.classList.add('rotate-180');
      return value1 > value2 ? -1 : 1;
    }

    Array.from(tBody.querySelectorAll('tr'))
      .sort(sortTable)
      .forEach(tr => tBody.appendChild(tr));

    sortableHeaders.forEach((th, thId) => {
      th.classList.add('cursor-pointer');

      th.addEventListener('click', event => {
        event.preventDefault();

        if (parseInt(currentSort[0]) === thId) {
          currentSort[1] = currentSort[1] === 'asc' ? 'desc' : 'asc';
        }
        else {
          currentSort = [thId, 'asc', th.dataset.sortable];
        }

        table.classList.add('opacity-0.5');
        Array.from(tBody.querySelectorAll('tr'))
          .sort(sortTable)
          .forEach(tr => tBody.appendChild(tr));
        table.classList.remove('opacity-0.5');
      });
    });
  });
})();