@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show auto-dismiss-alert shadow-sm border-0 d-flex align-items-center justify-content-between mb-3" role="alert" style="border-radius: 8px; transition: opacity 0.5s ease-out, transform 0.5s ease-out;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-5 text-success"></i>
            <span class="fw-medium" style="font-size: 0.88rem;">{{ session('success') }}</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show auto-dismiss-alert shadow-sm border-0 d-flex align-items-center justify-content-between mb-3" role="alert" style="border-radius: 8px; transition: opacity 0.5s ease-out, transform 0.5s ease-out;">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
            <span class="fw-medium" style="font-size: 0.88rem;">{{ session('error') }}</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show auto-dismiss-alert shadow-sm border-0 mb-3" role="alert" style="border-radius: 8px; transition: opacity 0.5s ease-out, transform 0.5s ease-out;">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-x-circle-fill fs-5 text-danger mt-0.5"></i>
            <div>
                <ul class="mb-0 ps-3" style="font-size: 0.88rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
                }, 500);
            });
        }, 3500);
    }
});
</script>
