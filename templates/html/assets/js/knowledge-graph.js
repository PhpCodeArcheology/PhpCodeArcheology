(function () {
  'use strict';

  const NODE_COLORS = {
    class:     '#06b6d4',
    interface: '#a855f7',
    trait:     '#f97316',
    package:   '#22c55e',
    author:    '#ec4899',
    method:    '#94a3b8',
    function:  '#64748b',
  };

  const EDGE_STYLES = {
    depends_on:  { dark: 'rgba(255,255,255,0.2)',  light: 'rgba(0,0,0,0.15)',     dash: null,   width: 1   },
    extends:     { dark: '#3b82f6',                light: '#3b82f6',               dash: null,   width: 2   },
    implements:  { dark: '#a855f7',                light: '#a855f7',               dash: null,   width: 1.5 },
    uses_trait:  { dark: '#f97316',                light: '#f97316',               dash: null,   width: 1.5 },
    cycle_member:{ dark: '#ef4444',                light: '#ef4444',               dash: [5, 4], width: 2   },
    belongs_to:  { dark: 'rgba(255,255,255,0.1)',  light: 'rgba(0,0,0,0.08)',      dash: [2, 3], width: 1   },
    authored_by: { dark: 'rgba(236,72,153,0.3)',   light: 'rgba(236,72,153,0.4)',  dash: null,   width: 1   },
    declares:    { dark: 'rgba(255,255,255,0.08)', light: 'rgba(0,0,0,0.06)',      dash: null,   width: 0.5 },
    calls:       { dark: '#facc15',                light: '#ca8a04',               dash: [3, 2], width: 1   },
  };

  function resolveNodeType(node) {
    if (node.type === 'class') {
      if (node.flags && node.flags.interface) return 'interface';
      if (node.flags && node.flags.trait)     return 'trait';
    }
    return node.type;
  }

  function nodeRadius(node) {
    return Math.max(5, Math.min(25, 5 + Math.sqrt(node.metrics && node.metrics.cc != null ? node.metrics.cc : 1) * 2.5));
  }

  function nodeUrl(node) {
    if (node.type === 'class') {
      return 'classes/' + node.id.replace('class:', '') + '.html';
    }
    return null;
  }

  function shortName(fullName) {
    if (!fullName) return '';
    const parts = fullName.split('\\');
    return parts[parts.length - 1];
  }

  function applyChipStyle(chip) {
    var color = chip.getAttribute('data-color');
    if (chip.classList.contains('inactive')) {
      chip.style.backgroundColor = '';
      chip.style.color = '';
      chip.style.borderColor = '';
    } else {
      chip.style.backgroundColor = color;
      chip.style.color = '#fff';
      chip.style.borderColor = color;
    }
  }

  // Apply initial chip styles
  document.querySelectorAll('.filter-chip').forEach(applyChipStyle);

  window.initKnowledgeGraph = function (data) {
    const nodes = (data.nodes || []).map(n => Object.assign({}, n));
    const edges = (data.edges || []).map(e => Object.assign({}, e));
    const cycles = data.cycles || [];

    // Fill stats
    const cycleNodeSet = new Set();
    cycles.forEach(function (cycle) {
      (cycle.nodes || []).forEach(function (nid) { cycleNodeSet.add(nid); });
    });

    const statNodes    = document.getElementById('stat-nodes');
    const statEdges    = document.getElementById('stat-edges');
    const statCycles   = document.getElementById('stat-cycles');
    const statPackages = document.getElementById('stat-packages');

    if (statNodes)    statNodes.textContent    = nodes.length;
    if (statEdges)    statEdges.textContent    = edges.length;
    if (statCycles)   statCycles.textContent   = cycles.length;
    if (statPackages) statPackages.textContent = nodes.filter(function (n) { return n.type === 'package'; }).length;

    // Filter state
    const activeNodeTypes = new Set(
      Array.from(document.querySelectorAll('.filter-chip[data-filter-type="node"]:not(.inactive)')).map(c => c.getAttribute('data-value'))
    );
    const activeEdgeTypes = new Set(
      Array.from(document.querySelectorAll('.filter-chip[data-filter-type="edge"]:not(.inactive)')).map(c => c.getAttribute('data-value'))
    );
    let minCC        = 0;
    let problemsOnly = false;

    // Focus mode: specific entity selection
    const selectedEntities = new Set();
    const packageMap = {};
    (data.clusters || []).forEach(function (c) { packageMap[c.id] = c; });

    // Build searchable entity index
    const entityIndex = [];
    var focusTypes = { class: 1, package: 1, author: 1 };
    nodes.forEach(function (n) {
      if (focusTypes[n.type]) {
        entityIndex.push({
          id: n.id,
          name: n.name,
          shortName: shortName(n.name),
          type: resolveNodeType(n),
          searchText: n.name.toLowerCase(),
        });
      }
    });
    var typePriority = { package: 0, author: 1 };
    entityIndex.sort(function (a, b) {
      var pa = typePriority[a.type] != null ? typePriority[a.type] : 2;
      var pb = typePriority[b.type] != null ? typePriority[b.type] : 2;
      if (pa !== pb) return pa - pb;
      return a.name.localeCompare(b.name);
    });

    // D3 setup
    const container = document.getElementById('graph-container');
    const svg       = d3.select('#knowledge-graph-svg');
    const tooltip   = document.getElementById('graph-tooltip');
    const tooltipTitle = document.getElementById('tooltip-title');
    const tooltipBody  = document.getElementById('tooltip-body');

    // Arrow marker defs
    const defs = svg.append('defs');
    Object.keys(EDGE_STYLES).forEach(function (type) {
      const style = EDGE_STYLES[type];
      defs.append('marker')
        .attr('id', 'arrow-' + type)
        .attr('viewBox', '0 -5 10 10')
        .attr('refX', 18)
        .attr('refY', 0)
        .attr('markerWidth', 6)
        .attr('markerHeight', 6)
        .attr('orient', 'auto')
        .append('path')
        .attr('d', 'M0,-5L10,0L0,5')
        .attr('fill', style.dark);
    });

    // Zoom
    const zoom = d3.zoom()
      .scaleExtent([0.05, 4])
      .on('zoom', function (event) {
        g.attr('transform', event.transform);
      });
    svg.call(zoom);

    const g = svg.append('g');

    let simulation = null;

    function buildGraph() {
      const isLight = document.documentElement.getAttribute('data-theme') === 'light';

      // Filter nodes
      let visibleNodes = nodes.filter(function (node) {
        const resolvedType = resolveNodeType(node);
        if (!activeNodeTypes.has(resolvedType)) return false;
        const cc = node.metrics && node.metrics.cc != null ? node.metrics.cc : 0;
        if (cc < minCC) return false;
        if (problemsOnly) {
          const problems = node.problems && node.problems.length ? node.problems.length : 0;
          if (problems === 0 && !cycleNodeSet.has(node.id)) return false;
        }
        return true;
      });

      // Focus mode: show only selected entities + 1-hop neighbors
      if (selectedEntities.size > 0) {
        const expandedFocus = new Set(selectedEntities);
        selectedEntities.forEach(function (eid) {
          var cluster = packageMap[eid];
          if (cluster && cluster.nodeIds) {
            cluster.nodeIds.forEach(function (nid) { expandedFocus.add(nid); });
          }
        });
        var neighborIds = new Set(expandedFocus);
        edges.forEach(function (e) {
          if (!activeEdgeTypes.has(e.type)) return;
          var srcId = (e.source && e.source.id != null) ? e.source.id : e.source;
          var tgtId = (e.target && e.target.id != null) ? e.target.id : e.target;
          if (expandedFocus.has(srcId)) neighborIds.add(tgtId);
          if (expandedFocus.has(tgtId)) neighborIds.add(srcId);
        });
        visibleNodes = visibleNodes.filter(function (node) {
          return neighborIds.has(node.id);
        });
      }

      const visibleNodeIds = new Set(visibleNodes.map(function (n) { return n.id; }));

      // Filter edges — after D3 has mutated source/target to objects, fall back to .id
      const visibleEdges = edges.filter(function (e) {
        if (!activeEdgeTypes.has(e.type)) return false;
        const srcId = (e.source && e.source.id != null) ? e.source.id : e.source;
        const tgtId = (e.target && e.target.id != null) ? e.target.id : e.target;
        return visibleNodeIds.has(srcId) && visibleNodeIds.has(tgtId);
      });

      // Stop old simulation
      if (simulation) simulation.stop();

      // Clear old content
      g.selectAll('*').remove();

      // Empty state
      if (visibleNodes.length === 0) {
        g.append('text')
          .attr('x', container.clientWidth / 2)
          .attr('y', container.clientHeight / 2)
          .attr('text-anchor', 'middle')
          .attr('fill', isLight ? '#94a3b8' : 'rgba(255,255,255,0.3)')
          .attr('font-size', '1rem')
          .attr('font-family', "'Source Sans 3', sans-serif")
          .text('No visible nodes — adjust filters');
        return;
      }

      // Increase alpha decay for large graphs to settle faster
      let alphaDecayVal = visibleNodes.length > 500 ? 0.05 : 0.028;

      // Update arrow marker colors for current theme
      defs.selectAll('marker path').each(function () {
        const markerId  = d3.select(this.parentNode).attr('id');
        const edgeType  = markerId.replace('arrow-', '');
        const edgeStyle = EDGE_STYLES[edgeType];
        if (edgeStyle) {
          d3.select(this).attr('fill', isLight ? edgeStyle.light : edgeStyle.dark);
        }
      });

      // Edges
      const link = g.append('g')
        .attr('class', 'links')
        .selectAll('line')
        .data(visibleEdges)
        .enter()
        .append('line')
        .each(function (d) {
          const style = EDGE_STYLES[d.type] || EDGE_STYLES.depends_on;
          const color = isLight ? style.light : style.dark;
          d3.select(this)
            .attr('stroke', color)
            .attr('stroke-width', style.width)
            .attr('marker-end', 'url(#arrow-' + d.type + ')');
          if (style.dash) {
            d3.select(this).attr('stroke-dasharray', style.dash.join(','));
          }
        });

      // Nodes
      const node = g.append('g')
        .attr('class', 'nodes')
        .selectAll('g')
        .data(visibleNodes)
        .enter()
        .append('g')
        .attr('class', 'node')
        .style('cursor', function (d) { return nodeUrl(d) ? 'pointer' : 'default'; })
        .call(
          d3.drag()
            .on('start', function (event, d) {
              if (!event.active) simulation.alphaTarget(0.3).restart();
              d.fx = d.x;
              d.fy = d.y;
            })
            .on('drag', function (event, d) {
              d.fx = event.x;
              d.fy = event.y;
            })
            .on('end', function (event, d) {
              if (!event.active) simulation.alphaTarget(0);
              d.fx = null;
              d.fy = null;
            })
        );

      // Circle
      node.append('circle')
        .attr('r', function (d) { return nodeRadius(d); })
        .attr('fill', function (d) {
          return NODE_COLORS[resolveNodeType(d)] || '#94a3b8';
        })
        .attr('fill-opacity', function (d) {
          return (d.flags && d.flags.abstract) ? 0.6 : 0.85;
        })
        .attr('stroke', function (d) {
          return cycleNodeSet.has(d.id) ? '#ef4444' : 'none';
        })
        .attr('stroke-width', function (d) {
          return cycleNodeSet.has(d.id) ? 2.5 : 0;
        });

      // Label
      node.append('text')
        .attr('text-anchor', 'middle')
        .attr('dy', function (d) { return nodeRadius(d) + 11; })
        .attr('font-size', '9px')
        .attr('font-family', "'Source Sans 3', sans-serif")
        .attr('fill', isLight ? '#475569' : 'rgba(255,255,255,0.6)')
        .attr('pointer-events', 'none')
        .text(function (d) { return shortName(d.name || ''); });

      // Tooltip events
      node
        .on('mouseover', function (event, d) {
          const resolvedType = resolveNodeType(d);
          const cc           = d.metrics && d.metrics.cc   != null ? d.metrics.cc   : '—';
          const lcom         = d.metrics && d.metrics.lcom != null ? d.metrics.lcom : '—';
          const mi           = d.metrics && d.metrics.mi   != null ? d.metrics.mi   : '—';
          const problems     = d.problems && d.problems.length ? d.problems.length : 0;
          const isCycle      = cycleNodeSet.has(d.id);

          tooltipTitle.textContent = shortName(d.name || '');
          tooltipTitle.style.color = NODE_COLORS[resolvedType] || '#94a3b8';

          let html = '<div class="graph-tooltip-row">Type: ' + resolvedType + '</div>';
          if (cc   !== '—') html += '<div class="graph-tooltip-row">CC: '   + cc   + '</div>';
          if (lcom !== '—') html += '<div class="graph-tooltip-row">LCOM: ' + lcom + '</div>';
          if (mi   !== '—') html += '<div class="graph-tooltip-row">MI: '   + mi   + '</div>';
          if (problems > 0) html += '<div class="graph-tooltip-row" style="color:#f59e0b;">⚠ ' + problems + ' Problem' + (problems > 1 ? 'e' : '') + '</div>';
          if (isCycle)      html += '<div class="graph-tooltip-warn">⟳ Part of a cycle</div>';

          tooltipBody.innerHTML = html;
          tooltip.style.display = 'block';
        })
        .on('mousemove', function (event) {
          const rect = container.getBoundingClientRect();
          let x = event.clientX - rect.left + 14;
          let y = event.clientY - rect.top  + 14;
          // Keep tooltip inside container
          if (x + 290 > container.clientWidth)  x = event.clientX - rect.left - 300;
          if (y + 160 > container.clientHeight) y = event.clientY - rect.top  - 170;
          tooltip.style.left = x + 'px';
          tooltip.style.top  = y + 'px';
        })
        .on('mouseout', function () {
          tooltip.style.display = 'none';
        })
        .on('click', function (event, d) {
          if (d._clickTimer) { clearTimeout(d._clickTimer); d._clickTimer = null; return; }
          d._clickTimer = setTimeout(function () {
            d._clickTimer = null;
            var url = nodeUrl(d);
            if (url) window.location.href = url;
          }, 250);
        })
        .on('dblclick', function (event, d) {
          event.stopPropagation();
          if (d._clickTimer) { clearTimeout(d._clickTimer); d._clickTimer = null; }
          if (!selectedEntities.has(d.id)) {
            selectedEntities.add(d.id);
            if (resolveNodeType(d) === 'author') {
              activateEdgeType('authored_by');
            }
            renderFocusChips();
            renderDropdown(searchInput.value);
            buildGraph();
          }
        });

      // Force simulation
      simulation = d3.forceSimulation(visibleNodes)
        .alphaDecay(alphaDecayVal)
        .force('link', d3.forceLink(visibleEdges)
          .id(function (d) { return d.id; })
          .distance(80)
          .strength(0.3)
        )
        .force('charge', d3.forceManyBody().strength(-120))
        .force('center', d3.forceCenter(container.clientWidth / 2, container.clientHeight / 2))
        .force('collide', d3.forceCollide(function (d) { return nodeRadius(d) + 4; }))
        .on('tick', function () {
          link
            .attr('x1', function (d) { return d.source.x; })
            .attr('y1', function (d) { return d.source.y; })
            .attr('x2', function (d) { return d.target.x; })
            .attr('y2', function (d) { return d.target.y; });
          node.attr('transform', function (d) {
            return 'translate(' + d.x + ',' + d.y + ')';
          });
        });
    }

    // Node-type chip listeners
    document.querySelectorAll('.filter-chip[data-filter-type="node"]').forEach(function (chip) {
      chip.addEventListener('click', function () {
        const value = chip.getAttribute('data-value');
        if (activeNodeTypes.has(value)) {
          activeNodeTypes.delete(value);
          chip.classList.add('inactive');
        } else {
          activeNodeTypes.add(value);
          chip.classList.remove('inactive');
        }
        applyChipStyle(chip);
        buildGraph();
      });
      chip.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); chip.click(); }
      });
    });

    // Edge-type chip listeners
    document.querySelectorAll('.filter-chip[data-filter-type="edge"]').forEach(function (chip) {
      chip.addEventListener('click', function () {
        const value = chip.getAttribute('data-value');
        if (activeEdgeTypes.has(value)) {
          activeEdgeTypes.delete(value);
          chip.classList.add('inactive');
        } else {
          activeEdgeTypes.add(value);
          chip.classList.remove('inactive');
        }
        applyChipStyle(chip);
        buildGraph();
      });
      chip.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); chip.click(); }
      });
    });

    // CC slider
    const ccSlider = document.getElementById('cc-filter');
    const ccLabel  = document.getElementById('cc-filter-label');
    if (ccSlider) {
      ccSlider.addEventListener('input', function () {
        minCC = parseInt(ccSlider.value, 10);
        if (ccLabel) ccLabel.textContent = minCC;
        buildGraph();
      });
    }

    // Problems-only checkbox
    const problemsCheckbox = document.getElementById('problems-only');
    if (problemsCheckbox) {
      problemsCheckbox.addEventListener('change', function () {
        problemsOnly = problemsCheckbox.checked;
        buildGraph();
      });
    }

    // Reset zoom
    const resetZoomBtn = document.getElementById('reset-zoom');
    if (resetZoomBtn) {
      resetZoomBtn.addEventListener('click', function () {
        svg.transition().duration(300).call(zoom.transform, d3.zoomIdentity);
      });
    }

    // Helper: activate an edge type filter if not already active
    function activateEdgeType(type) {
      if (activeEdgeTypes.has(type)) return;
      activeEdgeTypes.add(type);
      var chip = document.querySelector('.filter-chip[data-filter-type="edge"][data-value="' + type + '"]');
      if (chip) {
        chip.classList.remove('inactive');
        applyChipStyle(chip);
      }
    }

    // Focus selector: search, dropdown, chips
    const searchInput    = document.getElementById('entity-search');
    const dropdown       = document.getElementById('entity-dropdown');
    const chipsContainer = document.getElementById('focus-chips');
    const clearFocusBtn  = document.getElementById('clear-focus');
    let highlightIndex   = -1;

    function renderDropdown(query) {
      var q = query.toLowerCase().trim();
      var results = q === ''
        ? entityIndex.slice(0, 50)
        : entityIndex.filter(function (item) { return item.searchText.indexOf(q) !== -1; }).slice(0, 50);

      highlightIndex = -1;

      if (results.length === 0) {
        dropdown.innerHTML = '<div class="focus-dropdown-empty">No matches</div>';
        return;
      }

      var html = '';
      var lastGroup = '';
      results.forEach(function (item) {
        var group = item.type === 'package' ? 'Packages' : item.type === 'author' ? 'Authors' : 'Classes';
        if (group !== lastGroup) {
          html += '<div class="focus-dropdown-group">' + group + '</div>';
          lastGroup = group;
        }
        var isSelected = selectedEntities.has(item.id);
        var nsPart = item.type !== 'package'
          ? item.name.substring(0, Math.max(0, item.name.length - item.shortName.length - 1))
          : '';
        html += '<div class="focus-dropdown-item' + (isSelected ? ' selected' : '') + '" data-entity-id="' + item.id + '">'
          + '<div class="focus-dropdown-dot" data-type="' + item.type + '"></div>'
          + '<span class="focus-dropdown-item-name">' + item.shortName + '</span>'
          + (nsPart ? '<span class="focus-dropdown-item-ns">' + nsPart + '</span>' : '')
          + '<span class="focus-dropdown-item-check">' + (isSelected ? '✓' : '') + '</span>'
          + '</div>';
      });
      dropdown.innerHTML = html;
    }

    function renderFocusChips() {
      if (selectedEntities.size === 0) {
        chipsContainer.classList.remove('has-items');
        chipsContainer.innerHTML = '';
        clearFocusBtn.style.display = 'none';
        return;
      }
      clearFocusBtn.style.display = '';
      chipsContainer.classList.add('has-items');

      var html = '';
      selectedEntities.forEach(function (entityId) {
        var item = entityIndex.find(function (e) { return e.id === entityId; });
        if (!item) return;
        html += '<span class="focus-chip" data-type="' + item.type + '">'
          + item.shortName
          + '<span class="focus-chip-remove" data-entity-id="' + entityId + '">&times;</span>'
          + '</span>';
      });
      chipsContainer.innerHTML = html;
    }

    var dropdownDebounce = null;
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        clearTimeout(dropdownDebounce);
        dropdownDebounce = setTimeout(function () {
          renderDropdown(searchInput.value);
          dropdown.classList.add('open');
          searchInput.setAttribute('aria-expanded', 'true');
        }, 80);
      });

      searchInput.addEventListener('focus', function () {
        renderDropdown(searchInput.value);
        dropdown.classList.add('open');
        searchInput.setAttribute('aria-expanded', 'true');
      });

      searchInput.addEventListener('keydown', function (e) {
        var items = dropdown.querySelectorAll('.focus-dropdown-item');
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          highlightIndex = Math.min(highlightIndex + 1, items.length - 1);
          items.forEach(function (el, i) { el.classList.toggle('highlighted', i === highlightIndex); });
          if (items[highlightIndex]) items[highlightIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          highlightIndex = Math.max(highlightIndex - 1, 0);
          items.forEach(function (el, i) { el.classList.toggle('highlighted', i === highlightIndex); });
          if (items[highlightIndex]) items[highlightIndex].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' && highlightIndex >= 0) {
          e.preventDefault();
          if (items[highlightIndex]) items[highlightIndex].click();
        } else if (e.key === 'Escape') {
          dropdown.classList.remove('open');
          searchInput.setAttribute('aria-expanded', 'false');
          searchInput.blur();
        }
      });
    }

    // Close dropdown on outside click
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.focus-selector')) {
        dropdown.classList.remove('open');
        if (searchInput) searchInput.setAttribute('aria-expanded', 'false');
      }
    });

    // Dropdown item click: toggle selection
    dropdown.addEventListener('click', function (e) {
      var item = e.target.closest('.focus-dropdown-item');
      if (!item) return;
      var entityId = item.getAttribute('data-entity-id');
      if (selectedEntities.has(entityId)) {
        selectedEntities.delete(entityId);
        var cluster = packageMap[entityId];
        if (cluster && cluster.nodeIds) {
          cluster.nodeIds.forEach(function (nid) { selectedEntities.delete(nid); });
        }
      } else {
        selectedEntities.add(entityId);
        var cluster = packageMap[entityId];
        if (cluster && cluster.nodeIds) {
          cluster.nodeIds.forEach(function (nid) { selectedEntities.add(nid); });
        }
        // Auto-activate authored_by edge type when selecting an author
        var matched = entityIndex.find(function (ei) { return ei.id === entityId; });
        if (matched && matched.type === 'author') {
          activateEdgeType('authored_by');
        }
      }
      renderDropdown(searchInput.value);
      renderFocusChips();
      buildGraph();
    });

    // Remove focus chip
    chipsContainer.addEventListener('click', function (e) {
      var removeBtn = e.target.closest('.focus-chip-remove');
      if (!removeBtn) return;
      var entityId = removeBtn.getAttribute('data-entity-id');
      selectedEntities.delete(entityId);
      var cluster = packageMap[entityId];
      if (cluster && cluster.nodeIds) {
        cluster.nodeIds.forEach(function (nid) { selectedEntities.delete(nid); });
      }
      renderFocusChips();
      renderDropdown(searchInput.value);
      buildGraph();
    });

    // Clear all focus
    if (clearFocusBtn) {
      clearFocusBtn.addEventListener('click', function () {
        selectedEntities.clear();
        if (searchInput) searchInput.value = '';
        renderFocusChips();
        buildGraph();
      });
      clearFocusBtn.style.display = 'none';
    }

    // Theme change re-render
    const themeObserver = new MutationObserver(function () {
      document.querySelectorAll('.filter-chip').forEach(applyChipStyle);
      buildGraph();
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    // Initial render
    buildGraph();
  };

})();
