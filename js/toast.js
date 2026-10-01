/**
 * Reusable Toast Notification System
 * Usable across all pages and modules.
 *
 * Usage Examples:
 *   showToast("Please enter both email and password.", "error");
 *   toast.error("Invalid credentials", "Authentication Failed");
 *   toast.success("Login successful!");
 *   toast.warning("Your session is about to expire.");
 *   toast.info("A verification link has been sent.");
 */

(function (window) {
    "use strict";

    const DEFAULT_DURATION = 4000;

    const ICONS = {
        error: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="15" y1="9" x2="9" y2="15"></line>
            <line x1="9" y1="9" x2="15" y2="15"></line>
        </svg>`,
        success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>`,
        warning: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>`,
        info: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="16" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12.01" y2="8"></line>
        </svg>`
    };

    const CLOSE_ICON = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="18" y1="6" x2="6" y2="18"></line>
        <line x1="6" y1="6" x2="18" y2="18"></line>
    </svg>`;

    function getContainer() {
        let container = document.getElementById("toast-container");
        if (!container) {
            container = document.createElement("div");
            container.id = "toast-container";
            container.setAttribute("role", "region");
            container.setAttribute("aria-live", "polite");
            document.body.appendChild(container);
        }
        return container;
    }

    function createToast(options) {
        if (typeof options === "string") {
            options = { message: options };
        }

        const message = options.message || "";
        const type = options.type || "info"; // 'error' | 'success' | 'warning' | 'info'
        const title = options.title || "";
        const duration = typeof options.duration === "number" ? options.duration : DEFAULT_DURATION;

        const container = getContainer();

        const toast = document.createElement("div");
        toast.className = `toast toast-${type}`;
        toast.setAttribute("role", "alert");

        const iconHtml = `<div class="toast-icon">${ICONS[type] || ICONS.info}</div>`;

        let bodyHtml = `<div class="toast-body">`;
        if (title) {
            bodyHtml += `<div class="toast-title">${escapeHtml(title)}</div>`;
        }
        bodyHtml += `<div class="toast-message">${escapeHtml(message)}</div></div>`;

        const closeHtml = `<button type="button" class="toast-close" aria-label="Close">${CLOSE_ICON}</button>`;

        let progressHtml = "";
        if (duration > 0) {
            progressHtml = `<div class="toast-progress"></div>`;
        }

        toast.innerHTML = iconHtml + bodyHtml + closeHtml + progressHtml;
        container.appendChild(toast);

        // Animate entrance
        requestAnimationFrame(() => {
            toast.classList.add("toast-show");
        });

        // Setup progress bar animation & timer
        let timer = null;
        let startTime = Date.now();
        let remainingTime = duration;
        const progressBar = toast.querySelector(".toast-progress");

        function startTimer() {
            if (duration <= 0) return;
            startTime = Date.now();
            if (progressBar) {
                progressBar.style.transition = `transform ${remainingTime}ms linear`;
                progressBar.style.transform = "scaleX(0)";
            }
            timer = setTimeout(dismiss, remainingTime);
        }

        function pauseTimer() {
            if (duration <= 0) return;
            clearTimeout(timer);
            remainingTime -= Date.now() - startTime;
            if (progressBar) {
                const computedStyle = window.getComputedStyle(progressBar);
                progressBar.style.transition = "none";
                progressBar.style.transform = computedStyle.transform;
            }
        }

        function dismiss() {
            clearTimeout(timer);
            toast.classList.remove("toast-show");
            toast.classList.add("toast-hide");
            toast.addEventListener("transitionend", () => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, { once: true });
        }

        // Close button click
        const closeBtn = toast.querySelector(".toast-close");
        if (closeBtn) {
            closeBtn.addEventListener("click", (e) => {
                e.stopPropagation();
                dismiss();
            });
        }

        // Pause on mouse hover
        if (duration > 0) {
            toast.addEventListener("mouseenter", pauseTimer);
            toast.addEventListener("mouseleave", startTimer);
            startTimer();
        }

        return {
            element: toast,
            dismiss: dismiss
        };
    }

    function escapeHtml(text) {
        if (!text) return "";
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }

    // Public API
    function showToast(message, type = "info", title = "", duration = DEFAULT_DURATION) {
        if (typeof message === "object") {
            return createToast(message);
        }
        return createToast({ message, type, title, duration });
    }

    const toast = function (options) {
        return createToast(options);
    };

    toast.error = function (message, title = "", duration = DEFAULT_DURATION) {
        return createToast({ message, type: "error", title, duration });
    };

    toast.success = function (message, title = "", duration = DEFAULT_DURATION) {
        return createToast({ message, type: "success", title, duration });
    };

    toast.warning = function (message, title = "", duration = DEFAULT_DURATION) {
        return createToast({ message, type: "warning", title, duration });
    };

    toast.info = function (message, title = "", duration = DEFAULT_DURATION) {
        return createToast({ message, type: "info", title, duration });
    };

    // Expose globally
    window.showToast = showToast;
    window.toast = toast;

})(window);
