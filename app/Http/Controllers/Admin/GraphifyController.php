<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\GraphifyService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class GraphifyController extends Controller
{
    public function show(Request $request, string $type = 'network'): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isSuperAdmin()) {
            abort(403, 'Unauthorized. Super Administrator access required.');
        }

        $html = GraphifyService::getHtmlContent($type);

        if (! $html) {
            return response(
                '<h1>Architecture Graph Not Generated Yet</h1><p>Please run the generator from the Filament Platform Graph page or run <code>php artisan graphify:generate</code>.</p>',
                404,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        if ($type === 'network') {
            $html = $this->enhanceNetworkHtml($html, $request->query('focus'));
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    private function enhanceNetworkHtml(string $html, ?string $focus = null): string
    {
        $hudCss = <<<'CSS'
  #graph-controls-hud {
    position: absolute;
    top: 14px;
    left: 14px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
  }
  .hud-buttons {
    display: flex;
    align-items: center;
    gap: 4px;
    background: rgba(26, 26, 46, 0.88);
    backdrop-filter: blur(8px);
    border: 1px solid var(--border, #2a2a4e);
    border-radius: 10px;
    padding: 4px 6px;
    pointer-events: auto;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.35);
  }
  .hud-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: transparent;
    border: 1px solid transparent;
    color: var(--text, #e2e8f0);
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
  }
  .hud-btn:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
  }
  .hud-btn:active {
    transform: scale(0.96);
  }
  .hud-btn.active {
    background: rgba(99, 102, 241, 0.28);
    color: #a5b4fc;
    border-color: rgba(99, 102, 241, 0.5);
  }
  .hud-hint {
    background: rgba(26, 26, 46, 0.75);
    backdrop-filter: blur(6px);
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 11px;
    color: #94a3b8;
    pointer-events: auto;
    border: 1px solid var(--border, #2a2a4e);
  }
  [data-theme="light"] .hud-buttons {
    background: rgba(255, 255, 255, 0.95);
    border-color: #d9dee5;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }
  [data-theme="light"] .hud-btn {
    color: #334155;
  }
  [data-theme="light"] .hud-btn:hover {
    background: rgba(0, 0, 0, 0.06);
    color: #0f172a;
  }
  [data-theme="light"] .hud-btn.active {
    background: rgba(99, 102, 241, 0.15);
    color: #4f46e5;
    border-color: rgba(99, 102, 241, 0.3);
  }
  [data-theme="light"] .hud-hint {
    background: rgba(240, 242, 245, 0.9);
    border-color: #d9dee5;
    color: #555555;
  }
CSS;

        $hudHtml = <<<'HTML'
<div id="graph-controls-hud">
  <div class="hud-buttons">
    <button type="button" class="hud-btn" id="hud-zoom-in" title="Zoom In (+)">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>Zoom In</span>
    </button>
    <button type="button" class="hud-btn" id="hud-zoom-out" title="Zoom Out (-)">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
      <span>Zoom Out</span>
    </button>
    <button type="button" class="hud-btn" id="hud-fit" title="Fit to Screen (Reset)">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>
      <span>Fit All</span>
    </button>
    <button type="button" class="hud-btn" id="hud-perf-mode" title="Toggle 60 FPS Fast Mode (straight hardware lines, no hover hitching, optimized for low-end devices)">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg>
      <span id="hud-perf-text">Fast 60fps</span>
    </button>
    <button type="button" class="hud-btn" id="hud-expand" title="Toggle Fullscreen">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
      <span>Expand</span>
    </button>
  </div>
  <div class="hud-hint">
    Scroll to zoom &bull; Drag to pan &bull; Double-click subsystem to focus
  </div>
</div>
HTML;

        $safeFocusJson = json_encode($focus);

        $hudJs = <<<JS
<script>
window.addEventListener('DOMContentLoaded', function () {
  if (typeof network === 'undefined') return;

  // Determine initial performance mode
  var savedPref = null;
  try { savedPref = localStorage.getItem('graphify-perf-mode'); } catch (e) {}

  var isLowSpec = (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4)
    || (window.innerWidth < 1024)
    || (window.devicePixelRatio && window.devicePixelRatio > 1.25);

  var perfMode = savedPref !== null ? (savedPref === 'true') : isLowSpec;

  function applyPerformanceOptions(enabled) {
    if (typeof network === 'undefined') return;

    // Apply high-performance rendering options
    network.setOptions({
      interaction: {
        hover: true,
        hoverConnectedEdges: false, // Critical: stops iterating 2,600+ edges on every hover
        hideEdgesOnZoom: true,      // Critical: hides edges during zoom to maintain 60 FPS
        hideEdgesOnDrag: true,      // Critical: hides edges during pan/drag
        tooltipDelay: 250,          // Eliminates DOM tooltip thrashing
      },
      edges: {
        smooth: enabled ? false : { type: 'continuous', roundness: 0.15 },
        selectionWidth: enabled ? 1 : 2,
      },
      physics: {
        enabled: false,             // Freezes physics simulation
      }
    });

    var btn = document.getElementById('hud-perf-mode');
    var text = document.getElementById('hud-perf-text');
    if (btn && text) {
      if (enabled) {
        btn.classList.add('active');
        text.textContent = 'Fast 60fps: ON';
        btn.title = 'Fast Mode ACTIVE (hardware straight edges, zero hover lag). Click to switch to Curved Quality Mode.';
      } else {
        btn.classList.remove('active');
        text.textContent = 'Fast 60fps: OFF';
        btn.title = 'Quality Mode ACTIVE (curved edges). Click to switch to Fast Mode for low-end devices.';
      }
    }
  }

  // Hook HUD Controls
  document.getElementById('hud-zoom-in')?.addEventListener('click', function () {
    network.moveTo({ scale: network.getScale() * 1.5, animation: { duration: 250, easingFunction: 'easeInOutQuad' } });
  });

  document.getElementById('hud-zoom-out')?.addEventListener('click', function () {
    network.moveTo({ scale: network.getScale() * 0.65, animation: { duration: 250, easingFunction: 'easeInOutQuad' } });
  });

  document.getElementById('hud-fit')?.addEventListener('click', function () {
    network.fit({ animation: { duration: 400, easingFunction: 'easeInOutQuad' } });
  });

  document.getElementById('hud-perf-mode')?.addEventListener('click', function () {
    perfMode = !perfMode;
    try { localStorage.setItem('graphify-perf-mode', String(perfMode)); } catch (e) {}
    applyPerformanceOptions(perfMode);
  });

  document.getElementById('hud-expand')?.addEventListener('click', function () {
    if (!document.fullscreenElement) {
      document.documentElement.requestFullscreen().catch(function () {});
    } else {
      document.exitFullscreen().catch(function () {});
    }
  });

  network.on('doubleClick', function (params) {
    if (params.nodes && params.nodes.length > 0) {
      network.focus(params.nodes[0], { scale: 2.2, animation: { duration: 450, easingFunction: 'easeInOutQuad' } });
    }
  });

  // Apply baseline performance options immediately
  applyPerformanceOptions(perfMode);

  var focusTarget = {$safeFocusJson};
  if (!focusTarget) {
    var searchParams = new URLSearchParams(window.location.search);
    focusTarget = searchParams.get('focus');
  }

  network.once('stabilizationIterationsDone', function () {
    network.setOptions({ physics: { enabled: false } });
    applyPerformanceOptions(perfMode);

    setTimeout(function () {
      if (focusTarget) {
        try {
          var allNodes = network.body.data.nodes.get();
          var matched = allNodes.find(function(n) {
            return String(n.id) === String(focusTarget)
              || (n.label && n.label.toLowerCase() === focusTarget.toLowerCase())
              || (n.title && n.title.toLowerCase().indexOf(focusTarget.toLowerCase()) !== -1);
          });
          if (matched) {
            network.focus(matched.id, { scale: 1.8, animation: { duration: 600, easingFunction: 'easeInOutQuad' } });
            network.selectNodes([matched.id]);
            return;
          }
        } catch(e) {}
      }

      var currentScale = network.getScale();
      if (currentScale < 0.7) {
        network.moveTo({ scale: Math.max(0.9, currentScale * 2.4), animation: { duration: 600, easingFunction: 'easeInOutQuad' } });
      }
    }, 300);
  });
});
</script>
JS;

        // Inject CSS
        $html = str_replace('</style>', $hudCss."\n</style>", $html);

        // Inject HUD HTML into #graph
        $html = str_replace('<div id="graph"></div>', '<div id="graph" style="position:relative;">'.$hudHtml.'</div>', $html);

        // Inject HUD JS before closing </body>
        $html = str_replace('</body>', $hudJs."\n</body>", $html);

        return $html;
    }
}
