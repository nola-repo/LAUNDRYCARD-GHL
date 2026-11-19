@forelse($recentRuns as $run)
  <tr data-key="{{ $run->id }}">
    <td>{{ $run->started_at?->copy()->utc()->format('M j, Y · H:i') ?? '—' }}</td>
    <td>{{ ucfirst(str_replace('_', ' ', $run->type)) }}<small>{{ ucfirst(str_replace('_', ' ', $run->mode)) }}</small></td>
    <td><x-badge :value="$run->status" /></td>
    <td class="ov-actions"><button class="ov-row-details" type="button" data-detail-open data-detail-title="{{ ucfirst(str_replace('_', ' ', $run->type)) }}" data-detail-summary="{{ ucfirst(str_replace('_', ' ', $run->mode)) }} integration run." aria-label="Details for {{ ucfirst(str_replace('_', ' ', $run->type)) }}" aria-haspopup="dialog" aria-controls="ov-detail-dialog" hidden>Details</button>
      <template class="ov-detail-template"><dl class="ov-detail-list">
        <div><dt>Job</dt><dd>{{ ucfirst(str_replace('_', ' ', $run->type)) }}</dd></div>
        <div><dt>Mode</dt><dd>{{ ucfirst(str_replace('_', ' ', $run->mode)) }}</dd></div>
        <div><dt>Status</dt><dd>{{ ucfirst($run->status) }}</dd></div>
        <div><dt>Trigger</dt><dd>{{ ucfirst($run->trigger) }}</dd></div>
        <div><dt>Started (UTC)</dt><dd>{{ $run->started_at?->copy()->utc()->format('M j, Y · H:i:s') ?? '—' }}</dd></div>
        <div><dt>Finished (UTC)</dt><dd>{{ $run->finished_at?->copy()->utc()->format('M j, Y · H:i:s') ?? 'Still running' }}</dd></div>
      </dl></template>
    </td>
  </tr>
@empty
  <tr data-key="empty"><td colspan="4" class="ov-empty">No integration runs recorded yet.</td></tr>
@endforelse
