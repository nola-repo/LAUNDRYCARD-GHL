@forelse($recentRuns as $run)
  <tr data-key="{{ $run->id }}">
    <td>{{ $run->started_at?->copy()->utc()->format('M j, Y · H:i') ?? '—' }}</td>
    <td>{{ ucfirst(str_replace('_', ' ', $run->type)) }}<small>{{ ucfirst(str_replace('_', ' ', $run->mode)) }}</small></td>
    <td><x-badge :value="$run->status" /></td>
  </tr>
@empty
  <tr data-key="empty"><td colspan="3" class="ov-empty">No integration runs recorded yet.</td></tr>
@endforelse
