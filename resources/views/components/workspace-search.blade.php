@props(['name', 'label', 'placeholder' => '', 'id' => null])
@php($inputId = $id ?? 'search-'.$name)
<div class="workspace-field workspace-field-search"><label for="{{ $inputId }}">{{ $label }}</label><div class="workspace-search"><input type="search" id="{{ $inputId }}" name="{{ $name }}" value="{{ request($name) }}" placeholder="{{ $placeholder }}" autocomplete="off" data-clearable><button type="button" data-clear-search aria-label="Clear {{ strtolower($label) }}" hidden>×</button></div></div>
