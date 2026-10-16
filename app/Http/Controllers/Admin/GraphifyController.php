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
            $html = $this->enhanceNetworkHtml($html);
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    private function enhanceNetworkHtml(string $html): string
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
    padding: 5px;
    pointer-events: auto;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
  }
  [data-theme="light"] .hud-buttons {
    background: rgba(248, 249, 250, 0.92);
    border-color: #d9dee5;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
  }
  .hud-btn {
    background: transparent;
    color: var(--text, #e0e0e0);
    border: none;
    border-radius: 6px;
    padding: 6px 10px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: all 0.15s ease;
  }
  .hud-btn:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
  }
  [data-theme="light"] .hud-btn:hover {
    background: rgba(0, 0, 0, 0.06);
    color: #111111;
  }
  .hud-hint {
    font-size: 11px;
    color: var(--text-2, #aaa);
    background: rgba(15, 15, 26, 0.8);
    backdrop-filter: blur(4px);
    padding: 5px 10px;
    border-radius: 6px;
    width: fit-content;
    pointer-events: auto;
    border: 1px solid var(--border, #2a2a4e);
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
    <button type="button" class="hud-btn" id="hud-expand" title="Toggle Fullscreen">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
      <span>Expand</span>
    </button>
  </div>
  <div class="hud-hint">
    Scroll to zoom &bull; Drag to pan &bull; Double-click cluster to focus
  </div>
</div>
HTML;

        $hudJs = <<<'JS'
<script>
window.addEventListener('DOMContentLoaded', function () {
  if (typeof network === 'undefined') return;

  document.getElementById('hud-zoom-in')?.addEventListener('click', function () {
    network.moveTo({ scale: network.getScale() * 1.5, animation: { duration: 250, easingFunction: 'easeInOutQuad' } });
  });

  document.getElementById('hud-zoom-out')?.addEventListener('click', function () {
    network.moveTo({ scale: network.getScale() * 0.65, animation: { duration: 250, easingFunction: 'easeInOutQuad' } });
  });

  document.getElementById('hud-fit')?.addEventListener('click', function () {
    network.fit({ animation: { duration: 400, easingFunction: 'easeInOutQuad' } });
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

  network.once('stabilizationIterationsDone', function () {
    setTimeout(function () {
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

        // Inject JS before </body>
        $html = str_replace('</body>', $hudJs."\n</body>", $html);

        return $html;
    }
}
