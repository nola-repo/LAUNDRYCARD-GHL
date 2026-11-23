@props(['idPrefix' => 'mark', 'x' => 0, 'y' => 0, 'size' => 48, 'label' => 'LaundryCard mascot'])

<svg {{ $attributes->merge(['class' => 'brand-mark']) }} x="{{ $x }}" y="{{ $y }}" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $label }}">
  <defs>
    <linearGradient id="{{ $idPrefix }}-gRed" x1="0%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="#FF4060"/><stop offset="100%" stop-color="#C11028"/></linearGradient>
    <linearGradient id="{{ $idPrefix }}-gY" x1="0%" y1="100%" x2="0%" y2="0%"><stop offset="0%" stop-color="#C57B00"/><stop offset="100%" stop-color="#FCD34D"/></linearGradient>
    <linearGradient id="{{ $idPrefix }}-gB" x1="0%" y1="100%" x2="0%" y2="0%"><stop offset="0%" stop-color="#1565C0"/><stop offset="100%" stop-color="#64B5F6"/></linearGradient>
    <linearGradient id="{{ $idPrefix }}-gG" x1="0%" y1="100%" x2="0%" y2="0%"><stop offset="0%" stop-color="#047857"/><stop offset="100%" stop-color="#34D399"/></linearGradient>
    <filter id="{{ $idPrefix }}-cs"><feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#000" flood-opacity=".45"/></filter>
    <filter id="{{ $idPrefix }}-as"><feDropShadow dx="0" dy="1" stdDeviation="2" flood-color="#000" flood-opacity=".3"/></filter>
  </defs>

  <g class="lc-ay" filter="url(#{{ $idPrefix }}-as)">
    <rect x="6" y="21" width="7" height="13" rx="1" fill="url(#{{ $idPrefix }}-gY)"/>
    <polygon points="9.5,8 3,21 16,21" fill="url(#{{ $idPrefix }}-gY)"/>
    <polygon points="9.5,8 16,21 13,21 13,34 10,34" fill="#000" opacity=".1"/>
  </g>
  <g class="lc-ab" filter="url(#{{ $idPrefix }}-as)">
    <rect x="17" y="14" width="8" height="20" rx="1" fill="url(#{{ $idPrefix }}-gB)"/>
    <polygon points="21,2 13,14 29,14" fill="url(#{{ $idPrefix }}-gB)"/>
    <polygon points="21,2 29,14 25,14 25,34 21,34" fill="#000" opacity=".1"/>
  </g>
  <g class="lc-ag" filter="url(#{{ $idPrefix }}-as)">
    <rect x="29" y="10" width="7" height="24" rx="1" fill="url(#{{ $idPrefix }}-gG)"/>
    <polygon points="32.5,-2 25,10 40,10" fill="url(#{{ $idPrefix }}-gG)"/>
    <polygon points="32.5,-2 40,10 36,10 36,34 32.5,34" fill="#000" opacity=".1"/>
  </g>
  <g class="lc-float" filter="url(#{{ $idPrefix }}-cs)">
    <rect x="3" y="22" width="39" height="24" rx="6" fill="url(#{{ $idPrefix }}-gRed)" stroke="#8B0A1E" stroke-width="1.5"/>
    <path d="M5 24 L37 24 Q41 24 41 29 L41 31 Q24 30 5 36 Z" fill="#FFF" opacity=".14"/>
    <rect x="6" y="29" width="9" height="7" rx="2" fill="#FCD34D" stroke="#B45309" stroke-width="1"/>
    <line x1="10.5" y1="29" x2="10.5" y2="36" stroke="#92400E" stroke-width=".8"/>
    <line x1="6" y1="32.5" x2="15" y2="32.5" stroke="#92400E" stroke-width=".8"/>
    <g class="lc-blink" transform="translate(26,32)"><ellipse cx="0" cy="0" rx="2" ry="2.5" fill="#1E293B"/><circle cx=".7" cy="-.8" r=".8" fill="#FFF"/></g>
    <g class="lc-blink" transform="translate(34,32)"><ellipse cx="0" cy="0" rx="2" ry="2.5" fill="#1E293B"/><circle cx=".7" cy="-.8" r=".8" fill="#FFF"/></g>
    <circle cx="20" cy="36" r="2" fill="#FFA3B0" opacity=".6"/>
    <circle cx="38" cy="36" r="2" fill="#FFA3B0" opacity=".6"/>
    <path d="M 27 38 Q 30 42 33 38" stroke="#1E293B" stroke-width="1.4" stroke-linecap="round" fill="none"/>
  </g>
</svg>
