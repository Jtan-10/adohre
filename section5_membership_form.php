<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<div class="form-section">
    <div class="form-title">5. Key Expertise</div>
    <!-- CSRF Token -->
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

    <!-- Expertise Radio Buttons -->
    <div class="form-check">
        <input type="radio" id="research" name="key_expertise" value="Research" class="form-check-input" required>
        <label for="research" class="form-check-label">Research</label>
    </div>
    <div class="form-check">
        <input type="radio" id="training" name="key_expertise" value="Training/Teaching/Facilitation"
            class="form-check-input">
        <label for="training" class="form-check-label">Training/Teaching/Facilitation</label>
    </div>
    <div class="form-check">
        <input type="radio" id="monitoring" name="key_expertise" value="Monitoring & Evaluation"
            class="form-check-input">
        <label for="monitoring" class="form-check-label">Monitoring & Evaluation</label>
    </div>
    <div class="form-check">
        <input type="radio" id="statistics" name="key_expertise" value="Statistics" class="form-check-input">
        <label for="statistics" class="form-check-label">Statistics</label>
    </div>
    <div class="form-check">
        <input type="radio" id="finance_management" name="key_expertise" value="Finance Management"
            class="form-check-input">
        <label for="finance_management" class="form-check-label">Finance Management</label>
    </div>
    <div class="form-check">
        <input type="radio" id="procurement_supply_chain" name="key_expertise" value="Procurement & Supply Chain"
            class="form-check-input">
        <label for="procurement_supply_chain" class="form-check-label">Procurement & Supply Chain</label>
    </div>
    <div class="form-check">
        <input type="radio" id="hr_development" name="key_expertise" value="HR/Personnel Development"
            class="form-check-input">
        <label for="hr_development" class="form-check-label">HR/Personnel Development</label>
    </div>
    <div class="form-check">
        <input type="radio" id="policy_development" name="key_expertise" value="Policy Development"
            class="form-check-input">
        <label for="policy_development" class="form-check-label">Policy Development</label>
    </div>
    <div class="form-check">
        <input type="radio" id="planning" name="key_expertise" value="Planning" class="form-check-input">
        <label for="planning" class="form-check-label">Planning</label>
    </div>
    <div class="form-check">
        <input type="radio" id="project_management" name="key_expertise" value="Project Management"
            class="form-check-input">
        <label for="project_management" class="form-check-label">Project Management</label>
    </div>
    <div class="form-check">
        <input type="radio" id="project_proposal" name="key_expertise" value="Project Proposal Development"
            class="form-check-input">
        <label for="project_proposal" class="form-check-label">Project Proposal Development</label>
    </div>
    <div class="form-check">
        <input type="radio" id="digital_health" name="key_expertise" value="Digital Health" class="form-check-input">
        <label for="digital_health" class="form-check-label">Digital Health</label>
    </div>
    <div class="form-check">
        <input type="radio" id="administration" name="key_expertise" value="Administration" class="form-check-input">
        <label for="administration" class="form-check-label">Administration</label>
    </div>
    <div class="form-check">
        <input type="radio" id="others_key_expertise" name="key_expertise" value="Others" class="form-check-input">
        <label for="others_key_expertise" class="form-check-label">Others (Specify):</label>
        <input type="text" id="others_expertise_specify" name="others_expertise_specify" class="form-control mt-2"
            placeholder="Specify here..." disabled>
    </div>


    <!-- Specific Fields -->
    <div class="mt-4">
        <label>Indicate specific field:</label>
        <div class="form-check">
            <input type="radio" id="clinical_care" name="specific_field" value="Clinical Care" class="form-check-input"
                required>
            <label for="clinical_care" class="form-check-label">Clinical Care</label>
        </div>
        <div class="form-check">
            <input type="radio" id="public_health" name="specific_field" value="Public Health" class="form-check-input">
            <label for="public_health" class="form-check-label">Public Health</label>
        </div>
        <div class="form-check">
            <input type="radio" id="health_regulation" name="specific_field" value="Health Regulation"
                class="form-check-input">
            <label for="health_regulation" class="form-check-label">Health Regulation</label>
        </div>
        <div class="form-check">
            <input type="radio" id="health_system" name="specific_field" value="Health System" class="form-check-input">
            <label for="health_system" class="form-check-label">Health System</label>
        </div>
        <div class="form-check">
            <input type="radio" id="others_specific_field" name="specific_field" value="Others"
                class="form-check-input">
            <label for="others_specific_field" class="form-check-label">Others (Specify):</label>
            <input type="text" id="others_specific_field_specify" name="others_specific_field_specify"
                class="form-control mt-2" placeholder="Specify here..." disabled>
        </div>
    </div>
</div>