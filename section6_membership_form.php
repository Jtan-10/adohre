<div class="form-section">
    <!-- CSRF Protection -->
    <input type="hidden" name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="form-title">6. Other Skills</div>
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="special_skills" class="form-label">a. Special Skills</label>
            <input type="text" id="special_skills" name="special_skills" class="form-control"
                placeholder="Enter your special skills">
        </div>
        <div class="col-md-6">
            <label for="hobbies" class="form-label">b. Hobbies</label>
            <input type="text" id="hobbies" name="hobbies" class="form-control" placeholder="Enter your hobbies">
        </div>
    </div>
</div>