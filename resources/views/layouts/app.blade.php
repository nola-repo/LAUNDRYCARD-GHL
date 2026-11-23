<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="description" content="LaundryCard to GoHighLevel integration monitoring console (internal).">
<title>@yield('title', 'Integration Console') · LaundryCard ⇄ GHL</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
@stack('styles')
</head>
<body>

<div class="mobile-topbar">
  <div class="brand">
    <svg class="brand-lockup-svg" viewBox="0 0 320 48" xmlns="http://www.w3.org/2000/svg" aria-label="LaundryCard ⇄ GHL Integration Suite">
      <defs>
        <style>
          @keyframes lc-arrowY { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-2.5px)} }
          @keyframes lc-arrowB { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-4px)} }
          @keyframes lc-arrowG { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
          @keyframes lc-blink  { 0%,90%,100%{transform:scaleY(1)} 95%{transform:scaleY(0.08)} }
          @keyframes lc-dotR   { 0%{transform:translateX(-18px);opacity:0} 15%{opacity:1} 85%{opacity:1} 100%{transform:translateX(18px);opacity:0} }
          @keyframes lc-dotL   { 0%{transform:translateX(18px);opacity:0}  15%{opacity:1} 85%{opacity:1} 100%{transform:translateX(-18px);opacity:0} }
          @keyframes lc-float  { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-2px)} }
          .lc-ay { animation:lc-arrowY 2.5s ease-in-out infinite .0s; transform-origin:center bottom }
          .lc-ab { animation:lc-arrowB 2.5s ease-in-out infinite .2s; transform-origin:center bottom }
          .lc-ag { animation:lc-arrowG 2.5s ease-in-out infinite .4s; transform-origin:center bottom }
          .lc-blink { animation:lc-blink 4s ease-in-out infinite; transform-origin:center }
          .lc-dr1 { animation:lc-dotR 1.6s linear infinite 0s }
          .lc-dr2 { animation:lc-dotR 1.6s linear infinite .53s }
          .lc-dl1 { animation:lc-dotL 1.6s linear infinite .2s }
          .lc-dl2 { animation:lc-dotL 1.6s linear infinite .73s }
          .lc-float { animation:lc-float 3.4s ease-in-out infinite }
        </style>
        <linearGradient id="mb-gRed" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="#FF4060"/><stop offset="100%" stop-color="#C11028"/></linearGradient>
        <linearGradient id="mb-gY" x1="0%" y1="100%" x2="0%" y2="0%"><stop offset="0%" stop-color="#C57B00"/><stop offset="100%" stop-color="#FCD34D"/></linearGradient>
        <linearGradient id="mb-gB" x1="0%" y1="100%" x2="0%" y2="0%"><stop offset="0%" stop-color="#1565C0"/><stop offset="100%" stop-color="#64B5F6"/></linearGradient>
        <linearGradient id="mb-gG" x1="0%" y1="100%" x2="0%" y2="0%"><stop offset="0%" stop-color="#047857"/><stop offset="100%" stop-color="#34D399"/></linearGradient>
        <linearGradient id="mb-arrR" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="#E21B41" stop-opacity="0"/><stop offset="50%" stop-color="#E21B41"/><stop offset="100%" stop-color="#FF5070"/></linearGradient>
        <linearGradient id="mb-arrL" x1="100%" y1="0%" x2="0%" y2="0%"><stop offset="0%" stop-color="#E21B41" stop-opacity="0"/><stop offset="50%" stop-color="#E21B41"/><stop offset="100%" stop-color="#FF5070"/></linearGradient>
        <filter id="mb-cs"><feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#000" flood-opacity=".45"/></filter>
        <filter id="mb-as"><feDropShadow dx="0" dy="1" stdDeviation="2" flood-color="#000" flood-opacity=".3"/></filter>
        <clipPath id="mb-clip"><rect x="175" y="12" width="40" height="26"/></clipPath>
      </defs>

      <!-- ── ICON (0–46px) ───────────────────────────────── -->
      <g class="lc-float">
        <!-- GHL arrows -->
        <g class="lc-ay" filter="url(#mb-as)">
          <rect x="6" y="21" width="7" height="13" rx="1" fill="url(#mb-gY)"/>
          <polygon points="9.5,8 3,21 16,21" fill="url(#mb-gY)"/>
          <polygon points="9.5,8 16,21 13,21 13,34 10,34" fill="#000" opacity=".1"/>
        </g>
        <g class="lc-ab" filter="url(#mb-as)">
          <rect x="17" y="14" width="8" height="20" rx="1" fill="url(#mb-gB)"/>
          <polygon points="21,2 13,14 29,14" fill="url(#mb-gB)"/>
          <polygon points="21,2 29,14 25,14 25,34 21,34" fill="#000" opacity=".1"/>
        </g>
        <g class="lc-ag" filter="url(#mb-as)">
          <rect x="29" y="10" width="7" height="24" rx="1" fill="url(#mb-gG)"/>
          <polygon points="32.5,-2 25,10 40,10" fill="url(#mb-gG)"/>
          <polygon points="32.5,-2 40,10 36,10 36,34 32.5,34" fill="#000" opacity=".1"/>
        </g>
        <!-- Card -->
        <g filter="url(#mb-cs)">
          <rect x="3" y="22" width="39" height="24" rx="6" fill="url(#mb-gRed)" stroke="#8B0A1E" stroke-width="1.5"/>
          <path d="M5 24 L37 24 Q41 24 41 29 L41 31 Q24 30 5 36 Z" fill="#FFF" opacity=".14"/>
          <rect x="6" y="29" width="9" height="7" rx="2" fill="#FCD34D" stroke="#B45309" stroke-width="1"/>
          <line x1="10.5" y1="29" x2="10.5" y2="36" stroke="#92400E" stroke-width=".8"/>
          <line x1="6" y1="32.5" x2="15" y2="32.5" stroke="#92400E" stroke-width=".8"/>
          <g class="lc-blink" transform="translate(26,32)"><ellipse cx="0" cy="0" rx="2" ry="2.5" fill="#1E293B"/><circle cx=".7" cy="-.8" r=".8" fill="#FFF"/></g>
          <g class="lc-blink" transform="translate(34,32)"><ellipse cx="0" cy="0" rx="2" ry="2.5" fill="#1E293B"/><circle cx=".7" cy="-.8" r=".8" fill="#FFF"/></g>
          <circle cx="20" cy="36" r="2" fill="#FFA3B0" opacity=".6"/>
          <circle cx="38" cy="36" r="2" fill="#FFA3B0" opacity=".6"/>
          <path d="M 27 38 Q 30 42 33 38" stroke="#1E293B" stroke-width="1.4" stroke-linecap="round" fill="none"/>
        </g>
      </g>

      <!-- ── WORDMARK ─────────────────────────────────────── -->
      <text x="50" y="27" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-weight="900" font-size="17" letter-spacing="-.3">
        <tspan fill="#E21B41">Laundry</tspan><tspan fill="#0f172a">Card</tspan>
      </text>
      <text x="50" y="40" font-family="system-ui,-apple-system,sans-serif" font-weight="600" font-size="8" letter-spacing="2.5" fill="#64748b">INTEGRATION SUITE</text>

      <!-- ── TRANSFER BRIDGE ──────────────────────────────── -->
      <!-- top arrow → -->
      <rect x="176" y="17" width="30" height="2.5" rx="1.25" fill="url(#mb-arrR)"/>
      <polygon points="208,18.25 202,13.5 202,23" fill="#FF5070"/>
      <!-- bottom arrow ← -->
      <rect x="178" y="26" width="30" height="2.5" rx="1.25" fill="url(#mb-arrL)"/>
      <polygon points="176,27.25 182,22.5 182,32" fill="#FF5070"/>
      <!-- data dots -->
      <g clip-path="url(#mb-clip)">
        <circle class="lc-dr1" cx="193" cy="18.25" r="1.5" fill="#FFF" opacity=".9"/>
        <circle class="lc-dr2" cx="193" cy="18.25" r="1.5" fill="#FFF" opacity=".9"/>
        <circle class="lc-dl1" cx="193" cy="27.25" r="1.5" fill="#FFF" opacity=".9"/>
        <circle class="lc-dl2" cx="193" cy="27.25" r="1.5" fill="#FFF" opacity=".9"/>
      </g>

      <!-- ── GHL ──────────────────────────────────────────── -->
      <text x="216" y="27" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-weight="900" font-size="17" letter-spacing="-.3">
        <tspan fill="#F5A623">G</tspan><tspan fill="#1E88E5">H</tspan><tspan fill="#10B981">L</tspan>
      </text>
    </svg>
  </div>
  <button id="mobile-menu-toggle" class="mobile-menu-btn" aria-label="Open navigation menu" aria-controls="app-sidebar" aria-expanded="false">
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <line x1="3" y1="12" x2="21" y2="12"></line>
      <line x1="3" y1="6" x2="21" y2="6"></line>
      <line x1="3" y1="18" x2="21" y2="18"></line>
    </svg>
  </button>
</div>

<div id="sidebar-overlay" class="sidebar-overlay"></div>

<div class="shell">
  <aside id="app-sidebar" class="side">
    <div class="side-header">
      <div class="brand">
        <x-brand-lockup id-prefix="sidebar" />
      </div>
    </div>

    @php
      $failedCount = \App\Models\LaundrycardSourceRecord::whereIn('processing_status',['failed'])->count();
    @endphp

    <nav class="nav" aria-label="Primary navigation">
      <div class="nav-section-label">Core Platform</div>
      
      <a id="nav-overview" href="{{ route('admin.overview') }}" class="nav-link {{ request()->routeIs('admin.overview') ? 'on':'' }}">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="7" height="9" rx="1.5"></rect>
            <rect x="14" y="3" width="7" height="5" rx="1.5"></rect>
            <rect x="14" y="12" width="7" height="9" rx="1.5"></rect>
            <rect x="3" y="16" width="7" height="5" rx="1.5"></rect>
          </svg>
        </span>
        <span class="nav-label">Overview</span>
      </a>

      <a id="nav-installations" href="{{ route('admin.installations') }}" class="nav-link {{ request()->routeIs('admin.installations') ? 'on':'' }}">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            <path d="m9 12 2 2 4-4"></path>
          </svg>
        </span>
        <span class="nav-label">OAuth Installations</span>
      </a>

      <a id="nav-mappings" href="{{ route('admin.mappings') }}" class="nav-link {{ request()->routeIs('admin.mappings') ? 'on':'' }}">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="18" cy="18" r="3"></circle>
            <circle cx="6" cy="6" r="3"></circle>
            <path d="M18 6v6a6 6 0 0 1-6 6H6"></path>
          </svg>
        </span>
        <span class="nav-label">Branch Mapping</span>
      </a>

      <div class="nav-section-label">Data Pipeline</div>

      <a id="nav-records" href="{{ route('admin.records') }}" class="nav-link {{ request()->routeIs('admin.records') ? 'on':'' }}">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
            <circle cx="9" cy="7" r="4"></circle>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
          </svg>
        </span>
        <span class="nav-label">Customer Records</span>
      </a>

      <a id="nav-transactions" href="{{ route('admin.transactions') }}" class="nav-link {{ request()->routeIs('admin.transactions') ? 'on':'' }}">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
        </span>
        <span class="nav-label">Credit History</span>
      </a>

      <a id="nav-logs" href="{{ route('admin.logs') }}" class="nav-link {{ request()->routeIs('admin.logs') ? 'on':'' }}">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="4 17 10 11 4 5"></polyline>
            <line x1="12" y1="19" x2="20" y2="19"></line>
          </svg>
        </span>
        <span class="nav-label">Integration Logs</span>
      </a>

      <div class="nav-section-label">Reliability & API</div>

      <a id="nav-failed" href="{{ route('admin.failed') }}" class="nav-link {{ request()->routeIs('admin.failed') ? 'on':'' }}">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
          </svg>
        </span>
        <span class="nav-label">Failed Activity</span>
        @if($failedCount)
          <span class="count">{{ $failedCount }}</span>
        @endif
      </a>

      <a id="nav-developer" href="{{ route('admin.developer') }}" class="nav-link {{ request()->routeIs('admin.developer') ? 'on':'' }}">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="16 18 22 12 16 6"></polyline>
            <polyline points="8 6 2 12 8 18"></polyline>
          </svg>
        </span>
        <span class="nav-label">API Activity</span>
      </a>
    </nav>

    <div class="side-footer">
      <div class="who">
        <div class="who-profile">
          <div class="who-avatar">
            {{ strtoupper(substr(auth()->user()->email ?? 'A', 0, 1)) }}
          </div>
          <div class="who-info">
            <div class="who-email" title="{{ auth()->user()->email }}">{{ auth()->user()->email }}</div>
            <div class="who-role"><span class="status-indicator-dot"></span> Admin Console</div>
          </div>
        </div>
        <form method="post" action="{{ route('logout') }}" class="logout-form">
          @csrf
          <button id="logout" class="logout-btn" title="Sign out from session">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
              <polyline points="16 17 21 12 16 7"></polyline>
              <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
            <span>Sign out</span>
          </button>
        </form>
      </div>
    </div>
  </aside>

  <main class="main-content">
    @if(session('status'))
      <div class="flash ok">
        <div class="flash-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
          </svg>
        </div>
        <div class="flash-body">{{ session('status') }}</div>
      </div>
    @endif

    @if(session('error'))
      <div class="flash err">
        <div class="flash-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
        </div>
        <div class="flash-body">{{ session('error') }}</div>
      </div>
    @endif

    @if(isset($errors) && $errors->any())
      <div class="flash err">
        <div class="flash-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
        </div>
        <div class="flash-body">
          @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
      </div>
    @endif

    @if(session('result'))
      <div class="flash res">
        <div class="flash-body">
          <strong>{{ session('result')['title'] }}</strong>
          <pre>{{ json_encode(session('result')['data'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
      </div>
    @endif

    @yield('content')
  </main>
</div>

<script>
// Mobile navigation drawer handling
(() => {
  const sidebar = document.getElementById('app-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const mobileToggle = document.getElementById('mobile-menu-toggle');

  if (mobileToggle && sidebar && overlay) {
    const toggleMobileMenu = (forceOpen) => {
      const open = typeof forceOpen === 'boolean' ? forceOpen : !sidebar.classList.contains('mobile-open');
      sidebar.classList.toggle('mobile-open', open);
      overlay.classList.toggle('active', open);
      mobileToggle.setAttribute('aria-expanded', String(open));
      mobileToggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
    };

    mobileToggle.addEventListener('click', toggleMobileMenu);
    overlay.addEventListener('click', () => {
      toggleMobileMenu(false);
    });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && sidebar.classList.contains('mobile-open')) toggleMobileMenu(false);
    });
  }
})();
</script>

@stack('scripts')
</body>
</html>
