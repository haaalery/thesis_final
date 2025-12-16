/**
 * Global JavaScript Utilities
 * Client-side validation and UX enhancements
 */

// ===== EMAIL VALIDATION =====
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// ===== PASSWORD STRENGTH CHECKER =====
function checkPasswordStrength(password) {
    let strength = 0;
    const feedback = [];

    if (password.length >= 8) {
        strength += 1;
    } else {
        feedback.push("Password should be at least 8 characters");
    }

    if (/[a-z]/.test(password)) {
        strength += 1;
    } else {
        feedback.push("Add lowercase letters");
    }

    if (/[A-Z]/.test(password)) {
        strength += 1;
    } else {
        feedback.push("Add uppercase letters");
    }

    if (/[0-9]/.test(password)) {
        strength += 1;
    } else {
        feedback.push("Add numbers");
    }

    if (/[^a-zA-Z0-9]/.test(password)) {
        strength += 1;
    } else {
        feedback.push("Add special characters");
    }

    return {
        strength: strength,
        feedback: feedback,
        level: strength <= 2 ? 'weak' : strength <= 4 ? 'medium' : 'strong'
    };
}

// ===== PASSWORD STRENGTH INDICATOR (Optional Enhancement) =====
function showPasswordStrength(password, targetElementId) {
    const result = checkPasswordStrength(password);
    const element = document.getElementById(targetElementId);
    
    if (!element) return;

    if (password.length === 0) {
        element.innerHTML = '';
        return;
    }

    let color = '';
    let text = '';

    switch (result.level) {
        case 'weak':
            color = '#ef4444';
            text = 'Weak';
            break;
        case 'medium':
            color = '#f59e0b';
            text = 'Medium';
            break;
        case 'strong':
            color = '#10b981';
            text = 'Strong';
            break;
    }

    element.innerHTML = `<span style="color: ${color}; font-weight: 500;">Password Strength: ${text}</span>`;
}

// ===== FORM VALIDATION =====
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('is-invalid');
            isValid = false;
        } else {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        }

        // Email specific validation
        if (input.type === 'email' && input.value) {
            if (!validateEmail(input.value)) {
                input.classList.add('is-invalid');
                isValid = false;
            }
        }
    });

    return isValid;
}

// ===== SHOW/HIDE PASSWORD TOGGLE =====
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;

    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}

// ===== NOTIFICATION/ALERT DISPLAY =====
function showNotification(message, type = 'info', duration = 5000) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Styling
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.padding = '1rem 1.5rem';
    notification.style.borderRadius = '8px';
    notification.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1)';
    notification.style.zIndex = '9999';
    notification.style.maxWidth = '400px';
    notification.style.animation = 'slideIn 0.3s ease-out';

    // Color based on type
    switch (type) {
        case 'success':
            notification.style.backgroundColor = '#d1fae5';
            notification.style.color = '#065f46';
            notification.style.borderLeft = '4px solid #10b981';
            break;
        case 'error':
            notification.style.backgroundColor = '#fee2e2';
            notification.style.color = '#991b1b';
            notification.style.borderLeft = '4px solid #ef4444';
            break;
        case 'warning':
            notification.style.backgroundColor = '#fef3c7';
            notification.style.color = '#92400e';
            notification.style.borderLeft = '4px solid #f59e0b';
            break;
        default:
            notification.style.backgroundColor = '#dbeafe';
            notification.style.color = '#1e40af';
            notification.style.borderLeft = '4px solid #2563eb';
    }

    document.body.appendChild(notification);

    // Auto remove after duration
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-in';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, duration);
}

// ===== LOADING SPINNER FOR BUTTONS =====
function setButtonLoading(buttonId, isLoading, loadingText = 'Loading...') {
    const button = document.getElementById(buttonId);
    if (!button) return;

    if (isLoading) {
        button.setAttribute('data-original-text', button.textContent);
        button.textContent = loadingText;
        button.disabled = true;
        button.style.opacity = '0.6';
    } else {
        button.textContent = button.getAttribute('data-original-text') || 'Submit';
        button.disabled = false;
        button.style.opacity = '1';
    }
}

// ===== CSRF TOKEN HANDLER FOR AJAX =====
function getCSRFToken() {
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    return tokenInput ? tokenInput.value : '';
}

// ===== CONFIRMATION DIALOG =====
function confirmAction(message = 'Are you sure?') {
    return confirm(message);
}

// ===== SANITIZE INPUT (Basic) =====
function sanitizeInput(input) {
    const div = document.createElement('div');
    div.textContent = input;
    return div.innerHTML;
}

// ===== AUTO-DISMISS ALERTS =====
function autoDismissAlerts(duration = 5000) {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.3s ease';
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, duration);
    });
}

// ===== INITIALIZE ON PAGE LOAD =====
document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts after 5 seconds
    autoDismissAlerts();

    // Add animation keyframes
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
});

// ===== EXPORT FUNCTIONS FOR MODULE USE =====
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        validateEmail,
        checkPasswordStrength,
        showPasswordStrength,
        validateForm,
        togglePasswordVisibility,
        showNotification,
        setButtonLoading,
        getCSRFToken,
        confirmAction,
        sanitizeInput
    };
}