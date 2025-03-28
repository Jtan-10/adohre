<!-- privacy_and_cookie_notice.php -->
<!-- Data Privacy Notice Modal -->
<div class="modal fade" id="dataPrivacyModal" tabindex="-1" aria-labelledby="dataPrivacyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dataPrivacyModalLabel">Data Privacy Notice</h5>
            </div>
            <div class="modal-body">
                <p>
                    In accordance with the Philippines Data Privacy Act of 2012 (R.A. 10173), we inform you that your
                    personal data
                    will be collected, processed, and stored in compliance with our privacy practices. Please review our
                    full <a href="privacy_policy.php">Data Privacy Policy</a> for more information.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="acceptDataPrivacy">I Agree</button>
            </div>
        </div>
    </div>
</div>

<!-- Cookie Consent Modal -->
<div class="modal fade" id="cookieConsentModal" tabindex="-1" aria-labelledby="cookieConsentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cookieConsentModalLabel">Cookie Consent</h5>
            </div>
            <div class="modal-body">
                <p>
                    This website uses cookies to enhance your experience and to analyze site traffic. By clicking "I
                    Agree",
                    you consent to our use of cookies as described in our <a href="cookie_policy.php">Cookie Policy</a>.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="acceptCookies">I Agree</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript to handle modal display -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if the user has accepted the Data Privacy Notice.
    if (!localStorage.getItem('dataPrivacyAccepted')) {
        var dataPrivacyModal = new bootstrap.Modal(document.getElementById('dataPrivacyModal'), {
            backdrop: 'static',
            keyboard: false
        });
        dataPrivacyModal.show();

        document.getElementById('acceptDataPrivacy').addEventListener('click', function() {
            localStorage.setItem('dataPrivacyAccepted', 'true');
            dataPrivacyModal.hide();
            // After accepting Data Privacy, show Cookie Consent.
            showCookieConsent();
        });
    } else if (!localStorage.getItem('cookieConsentAccepted')) {
        // If Data Privacy is already accepted, check Cookie Consent.
        showCookieConsent();
    }

    function showCookieConsent() {
        var cookieConsentModal = new bootstrap.Modal(document.getElementById('cookieConsentModal'), {
            backdrop: 'static',
            keyboard: false
        });
        cookieConsentModal.show();
        document.getElementById('acceptCookies').addEventListener('click', function() {
            localStorage.setItem('cookieConsentAccepted', 'true');
            cookieConsentModal.hide();
        });
    }
});
</script>