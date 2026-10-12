<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="description" content="LaundryCard to GoHighLevel integration monitoring console (internal).">
<title>@yield('title', 'Integration Console') · LaundryCard → GHL</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<div class="shell">
  <aside class="side">
    <div class="brand"><i>⇄</i> Integration Console</div>
    <nav class="nav">
      @php
        $items = ['overview'=>'Overview','installations'=>'OAuth Installations','mappings'=>'Branch Mapping','records'=>'Customer Records','logs'=>'Integration Logs','failed'=>'Failed Jobs','developer'=>'Developer Controls'];
        $failedCount = \App\Models\LaundrycardSourceRecord::whereIn('processing_status',['failed'])->count();
      @endphp
      @foreach($items as $r=>$label)
        <a id="nav-{{ $r }}" href="{{ route('admin.'.$r) }}" class="{{ request()->routeIs('admin.'.$r) ? 'on':'' }}">{{ $label }}
          @if($r==='failed' && $failedCount)<span class="count">{{ $failedCount }}</span>@endif</a>
      @endforeach
    </nav>
    <div class="who">{{ auth()->user()->email }}
      <form method="post" action="{{ route('logout') }}" style="margin-top:8px">@csrf<button id="logout" class="btn sm">Sign out</button></form>
    </div>
  </aside>
  <main>
    @if(session('status'))<div class="flash ok">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="flash err">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="flash err">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    @if(session('result'))
      <div class="flash res"><strong>{{ session('result')['title'] }}</strong>
        <pre>{{ json_encode(session('result')['data'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) }}</pre></div>
    @endif
    @yield('content')
  </main>
</div>
</body>
</html>
