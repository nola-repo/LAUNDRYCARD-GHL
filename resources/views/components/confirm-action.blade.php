@props(['id', 'title', 'action', 'confirmLabel', 'busyLabel' => 'Working…'])
<dialog class="admin-confirm-dialog" id="{{ $id }}" aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-description" data-confirm-dialog>
  <form method="post" action="{{ $action }}" novalidate>
    @csrf
    <h2 id="{{ $id }}-title">{{ $title }}</h2>
    <div id="{{ $id }}-description" class="admin-confirm-description">{{ $slot }}</div>
    <p class="admin-confirm-status" role="status" data-confirm-status data-busy-label="{{ $busyLabel }}"></p>
    <div class="admin-confirm-actions">
      <button type="button" class="admin-action" data-confirm-close autofocus>Cancel</button>
      <button type="submit" class="admin-action admin-action-primary">{{ $confirmLabel }}</button>
    </div>
  </form>
</dialog>
