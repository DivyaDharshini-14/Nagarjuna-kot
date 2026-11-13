@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show auto-dismiss-alert shadow-sm border-0 d-flex align-items-center justify-content-between mb-3 bg-success-subtle text-success-emphasis" role="alert" style="border-radius: 10px; transition: opacity 0.4s ease-out, transform 0.4s ease-out;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill text-success fs-5"></i>
            <span class="fw-medium" style="font-size: 0.86rem;">{{ session('success') }}</span>
        </div>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show auto-dismiss-alert shadow-sm border-0 d-flex align-items-center justify-content-between mb-3 bg-danger-subtle text-danger-emphasis" role="alert" style="border-radius: 10px; transition: opacity 0.4s ease-out, transform 0.4s ease-out;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
            <span class="fw-medium" style="font-size: 0.86rem;">{{ session('error') }}</span>
        </div>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show auto-dismiss-alert shadow-sm border-0 mb-3 bg-danger-subtle text-danger-emphasis" role="alert" style="border-radius: 10px; transition: opacity 0.4s ease-out, transform 0.4s ease-out;">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-x-circle-fill text-danger fs-5 mt-0.5"></i>
            <div>
                <ul class="mb-0 ps-3" style="font-size: 0.86rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    const alerts = document.querySelectorAll('.auto-dismiss-alert');
    if (alerts.length > 0) {
        setTimeout(function () {
            alerts.forEach(function (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-6px)';
                setTimeout(function () {
                    alert.remove();
                }, 400);
            });
        }, 3500);
    }
});
</script>
