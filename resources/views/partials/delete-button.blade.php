@if(auth()->user()->hasPermission($permission))
    <form method="POST" action="{{ route($route, $model) }}" class="d-inline" onsubmit="return confirm('Delete this record?')">
        @csrf
        @method('DELETE')
        <button class="btn btn-sm btn-outline-danger">Delete</button>
    </form>
@endif
