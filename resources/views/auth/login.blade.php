<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="description" content="Sign in to the LaundryCard integration console.">
<title>Sign in · LaundryCard ⇄ GHL</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ @filemtime(public_path('css/admin.css')) }}">
<link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ @filemtime(public_path('css/login.css')) }}">
</head>
<body class="login-page">
<main class="login-shell">
  <section class="login-ticket" aria-labelledby="login-title">
    <div class="login-ticket-main">
      <div class="login-access-label"><span aria-hidden="true"></span> Administrator access</div>
      <h1 id="login-title">Sign in</h1>
      <p class="login-intro">Access store discovery, branch mappings, and contact sync.</p>

      <form class="login-form" method="post" action="{{ route('login.attempt') }}" novalidate data-login-form>
        @csrf

        <div class="login-field">
          <label for="email">Email address</label>
          <div class="login-input-wrap">
            <svg class="login-input-icon" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="3" y="5" width="18" height="14" rx="2"></rect>
              <path d="m3 7 9 6 9-6"></path>
            </svg>
            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="username" required autofocus aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if($errors->has('email')) aria-describedby="login-email-error" @endif>
          </div>
          <p id="login-email-error" class="login-field-error" role="alert" @if(!$errors->has('email')) hidden @endif>@error('email'){{ $message }}@enderror</p>
        </div>

        <div class="login-field">
          <label for="password">Password</label>
          <div class="login-input-wrap">
            <svg class="login-input-icon" aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
              <rect x="4" y="10" width="16" height="11" rx="2"></rect>
              <path d="M8 10V7a4 4 0 0 1 8 0v3"></path>
            </svg>
            <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if($errors->has('password')) aria-describedby="login-password-error" @endif>
            <button class="login-password-toggle" type="button" aria-label="Show password" aria-pressed="false" data-password-toggle>Show</button>
          </div>
          <p id="login-password-error" class="login-field-error" role="alert" @if(!$errors->has('password')) hidden @endif>@error('password'){{ $message }}@enderror</p>
        </div>

        <button class="login-submit" type="submit" data-login-submit>
          <span>Sign in</span>
        </button>
      </form>

      <div class="login-security-note">
        <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="m12 22 1.5-.7c4-1.9 6.5-5.8 6.5-10.2V5l-8-3-8 3v6.1c0 4.4 2.5 8.3 6.5 10.2L12 22Z"></path>
          <path d="M9 12h6"></path>
        </svg>
        <span>Administrator access only</span>
      </div>
    </div>

    <aside class="login-ticket-art" aria-label="LaundryCard integration mark">
      <x-brand-mark id-prefix="login-art" class="login-art-mark" label="LaundryCard mascot with GoHighLevel transfer arrows" />
      <div class="login-art-caption">
        <span class="login-art-source">LAUNDRYCARD</span>
        <span class="login-art-divider" aria-hidden="true">/</span>
        <span class="login-art-target"><span>G</span><span>H</span><span>L</span></span>
      </div>
      <p class="login-art-detail">STORE ACTIVITY · CONTACT SYNC</p>
    </aside>
  </section>
</main>
<script src="{{ asset('js/login.js') }}?v={{ @filemtime(public_path('js/login.js')) }}" defer></script>
</body>
</html>
