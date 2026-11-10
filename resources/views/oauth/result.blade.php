<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>GoHighLevel Authorization · LaundryCard ⇄ GHL</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="center">
  <div class="card login" style="max-width:480px">
    <div class="brand">
      <i>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
          <path d="m9 12 2 2 4-4"></path>
        </svg>
      </i>
      <div class="brand-text">
        <span class="brand-title">GoHighLevel OAuth</span>
        <span class="brand-tag">Location Handshake</span>
      </div>
    </div>

    <h1>{{ $ok ? 'Location Connected' : 'Authorization Failed' }}</h1>
    <div class="health-card-status-badge {{ $ok ? 'ok' : 'bad' }}" style="margin-top:14px">
      <span class="dot {{ $ok ? 'ok' : 'bad' }}"></span>
      <span>{{ $message }}</span>
    </div>

    @if(!$ok)
    <p class="muted" style="font-size:13px;margin-top:12px">
      Check the <a href="{{ route('admin.logs') }}?stage=oauth">OAuth activity log</a>
      for details, then try again from the <a href="{{ route('admin.installations') }}">Installations page</a>.
    </p>
    @endif

    @if(!empty($debug))
    <div class="flash res" style="margin-top:16px">
      <strong>Debug info (dev only)</strong>
      <pre>Class: {{ $debug['exception_class'] }}
Message: {{ $debug['exception_message'] }}@if($debug['stage'])
Stage: {{ $debug['stage'] }}@endif</pre>
    </div>
    @endif

    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:24px;flex-wrap:wrap">
      <span class="muted mono" style="font-size:11.5px">{{ $timestamp ?? now()->toDateTimeString() }}</span>
      <a href="{{ route('admin.installations') }}" class="btn primary">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="m15 18-6-6 6-6"></path>
        </svg>
        <span>Back to Installations</span>
      </a>
    </div>
  </div>
</div>
</body>
</html>
