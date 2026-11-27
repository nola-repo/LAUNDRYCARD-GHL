@props(['value', 'empty' => 'Not recorded'])
{{ $value === null ? $empty : ((float)$value < 0 ? '−$' : '$').number_format(abs((float)$value), 2) }}
