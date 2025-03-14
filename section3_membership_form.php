<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<div class="form-section">
    <!-- CSRF protection -->
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
    <div class="form-title">3. Highest Educational Background</div>
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="school" class="form-label">School</label>
            <input type="text" id="school" name="school" class="form-control" required autocomplete="organization">
        </div>
        <div class="col-md-6">
            <label for="degree" class="form-label">Degree/Course Attained</label>
            <input type="text" id="degree" name="degree" class="form-control" required autocomplete="off">
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="year_graduated" class="form-label">Year Graduated</label>
            <input type="number" id="year_graduated" name="year_graduated" class="form-control" required min="1900"
                max="2100">
        </div>
    </div>
</div>