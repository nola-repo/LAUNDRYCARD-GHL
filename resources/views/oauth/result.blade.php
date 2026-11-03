<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>GoHighLevel authorization · Integration Console</title>
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="center">
  <div class="card login">
    <h1>{{ $ok ? 'Connected' : 'Authorization failed' }}</h1>
    <p class="sub"><span class="dot {{ $ok ? 'ok' : 'bad' }}"></span>{{ $message }}</p>

    @if(!$ok)
    <p style="margin-top:1rem;font-size:13px;color:#6b7280;">
      ℹ️ Check the <a href="{{ route('admin.logs') }}?stage=oauth" style="color:#2563eb">API Activity log</a>
      for detailed error information, then try connecting again from the
      <a href="{{ route('admin.installations') }}" style="color:#2563eb">Installations page</a>.
    </p>
    @endif

    @if(!empty($debug))
    <div style="margin-top:1rem;padding:12px;background:#fef3c7;border:1px solid #f59e0b;border-radius:6px;font-size:12px;font-family:monospace;text-align:left;">
      <strong>Debug info (dev only)</strong><br>
      Class: {{ $debug['exception_class'] }}<br>
      Message: {{ $debug['exception_message'] }}<br>
      @if($debug['stage']) Stage: {{ $debug['stage'] }} @endif
    </div>
    @endif

    <p style="margin-top:1.5rem;font-size:12px;color:#9ca3af;">{{ $timestamp ?? now()->toDateTimeString() }}</p>

    <div style="margin-top:1rem;">
      <a href="{{ route('admin.installations') }}" style="display:inline-block;padding:8px 16px;background:#2563eb;color:#fff;border-radius:6px;text-decoration:none;font-weight:600;font-size:14px;">
        ← Back to Installations
      </a>
    </div>
  </div>
</div>
</body>
</html>
