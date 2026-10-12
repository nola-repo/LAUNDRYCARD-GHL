<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Sign in · Integration Console</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="center">
  <form class="card login" method="post" action="{{ route('login.attempt') }}">
    @csrf
    <div class="brand"><i>⇄</i> Integration Console</div>
    <h1>Administrator sign in</h1>
    <p class="sub">Internal monitoring tool. Authorized personnel only.</p>
    <p><input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Email" required autofocus style="width:100%"></p>
    <p><input id="password" type="password" name="password" placeholder="Password" required style="width:100%"></p>
    @error('email')<div class="err-txt">{{ $message }}</div>@enderror
    <p><button id="login-submit" class="btn primary" style="width:100%">Sign in</button></p>
  </form>
</div>
</body>
</html>
