@if(auth()->user()->hasPermission($permission))
    <form method="POST" action="{{ route($route, $model) }}" class="{{ $formClass ?? 'm-0' }}" onsubmit="return confirm('{{ $confirmText ?? 'Delete this record?' }}')">
        @csrf
        @method('DELETE')
        <button type="submit" class="{{ $btnClass ?? 'dropdown-item text-danger d-flex align-items-center gap-2' }}">
            <i class="bi bi-trash text-danger"></i>
            <span>{{ $btnText ?? 'Delete' }}</span>
        </button>
    </form>
@endif
