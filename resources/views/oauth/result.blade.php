<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ $ok ? 'Location connected' : 'Authorization failed' }} · LaundryCard ⇄ GHL</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
<link rel="stylesheet" href="{{ asset('css/admin-actions.css') }}?v={{ filemtime(public_path('css/admin-actions.css')) }}">
<link rel="stylesheet" href="{{ asset('css/oauth-result.css') }}?v={{ filemtime(public_path('css/oauth-result.css')) }}">
</head>
<body class="oauth-result-page">
<main class="oauth-result" aria-labelledby="oauth-result-title">
  <header><x-brand-lockup id-prefix="oauth-result" :inverse="true"/><p>GoHighLevel · Location authorization</p></header>
  <div class="oauth-result-body">
    <span class="oauth-result-mark {{ $ok ? 'is-success' : 'is-error' }}" aria-hidden="true">{{ $ok ? '✓' : '!' }}</span>
    <h1 id="oauth-result-title">{{ $ok ? 'Location connected' : 'Authorization failed' }}</h1>
    <p class="oauth-result-message">{{ $message }}</p>
    <p class="oauth-result-next">{{ $ok ? 'Review the location and its branch assignment in OAuth installations.' : 'Review the OAuth activity log, then start a new connection from the installations page.' }}</p>
    @if(!empty($debug))
      <details class="oauth-result-debug"><summary>Development details</summary><pre>Class: {{ $debug['exception_class'] }}
Message: {{ $debug['exception_message'] }}@if($debug['stage'])
Stage: {{ $debug['stage'] }}@endif</pre></details>
    @endif
    <div class="oauth-result-actions"><a class="admin-action admin-action-primary" href="{{ route('admin.installations') }}">View installations</a>@unless($ok)<a class="admin-action" href="{{ route('admin.logs', ['stage'=>'oauth']) }}">Review OAuth log</a>@endunless</div>
  </div>
  <footer><span>Authorization result</span><time>{{ \Illuminate\Support\Carbon::parse($timestamp ?? now())->utc()->format('M d, Y · H:i:s') }} UTC</time></footer>
</main>
</body>
</html>