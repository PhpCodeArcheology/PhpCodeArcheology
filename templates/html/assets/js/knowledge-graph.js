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
      const visibleNodes = nodes.filter(function (node) {
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

      // Node limit warning
      let alphaDecayVal = 0.028;
      if (visibleNodes.length > 500) {
        alphaDecayVal = 0.05;
        g.append('text')
          .attr('x', container.clientWidth / 2)
          .attr('y', 22)
          .attr('text-anchor', 'middle')
          .attr('fill', '#f59e0b')
          .attr('font-size', '0.78rem')
          .attr('font-family', "'Source Sans 3', sans-serif")
          .text('Note: >500 nodes — performance reduced. Narrow filters for better interactivity.');
      }

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
          const url = nodeUrl(d);
          if (url) window.location.href = url;
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
        buildGraph();
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
        buildGraph();
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

    // Theme change re-render
    const themeObserver = new MutationObserver(function () {
      buildGraph();
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    // Initial render
    buildGraph();
  };

})();
