<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<div class="form-section">
    <div class="form-title">4. Current Engagement</div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="current_engagement" id="none" value="None" required>
        <label class="form-check-label" for="none">None</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="current_engagement" id="fulltime" value="Working Full-time">
        <label class="form-check-label" for="fulltime">Working Full-time</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="current_engagement" id="parttime" value="Working Part-time">
        <label class="form-check-label" for="parttime">Working Part-time</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="current_engagement" id="civic" value="Civic Activities">
        <label class="form-check-label" for="civic">Civic Activities</label>
    </div>
    <div class="form-check">
        <input class="form-check-input" type="radio" name="current_engagement" id="others_current_engagement"
            value="Others">
        <label class="form-check-label" for="others_current_engagement">Others (Specify):</label>
        <input type="text" id="others_engagement_specify" name="others_engagement_specify" class="form-control mt-2"
            placeholder="Specify here..." disabled>
    </div>
    <!-- CSRF token hidden field -->
    <input type="hidden" name="csrf_token"
        value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
</div>