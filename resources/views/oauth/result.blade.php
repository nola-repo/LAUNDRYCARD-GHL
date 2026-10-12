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
  </div>
</div>
</body>
</html>
