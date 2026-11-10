<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Sign in · LaundryCard ⇄ GHL</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
</head>
<body>
<div class="center">
  <form class="card login" method="post" action="{{ route('login.attempt') }}">
    @csrf
    <div class="brand">
      <i>
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="m16 3 4 4-4 4"></path>
          <path d="M20 7H4"></path>
          <path d="m8 21-4-4 4-4"></path>
          <path d="M4 17h16"></path>
        </svg>
      </i>
      <div class="brand-text">
        <span class="brand-title">LaundryCard <span class="brand-sep">⇄</span> GHL</span>
        <span class="brand-tag">Integration Console</span>
      </div>
    </div>

    <h1>Welcome back</h1>
    <p class="sub" style="margin:6px 0 24px">Sign in with your administrator credentials to monitor store telemetry and synchronization.</p>

    <div class="field-group">
      <label class="field-label" for="email">Email Address</label>
      <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="admin@example.com" required autofocus>
    </div>

    <div class="field-group">
      <label class="field-label" for="password">Password</label>
      <input id="password" type="password" name="password" placeholder="••••••••••••" required>
    </div>

    @error('email')
      <div class="err-txt">{{ $message }}</div>
    @enderror

    <div style="margin-top:24px">
      <button id="login-submit" class="btn primary" style="width:100%;padding:13px 20px;font-size:14px">
        <span>Sign in to Console</span>
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M5 12h14"></path>
          <path d="m12 5 7 7-7 7"></path>
        </svg>
      </button>
    </div>
  </form>
</div>
</body>
</html>
