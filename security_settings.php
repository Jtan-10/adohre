<?php
if (!defined('IN_PROFILE')) {
    header("Location: index.php");
    exit();
}

// Fetch current OTP settings
$stmt = $conn->prepare("
    SELECT COALESCE(otp_enabled, 0) as otp_enabled
    FROM user_settings
    WHERE user_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$otpSettings = $result->fetch_assoc() ?: ['otp_enabled' => 0];
$stmt->close();
?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Security Settings</h5>
    </div>
    <div class="card-body">
        <form id="securitySettingsForm">
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="otpEnabled" <?php echo $otpSettings['otp_enabled'] ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="otpEnabled">Enable Two-Factor Authentication (2FA)</label>
                </div>
                <small class="text-muted">When enabled, you'll need to enter a one-time password (OTP) sent to your email after password verification.</small>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
    </div>
</div>

<script nonce="<?php echo $scriptNonce; ?>">
    document.getElementById('securitySettingsForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const otpEnabled = document.getElementById('otpEnabled').checked;

        try {
            const response = await fetch('backend/routes/update_security_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    otpEnabled: otpEnabled
                })
            });

            const result = await response.json();

            if (result.status) {
                showToast('Success', 'Security settings updated successfully.', 'success');
            } else {
                showToast('Error', result.message, 'error');
            }
        } catch (error) {
            showToast('Error', 'An error occurred while updating security settings.', 'error');
        }
    });