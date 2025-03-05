<div class="form-section">
    <div class="form-title">1. Personal Information</div>
    <div class="row mb-3">
        <div class="col-md-6">
            <label for="name" class="form-label">Name (Family Name, Given Name, Middle Name)</label>
            <input type="text" id="name" name="name" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label for="dob" class="form-label">Date of Birth</label>
            <input type="date" id="dob" name="dob" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label for="sex" class="form-label">Sex</label>
            <select id="sex" name="sex" class="form-select" required>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label for="current_address" class="form-label">Current Address</label>
            <input type="text" id="current_address" name="current_address" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label for="permanent_address" class="form-label">Permanent Address</label>
            <input type="text" id="permanent_address" name="permanent_address" class="form-control">
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label for="email" class="form-label">Email Address</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label for="landline" class="form-label">Landline #</label>
            <input type="text" id="landline" name="landline" class="form-control">
        </div>
        <div class="col-md-4">
            <label for="mobile" class="form-label">Mobile Phone #</label>
            <input type="text" id="mobile" name="mobile" class="form-control" required>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label for="place_of_birth" class="form-label">Place of Birth</label>
            <input type="text" id="place_of_birth" name="place_of_birth" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label for="marital_status" class="form-label">Marital Status</label>
            <input type="text" id="marital_status" name="marital_status" class="form-control">
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <label for="emergency_contact" class="form-label">Emergency Contact</label>
            <input type="text" id="emergency_contact" name="emergency_contact" class="form-control"
                placeholder="Full Name, Relationship, Phone, Email" required>
        </div>
    </div>
</div>