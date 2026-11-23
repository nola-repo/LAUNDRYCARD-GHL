@props(['idPrefix' => 'sb', 'inverse' => false])

<svg {{ $attributes->merge(['class' => 'brand-lockup-svg']) }} viewBox="0 0 320 48" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="LaundryCard and GoHighLevel Integration Suite">
  <defs>
    <linearGradient id="{{ $idPrefix }}-arrR" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="#E21B41" stop-opacity="0"/><stop offset="50%" stop-color="#E21B41"/><stop offset="100%" stop-color="#FF5070"/></linearGradient>
    <linearGradient id="{{ $idPrefix }}-arrL" x1="100%" y1="0%" x2="0%" y2="0%"><stop offset="0%" stop-color="#E21B41" stop-opacity="0"/><stop offset="50%" stop-color="#E21B41"/><stop offset="100%" stop-color="#FF5070"/></linearGradient>
    <clipPath id="{{ $idPrefix }}-clip"><rect x="175" y="12" width="40" height="26"/></clipPath>
  </defs>

  <x-brand-mark :id-prefix="$idPrefix" x="0" y="0" size="48" aria-hidden="true" />

  <text x="50" y="27" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-weight="900" font-size="17" letter-spacing="-.3">
    <tspan fill="#E21B41">Laundry</tspan><tspan fill="{{ $inverse ? 'var(--bg-canvas)' : '#0f172a' }}">Card</tspan>
  </text>
  <text x="50" y="40" font-family="system-ui,-apple-system,sans-serif" font-weight="600" font-size="8" letter-spacing="2.5" fill="{{ $inverse ? 'var(--clay-lo)' : '#64748b' }}">INTEGRATION SUITE</text>

  <rect x="176" y="17" width="30" height="2.5" rx="1.25" fill="url(#{{ $idPrefix }}-arrR)"/>
  <polygon points="208,18.25 202,13.5 202,23" fill="#FF5070"/>
  <rect x="178" y="26" width="30" height="2.5" rx="1.25" fill="url(#{{ $idPrefix }}-arrL)"/>
  <polygon points="176,27.25 182,22.5 182,32" fill="#FF5070"/>
  <g clip-path="url(#{{ $idPrefix }}-clip)">
    <circle class="lc-dr1" cx="193" cy="18.25" r="1.5" fill="#FFF" opacity=".9"/>
    <circle class="lc-dr2" cx="193" cy="18.25" r="1.5" fill="#FFF" opacity=".9"/>
    <circle class="lc-dl1" cx="193" cy="27.25" r="1.5" fill="#FFF" opacity=".9"/>
    <circle class="lc-dl2" cx="193" cy="27.25" r="1.5" fill="#FFF" opacity=".9"/>
  </g>
  <text x="216" y="27" font-family="system-ui,-apple-system,'Segoe UI',sans-serif" font-weight="900" font-size="17" letter-spacing="-.3">
    <tspan fill="#F5A623">G</tspan><tspan fill="#1E88E5">H</tspan><tspan fill="#10B981">L</tspan>
  </text>
</svg>
