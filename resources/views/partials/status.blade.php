@if($active)
    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1" style="font-size: 0.75rem;">
        <i class="bi bi-check-circle-fill me-1"></i>Active
    </span>
@else
    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2 py-1" style="font-size: 0.75rem;">
        <i class="bi bi-dash-circle me-1"></i>Inactive
    </span>
@endif
