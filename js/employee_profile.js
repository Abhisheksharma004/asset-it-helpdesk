/**
 * VIROS Asset & IT Service Desk - Dedicated Employee Profile Script
 * Handles Change Password modal, form validation, AJAX submission & toasts
 */

document.addEventListener('DOMContentLoaded', function () {
    initProfileChangePassword();
});

// Modal Helpers exposed to window
window.openChangePasswordModal = function () {
    const modal = document.getElementById('changePasswordModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
        setTimeout(() => {
            const curInput = document.getElementById('currentPasswordInput');
            if (curInput) curInput.focus();
        }, 120);
    }
};

window.closeModal = function (modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
};

window.toggleInputType = function (inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        if (btn) btn.textContent = 'Hide';
    } else {
        input.type = 'password';
        if (btn) btn.textContent = 'Show';
    }
};

// Initialize Change Password Form Handler
function initProfileChangePassword() {
    const form = document.getElementById('changePasswordForm');
    if (!form) return;

    const currentInput = document.getElementById('currentPasswordInput');
    const newInput = document.getElementById('newPasswordInput');
    const confirmInput = document.getElementById('confirmPasswordInput');
    const matchMsg = document.getElementById('pwdMatchMsg');
    const submitBtn = document.getElementById('savePasswordBtn');

    // Live password match verification
    function checkMatch() {
        if (!confirmInput || !confirmInput.value) {
            if (matchMsg) matchMsg.textContent = '';
            return;
        }
        if (newInput.value === confirmInput.value) {
            if (matchMsg) {
                matchMsg.textContent = '✓ Passwords match';
                matchMsg.style.color = '#10b981';
            }
        } else {
            if (matchMsg) {
                matchMsg.textContent = '✗ Passwords do not match';
                matchMsg.style.color = '#ef4444';
            }
        }
    }

    if (newInput) newInput.addEventListener('input', checkMatch);
    if (confirmInput) confirmInput.addEventListener('input', checkMatch);

    // Form submit
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const curVal = currentInput ? currentInput.value.trim() : '';
        const newVal = newInput ? newInput.value.trim() : '';
        const cnfVal = confirmInput ? confirmInput.value.trim() : '';

        if (!curVal) {
            showNotification('Please enter your current account password.', 'error');
            if (currentInput) currentInput.focus();
            return;
        }

        if (newVal.length < 6) {
            showNotification('New password must be at least 6 characters long.', 'error');
            if (newInput) newInput.focus();
            return;
        }

        if (newVal !== cnfVal) {
            showNotification('New password and confirmation password do not match.', 'error');
            if (confirmInput) confirmInput.focus();
            return;
        }

        if (newVal === curVal) {
            showNotification('New password cannot be the same as your current password.', 'error');
            if (newInput) newInput.focus();
            return;
        }

        const originalBtnHtml = submitBtn ? submitBtn.innerHTML : 'Save Password';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="spin-icon" style="animation: spin 1s linear infinite;">
                    <circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle>
                    <path d="M12 2a10 10 0 0 1 10 10"></path>
                </svg>
                <span>Updating...</span>
            `;
        }

        fetch('api/change_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                current_password: curVal,
                new_password: newVal,
                confirm_password: cnfVal
            })
        })
        .then(response => {
            return response.json().then(data => ({
                status: response.status,
                ok: response.ok,
                data: data
            }));
        })
        .then(result => {
            if (result.ok && result.data && result.data.success) {
                closeModal('changePasswordModal');
                form.reset();
                if (matchMsg) matchMsg.textContent = '';
                showNotification(result.data.message || 'Your account password has been updated successfully!', 'success');
            } else {
                const errMsg = (result.data && result.data.message) ? result.data.message : 'Failed to update password. Please check your current password.';
                showNotification(errMsg, 'error');
            }
        })
        .catch(err => {
            console.error('Change password error:', err);
            showNotification('Network or server error. Please try again.', 'error');
        })
        .finally(() => {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        });
    });
}

// Notification Helper
function showNotification(msg, type) {
    if (typeof showToast === 'function') {
        showToast(msg, type);
    } else {
        alert(msg);
    }
}

// Close modal on escape or backdrop click
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeModal('changePasswordModal');
    }
});

window.addEventListener('click', function (e) {
    if (e.target && e.target.classList.contains('modal-overlay')) {
        closeModal('changePasswordModal');
    }
});
