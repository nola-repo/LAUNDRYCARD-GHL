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
    <div class="brand-icon">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="m16 3 4 4-4 4"></path>
        <path d="M20 7H4"></path>
        <path d="m8 21-4-4 4-4"></path>
        <path d="M4 17h16"></path>
      </svg>
    </div>
    <div class="brand-text">
      <span class="brand-title">LaundryCard <span class="brand-sep">⇄</span> GHL</span>
      <span class="brand-subtitle">Console</span>
    </div>
  </div>
  <button id="mobile-menu-toggle" class="mobile-menu-btn" aria-label="Toggle navigation">
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
        <div class="brand-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="m16 3 4 4-4 4"></path>
            <path d="M20 7H4"></path>
            <path d="m8 21-4-4 4-4"></path>
            <path d="M4 17h16"></path>
          </svg>
        </div>
        <div class="brand-text">
          <span class="brand-title">LaundryCard <span class="brand-sep">⇄</span> GHL</span>
          <span class="brand-tag">Integration Suite</span>
        </div>
      </div>
      <button id="sidebar-collapse-toggle" class="sidebar-collapse-btn" title="Toggle sidebar width" aria-label="Collapse sidebar">
        <svg class="collapse-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
      </button>
    </div>

    @php
      $failedCount = \App\Models\LaundrycardSourceRecord::whereIn('processing_status',['failed'])->count();
    @endphp

    <nav class="nav">
      <div class="nav-section-label">Core Platform</div>
      
      <a id="nav-overview" href="{{ route('admin.overview') }}" class="nav-link {{ request()->routeIs('admin.overview') ? 'on':'' }}" data-tooltip="Overview">
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

      <a id="nav-installations" href="{{ route('admin.installations') }}" class="nav-link {{ request()->routeIs('admin.installations') ? 'on':'' }}" data-tooltip="OAuth Installations">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            <path d="m9 12 2 2 4-4"></path>
          </svg>
        </span>
        <span class="nav-label">OAuth Installations</span>
      </a>

      <a id="nav-mappings" href="{{ route('admin.mappings') }}" class="nav-link {{ request()->routeIs('admin.mappings') ? 'on':'' }}" data-tooltip="Branch Mapping">
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

      <a id="nav-records" href="{{ route('admin.records') }}" class="nav-link {{ request()->routeIs('admin.records') ? 'on':'' }}" data-tooltip="Customer Records">
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

      <a id="nav-transactions" href="{{ route('admin.transactions') }}" class="nav-link {{ request()->routeIs('admin.transactions') ? 'on':'' }}" data-tooltip="Credit History">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
        </span>
        <span class="nav-label">Credit History</span>
      </a>

      <a id="nav-logs" href="{{ route('admin.logs') }}" class="nav-link {{ request()->routeIs('admin.logs') ? 'on':'' }}" data-tooltip="Integration Logs">
        <span class="nav-icon">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="4 17 10 11 4 5"></polyline>
            <line x1="12" y1="19" x2="20" y2="19"></line>
          </svg>
        </span>
        <span class="nav-label">Integration Logs</span>
      </a>

      <div class="nav-section-label">Reliability & API</div>

      <a id="nav-failed" href="{{ route('admin.failed') }}" class="nav-link {{ request()->routeIs('admin.failed') ? 'on':'' }}" data-tooltip="Failed Activity">
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

      <a id="nav-developer" href="{{ route('admin.developer') }}" class="nav-link {{ request()->routeIs('admin.developer') ? 'on':'' }}" data-tooltip="API Activity">
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
          <button id="logout" class="btn sm logout-btn" title="Sign out from session">
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
// Sidebar Toggle & Mobile Drawer Handling
(() => {
  const sidebar = document.getElementById('app-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  const collapseToggle = document.getElementById('sidebar-collapse-toggle');
  const mobileToggle = document.getElementById('mobile-menu-toggle');

  if (collapseToggle && sidebar) {
    const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
    if (isCollapsed) {
      sidebar.classList.add('collapsed');
      document.body.classList.add('sidebar-is-collapsed');
    }

    collapseToggle.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      const collapsed = sidebar.classList.contains('collapsed');
      document.body.classList.toggle('sidebar-is-collapsed', collapsed);
      localStorage.setItem('sidebar_collapsed', collapsed ? 'true' : 'false');
    });
  }

  if (mobileToggle && sidebar && overlay) {
    const toggleMobileMenu = () => {
      sidebar.classList.toggle('mobile-open');
      overlay.classList.toggle('active');
    };

    mobileToggle.addEventListener('click', toggleMobileMenu);
    overlay.addEventListener('click', () => {
      sidebar.classList.remove('mobile-open');
      overlay.classList.remove('active');
    });
  }
})();
</script>

@stack('scripts')
</body>
</html>
