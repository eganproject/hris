import $ from 'jquery';
import flatpickr from 'flatpickr';
import select2Factory from 'select2';
import 'flatpickr/dist/flatpickr.css';
import 'select2/dist/css/select2.css';

window.$ = window.jQuery = $;

$.isArray = $.isArray || Array.isArray;
$.isFunction = $.isFunction || ((value) => typeof value === 'function');
$.trim = $.trim || ((value) => (value == null ? '' : String(value).trim()));

if (typeof select2Factory === 'function') {
    select2Factory(window, $);
}

const playNotificationSound = () => {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;

        if (!AudioContext) {
            return;
        }

        const context = new AudioContext();
        const gain = context.createGain();

        gain.gain.setValueAtTime(0.0001, context.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.08, context.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, context.currentTime + 0.42);
        gain.connect(context.destination);

        [660, 880].forEach((frequency, index) => {
            const oscillator = context.createOscillator();
            const startAt = context.currentTime + index * 0.11;

            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(frequency, startAt);
            oscillator.connect(gain);
            oscillator.start(startAt);
            oscillator.stop(startAt + 0.16);
        });

        window.setTimeout(() => context.close(), 650);
    } catch (_) {
        // Browsers may block audio without a recent user gesture.
    }
};

const toastLabels = {
    success: { title: 'Berhasil', icon: 'OK' },
    error: { title: 'Gagal', icon: '!' },
    warning: { title: 'Perhatian', icon: '!' },
    info: { title: 'Informasi', icon: 'i' },
};

const toastStack = (() => {
    let stack = document.querySelector('[data-toast-stack]');

    if (!stack) {
        stack = document.createElement('div');
        stack.className = 'flash-toast-stack';
        stack.setAttribute('data-toast-stack', '');
        stack.setAttribute('aria-live', 'polite');
        stack.setAttribute('aria-atomic', 'false');
        document.body.append(stack);
    }

    return stack;
})();

const showFlashToast = ({ message, type = 'success' }) => {
    if (!message) {
        return;
    }

    const meta = toastLabels[type] || toastLabels.info;
    const toast = document.createElement('section');
    toast.className = `flash-toast flash-toast-${type}`;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

    const icon = document.createElement('div');
    icon.className = 'flash-toast-icon';
    icon.textContent = meta.icon;

    const content = document.createElement('div');
    content.className = 'flash-toast-content';

    const title = document.createElement('p');
    title.className = 'flash-toast-title';
    title.textContent = meta.title;

    const body = document.createElement('p');
    body.className = 'flash-toast-message';
    body.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'flash-toast-close';
    close.setAttribute('aria-label', 'Tutup notifikasi');
    close.textContent = 'x';

    const progress = document.createElement('div');
    progress.className = 'flash-toast-progress';

    content.append(title, body);
    toast.append(icon, content, close, progress);
    toastStack.append(toast);
    playNotificationSound();

    const removeToast = () => {
        toast.classList.add('is-leaving');
        window.setTimeout(() => toast.remove(), 180);
    };

    close.addEventListener('click', removeToast);
    window.setTimeout(removeToast, 4600);
};

document.querySelectorAll('[data-flash-notification]').forEach((flash) => {
    showFlashToast({
        message: flash.dataset.message,
        type: flash.dataset.type || 'success',
    });
});

const resetLoadingControls = () => {
    document.querySelectorAll('form[data-loading="true"]').forEach((form) => {
        form.dataset.loading = 'false';

        form.querySelectorAll('button, input[type="submit"]').forEach((button) => {
            if (button.dataset.loadingWasDisabled !== 'true') {
                button.disabled = false;
            }

            if (button.dataset.originalText) {
                if (button.tagName === 'INPUT') {
                    button.value = button.dataset.originalText;
                } else {
                    button.textContent = button.dataset.originalText;
                }
            }

            button.classList.remove('is-loading-control');
            delete button.dataset.originalText;
            delete button.dataset.loadingWasDisabled;
        });
    });
};

const pageLoader = (() => {
    const indicator = document.querySelector('[data-page-loading]');
    let timer = null;
    let fallbackTimer = null;

    if (!indicator) {
        return {
            show() {},
            hide() {},
        };
    }

    const show = () => {
        window.clearTimeout(timer);
        window.clearTimeout(fallbackTimer);
        timer = window.setTimeout(() => {
            indicator.hidden = false;
        }, 90);
        fallbackTimer = window.setTimeout(() => {
            indicator.hidden = true;
        }, 8000);
    };

    const hide = () => {
        window.clearTimeout(timer);
        window.clearTimeout(fallbackTimer);
        indicator.hidden = true;
    };

    return { show, hide };
})();

const loadingOverlay = (() => {
    const overlay = document.querySelector('[data-loading-overlay]');

    if (!overlay) {
        return {
            show() {},
            hide() {},
        };
    }

    const title = overlay.querySelector('[data-loading-title]');
    const message = overlay.querySelector('[data-loading-message]');
    let timer = null;

    const show = (nextTitle = 'Memproses data...', nextMessage = 'Mohon tunggu sebentar.') => {
        window.clearTimeout(timer);

        if (title) {
            title.textContent = nextTitle;
        }

        if (message) {
            message.textContent = nextMessage;
        }

        timer = window.setTimeout(() => {
            overlay.hidden = false;
            document.body.setAttribute('aria-busy', 'true');
        }, 120);
    };

    const hide = () => {
        window.clearTimeout(timer);
        overlay.hidden = true;
        document.body.removeAttribute('aria-busy');
    };

    return { show, hide };
})();

window.addEventListener('pageshow', () => {
    pageLoader.hide();
    loadingOverlay.hide();
    resetLoadingControls();
});

const applySidebarState = (collapsed) => {
    document.body.dataset.sidebarCollapsed = collapsed ? 'true' : 'false';

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
        button.setAttribute('title', collapsed ? 'Perbesar sidebar' : 'Perkecil sidebar');

        const label = button.querySelector('.sidebar-label');

        if (label) {
            label.textContent = collapsed ? 'Perbesar' : 'Perkecil';
        }
    });
};

const storedSidebarState = window.localStorage.getItem('sidebar-collapsed') === 'true';

applySidebarState(storedSidebarState);

document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const collapsed = document.body.dataset.sidebarCollapsed !== 'true';

        window.localStorage.setItem('sidebar-collapsed', collapsed ? 'true' : 'false');
        applySidebarState(collapsed);
    });
});

// Generic dropdown menus (e.g. row action menus). The panel is positioned with
// `fixed` so it is never clipped by a table's horizontal-scroll container.
const closeAllDropdowns = (except = null) => {
    document.querySelectorAll('[data-dropdown-menu]').forEach((menu) => {
        if (menu === except || menu.hidden) {
            return;
        }

        menu.hidden = true;
        menu.closest('[data-dropdown]')?.querySelector('[data-dropdown-trigger]')?.setAttribute('aria-expanded', 'false');
    });
};

document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
    const trigger = dropdown.querySelector('[data-dropdown-trigger]');
    const menu = dropdown.querySelector('[data-dropdown-menu]');

    if (!trigger || !menu) {
        return;
    }

    const openMenu = () => {
        closeAllDropdowns(menu);

        const rect = trigger.getBoundingClientRect();

        menu.style.position = 'fixed';
        menu.style.top = `${Math.round(rect.bottom + 6)}px`;
        menu.style.left = 'auto';
        menu.style.right = `${Math.round(window.innerWidth - rect.right)}px`;
        menu.style.zIndex = '50';
        menu.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
    };

    trigger.addEventListener('click', (event) => {
        event.stopPropagation();

        if (menu.hidden) {
            openMenu();
        } else {
            closeAllDropdowns();
        }
    });

    menu.addEventListener('click', (event) => event.stopPropagation());
});

document.addEventListener('click', () => closeAllDropdowns());
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAllDropdowns();
    }
});
window.addEventListener('resize', () => closeAllDropdowns());
window.addEventListener('scroll', () => closeAllDropdowns(), true);

document.querySelectorAll('[data-tabs]').forEach((tabs) => {
    const storageKey = tabs.dataset.tabsStorageKey;
    const buttons = [...tabs.querySelectorAll('[data-tab-button]')];
    const panels = [...tabs.querySelectorAll('[data-tab-panel]')];

    const activate = (target) => {
        buttons.forEach((button) => {
            const isActive = button.dataset.tabButton === target;

            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            button.classList.toggle('bg-primary', isActive);
            button.classList.toggle('text-white', isActive);
            button.classList.toggle('shadow-xs', isActive);
            button.classList.toggle('text-gray-600', !isActive);
        });

        panels.forEach((panel) => {
            panel.hidden = panel.dataset.tabPanel !== target;
        });

        if (storageKey) {
            window.localStorage.setItem(storageKey, target);
        }
    };

    const firstTab = buttons[0]?.dataset.tabButton;
    const storedTab = storageKey ? window.localStorage.getItem(storageKey) : null;
    const initialTab = buttons.some((button) => button.dataset.tabButton === storedTab) ? storedTab : firstTab;

    if (initialTab) {
        activate(initialTab);
    }

    buttons.forEach((button) => {
        button.addEventListener('click', () => activate(button.dataset.tabButton));
    });
});

const flatpickrYearRange = (() => {
    const currentYear = new Date().getFullYear();

    return { min: currentYear - 100, max: currentYear + 10 };
})();

// flatpickr 4.6 ships a month dropdown but only tiny up/down arrows for the year,
// which makes jumping to a birth year very tedious. Replace the year stepper with
// a real dropdown so any year in range can be picked directly.
const syncFlatpickrYearDropdown = (instance) => {
    const monthNav = instance.calendarContainer?.querySelector('.flatpickr-current-month');

    if (!monthNav) {
        return;
    }

    let dropdown = monthNav.querySelector('.flatpickr-yearDropdown');

    if (!dropdown) {
        const yearInputWrapper = monthNav.querySelector('.numInputWrapper');

        if (yearInputWrapper) {
            yearInputWrapper.style.display = 'none';
        }

        dropdown = document.createElement('select');
        dropdown.className = 'flatpickr-yearDropdown';
        dropdown.setAttribute('aria-label', 'Pilih tahun');

        for (let year = flatpickrYearRange.max; year >= flatpickrYearRange.min; year -= 1) {
            const option = document.createElement('option');

            option.value = String(year);
            option.textContent = String(year);
            dropdown.appendChild(option);
        }

        dropdown.addEventListener('change', () => {
            instance.jumpToDate(new Date(Number(dropdown.value), instance.currentMonth, 1));
        });

        const monthDropdown = monthNav.querySelector('.flatpickr-monthDropdown-months');

        if (monthDropdown) {
            monthDropdown.after(dropdown);
        } else {
            monthNav.appendChild(dropdown);
        }
    }

    dropdown.value = String(instance.currentYear);
};

flatpickr('input[type="date"], [data-flatpickr-date]', {
    allowInput: true,
    dateFormat: 'Y-m-d',
    disableMobile: true,
    onReady: (selectedDates, dateStr, instance) => syncFlatpickrYearDropdown(instance),
    onYearChange: (selectedDates, dateStr, instance) => syncFlatpickrYearDropdown(instance),
    onMonthChange: (selectedDates, dateStr, instance) => syncFlatpickrYearDropdown(instance),
});

flatpickr('input[type="time"], [data-flatpickr-time]', {
    allowInput: true,
    dateFormat: 'H:i',
    disableMobile: true,
    enableTime: true,
    noCalendar: true,
    time_24hr: true,
});

if (typeof $.fn.select2 === 'function') {
    $('select').filter(function () {
        // Skip flatpickr's own month/year dropdowns (inside the calendar), the exit
        // modal, and the collapsible "renew contract" form (a hidden <details>).
        return !this.closest('.flatpickr-calendar')
            && !this.closest('[data-exit-modal]')
            && !this.closest('[data-renew-form]')
            && !this.closest('[data-list-modal]');
    }).each(function () {
        const $select = $(this);

        if ($select.data('select2')) {
            return;
        }

        $select.select2({
            allowClear: !$select.prop('required'),
            dropdownAutoWidth: false,
            placeholder: $select.find('option:first').text() || 'Pilih',
            width: '100%',
        });
    });
}

document.querySelectorAll('label[for]').forEach((label) => {
    const field = document.getElementById(label.getAttribute('for'));

    if (!field || field.disabled || field.type === 'hidden' || field.type === 'checkbox' || field.type === 'radio') {
        return;
    }

    if (label.querySelector('.field-requirement')) {
        return;
    }

    const marker = document.createElement('span');
    const isRequired = field.required || field.getAttribute('aria-required') === 'true';

    marker.className = isRequired ? 'field-requirement is-required' : 'field-requirement is-optional';
    marker.setAttribute('aria-label', isRequired ? 'Wajib diisi' : 'Opsional');
    marker.textContent = isRequired ? '*' : 'Opsional';
    label.append(' ', marker);
});

document.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select, textarea').forEach((field) => {
    if (field.disabled) {
        return;
    }

    if (field.id && document.querySelector(`label[for="${field.id}"]`)) {
        return;
    }

    const label = field.closest('label');

    if (!label || label.querySelector('.field-requirement')) {
        return;
    }

    const marker = document.createElement('span');
    const isRequired = field.required || field.getAttribute('aria-required') === 'true';

    marker.className = isRequired ? 'field-requirement is-required' : 'field-requirement is-optional';
    marker.setAttribute('aria-label', isRequired ? 'Wajib diisi' : 'Opsional');
    marker.textContent = isRequired ? '*' : 'Opsional';
    label.append(' ', marker);
});

// Live image preview: when a file is chosen for an [data-image-input], show it in
// the sibling [data-image-preview] and hide the [data-image-placeholder]. Clearing
// the input restores the original photo (edit) or the placeholder (create).
document.querySelectorAll('[data-image-input]').forEach((input) => {
    const field = input.closest('[data-image-field]');

    if (!field) {
        return;
    }

    const preview = field.querySelector('[data-image-preview]');
    const placeholder = field.querySelector('[data-image-placeholder]');
    const originalSrc = preview?.getAttribute('src') || '';
    let objectUrl = null;

    const restoreOriginal = () => {
        if (preview) {
            if (originalSrc) {
                preview.src = originalSrc;
                preview.hidden = false;
            } else {
                preview.removeAttribute('src');
                preview.hidden = true;
            }
        }

        if (placeholder) {
            placeholder.hidden = Boolean(originalSrc);
        }
    };

    input.addEventListener('change', () => {
        const file = input.files && input.files[0];

        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        if (!file || !file.type.startsWith('image/')) {
            restoreOriginal();
            return;
        }

        objectUrl = URL.createObjectURL(file);

        if (preview) {
            preview.src = objectUrl;
            preview.hidden = false;
        }

        if (placeholder) {
            placeholder.hidden = true;
        }
    });
});

const confirmationModal = (() => {
    const overlay = document.createElement('div');

    overlay.className = 'confirm-modal-overlay';
    overlay.hidden = true;
    overlay.innerHTML = `
        <div class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
            <div class="confirm-modal-icon" aria-hidden="true">?</div>
            <div class="confirm-modal-content">
                <h2 id="confirm-modal-title">Konfirmasi</h2>
                <p data-confirm-message>Apakah Anda yakin ingin melanjutkan?</p>
            </div>
            <div class="confirm-modal-actions">
                <button type="button" class="confirm-modal-cancel">Batal</button>
                <button type="button" class="confirm-modal-approve">Ya, lanjutkan</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    const message = overlay.querySelector('[data-confirm-message]');
    const cancelButton = overlay.querySelector('.confirm-modal-cancel');
    const approveButton = overlay.querySelector('.confirm-modal-approve');

    let activeForm = null;

    const close = () => {
        overlay.hidden = true;
        activeForm = null;
    };

    cancelButton.addEventListener('click', close);
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            close();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (!overlay.hidden && event.key === 'Escape') {
            close();
        }
    });
    approveButton.addEventListener('click', () => {
        if (!activeForm) {
            close();
            return;
        }

        activeForm.dataset.confirmed = 'true';

        if (typeof activeForm.requestSubmit === 'function') {
            activeForm.requestSubmit();
        } else {
            activeForm.submit();
        }
    });

    return {
        open(form, text, approveText) {
            activeForm = form;
            message.textContent = text;
            approveButton.textContent = approveText;
            overlay.hidden = false;
            cancelButton.focus();
        },
    };
})();

document.querySelectorAll('form').forEach((form) => {
    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    const action = form.getAttribute('action') || '';

    if (method === 'GET' || form.dataset.noConfirm === 'true' || action.includes('/login') || action.includes('/logout')) {
        return;
    }

    form.addEventListener('submit', (event) => {
        if (form.dataset.confirmed === 'true') {
            return;
        }

        event.preventDefault();

        const spoofedMethod = form.querySelector('input[name="_method"]')?.value?.toUpperCase();
        const effectiveMethod = spoofedMethod || method;
        const messages = {
            POST: ['Simpan data ini?', 'Ya, simpan'],
            PUT: ['Simpan perubahan data ini?', 'Ya, simpan'],
            PATCH: ['Simpan perubahan data ini?', 'Ya, simpan'],
            DELETE: ['Hapus data ini? Tindakan ini tidak bisa dibatalkan.', 'Ya, hapus'],
        };
        const [message, approveText] = messages[effectiveMethod] || ['Apakah Anda yakin ingin melanjutkan?', 'Ya, lanjutkan'];

        confirmationModal.open(form, form.dataset.confirmMessage || message, form.dataset.confirmApprove || approveText);
    });
});

const loadingMessageForForm = (form) => {
    const method = (form.getAttribute('method') || 'GET').toUpperCase();
    const spoofedMethod = form.querySelector('input[name="_method"]')?.value?.toUpperCase();
    const effectiveMethod = spoofedMethod || method;

    if (method === 'GET') {
        return ['Mengambil data...', 'Filter atau data halaman sedang dimuat.'];
    }

    if (effectiveMethod === 'DELETE') {
        return ['Menghapus data...', 'Mohon tunggu sampai proses hapus selesai.'];
    }

    if (effectiveMethod === 'PUT' || effectiveMethod === 'PATCH') {
        return ['Menyimpan perubahan...', 'Mohon tunggu sampai data berhasil diperbarui.'];
    }

    return ['Menyimpan data...', 'Mohon tunggu sampai data berhasil dibuat.'];
};

const markFormAsLoading = (form, submitter) => {
    if (form.dataset.loading === 'true') {
        return;
    }

    form.dataset.loading = 'true';

    const buttons = [...form.querySelectorAll('button, input[type="submit"]')];

    buttons.forEach((button) => {
        button.dataset.loadingWasDisabled = button.disabled ? 'true' : 'false';
        button.disabled = true;
    });

    if (submitter && 'dataset' in submitter) {
        submitter.dataset.originalText = submitter.tagName === 'INPUT' ? submitter.value : submitter.textContent;

        if (submitter.tagName === 'INPUT') {
            submitter.value = 'Memproses...';
        } else {
            submitter.textContent = 'Memproses...';
        }

        submitter.classList.add('is-loading-control');
    }
};

document.querySelectorAll('form').forEach((form) => {
    const action = form.getAttribute('action') || '';

    if (form.dataset.noLoading === 'true' || action.includes('/login') || action.includes('/logout')) {
        return;
    }

    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented || !form.checkValidity()) {
            return;
        }

        if (form.dataset.loading === 'true') {
            event.preventDefault();
            return;
        }

        const method = (form.getAttribute('method') || 'GET').toUpperCase();

        if (method === 'GET') {
            pageLoader.show();
            return;
        }

        const [title, message] = loadingMessageForForm(form);

        markFormAsLoading(form, event.submitter);
        loadingOverlay.show(form.dataset.loadingTitle || title, form.dataset.loadingMessage || message);
    });
});

document.querySelectorAll('[data-employee-stepper]').forEach((stepper) => {
    const form = stepper.closest('form');
    const buttons = [...stepper.querySelectorAll('[data-stepper-button]')];
    const panels = [...stepper.querySelectorAll('[data-stepper-panel]')];
    const previousButton = stepper.querySelector('[data-stepper-prev]');
    const nextButton = stepper.querySelector('[data-stepper-next]');
    const submitButton = stepper.querySelector('[data-stepper-submit]');
    const emailField = stepper.querySelector('[data-employee-email]');
    const loginEmailDisplay = stepper.querySelector('[data-login-email-display]');

    let activeStep = 0;

    const controlsFor = (panel) => [...panel.querySelectorAll('input, select, textarea')]
        .filter((control) => !control.disabled && control.type !== 'hidden');

    const syncEmailDisplay = () => {
        if (emailField && loginEmailDisplay) {
            loginEmailDisplay.value = emailField.value;
        }
    };

    const focusControl = (control) => {
        if (!control) {
            return;
        }

        if (typeof $.fn.select2 === 'function' && $(control).data('select2')) {
            $(control).select2('open');
            return;
        }

        control.focus({ preventScroll: true });
    };

    const activate = (step) => {
        activeStep = Math.min(Math.max(step, 0), panels.length - 1);

        buttons.forEach((button) => {
            const isActive = Number(button.dataset.stepperButton) === activeStep;
            const relatedPanel = panels[Number(button.dataset.stepperButton)];
            // Use the `validity` state (a property read) instead of `checkValidity()`,
            // which dispatches an `invalid` event. The form has a capturing `invalid`
            // listener that calls activate(), so calling checkValidity() here would
            // recurse infinitely on the create page (all required fields start empty).
            const hasInvalidField = relatedPanel ? controlsFor(relatedPanel).some((control) => !control.validity.valid) : false;

            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            button.classList.toggle('bg-primary', isActive);
            button.classList.toggle('text-white', isActive);
            button.classList.toggle('shadow-xs', isActive);
            button.classList.toggle('text-gray-600', !isActive);
            button.classList.toggle('ring-1', hasInvalidField && !isActive);
            button.classList.toggle('ring-red-200', hasInvalidField && !isActive);
            button.classList.toggle('text-red-700', hasInvalidField && !isActive);
            button.classList.toggle('bg-red-50', hasInvalidField && !isActive);

            const number = button.querySelector('span');

            if (number) {
                number.classList.toggle('text-white/70', isActive);
                number.classList.toggle('text-gray-400', !isActive && !hasInvalidField);
                number.classList.toggle('text-red-500', hasInvalidField && !isActive);
            }
        });

        panels.forEach((panel) => {
            panel.hidden = Number(panel.dataset.stepperPanel) !== activeStep;
        });

        if (previousButton) {
            previousButton.hidden = activeStep === 0;
        }

        if (nextButton) {
            nextButton.hidden = activeStep === panels.length - 1;
        }

        if (submitButton) {
            submitButton.hidden = activeStep !== panels.length - 1;
        }
    };

    const firstInvalidControl = (panel) => controlsFor(panel).find((control) => !control.checkValidity());

    const validateStep = (step) => {
        const invalidControl = firstInvalidControl(panels[step]);

        if (!invalidControl) {
            return true;
        }

        activate(step);
        window.setTimeout(() => {
            invalidControl.reportValidity();
            focusControl(invalidControl);
        }, 0);

        return false;
    };

    const validateThrough = (targetStep) => {
        const direction = targetStep > activeStep ? 1 : -1;

        if (direction < 0) {
            return true;
        }

        for (let step = 0; step < targetStep; step += 1) {
            if (!validateStep(step)) {
                return false;
            }
        }

        return true;
    };

    const validateAll = () => {
        for (let step = 0; step < panels.length; step += 1) {
            if (!validateStep(step)) {
                return false;
            }
        }

        return true;
    };

    const firstPanelWithServerError = () => panels.find((panel) => panel.querySelector('.text-red-600'));

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetStep = Number(button.dataset.stepperButton);

            if (validateThrough(targetStep)) {
                activate(targetStep);
            }
        });
    });

    previousButton?.addEventListener('click', () => activate(activeStep - 1));
    nextButton?.addEventListener('click', () => {
        if (validateStep(activeStep)) {
            activate(activeStep + 1);
        }
    });
    emailField?.addEventListener('input', syncEmailDisplay);

    form?.addEventListener('invalid', (event) => {
        const panel = event.target.closest('[data-stepper-panel]');

        if (panel) {
            activate(Number(panel.dataset.stepperPanel));
        }
    }, true);

    form?.addEventListener('submit', (event) => {
        if (form.dataset.confirmed === 'true') {
            return;
        }

        if (!validateAll()) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    }, true);

    syncEmailDisplay();
    activate(Number(firstPanelWithServerError()?.dataset.stepperPanel ?? 0));
});

document.querySelectorAll('[data-placement-form]').forEach((form) => {
    const branchSelect = form.querySelector('[data-placement-branch]');
    const departmentSelect = form.querySelector('[data-placement-department]');
    const positionSelect = form.querySelector('[data-placement-position]');

    if (!branchSelect || !departmentSelect || !positionSelect) {
        return;
    }

    const catalog = JSON.parse(form.dataset.placementCatalog || '{"branches":{},"departments":{}}');
    const departmentOptions = [...departmentSelect.querySelectorAll('[data-placement-department-option]')];
    const positionOptions = [...positionSelect.querySelectorAll('[data-placement-position-option]')];

    const syncPositions = () => {
        const allowedPositions = catalog.departments[departmentSelect.value]?.map(String) ?? [];

        positionOptions.forEach((option) => {
            const isAllowed = !departmentSelect.value || allowedPositions.includes(option.value);

            option.hidden = !isAllowed;
            option.disabled = !isAllowed;
        });

        if (positionSelect.value && positionSelect.selectedOptions[0]?.disabled) {
            positionSelect.value = '';
        }

        $(positionSelect).trigger('change.select2');
    };

    const syncDepartments = () => {
        const allowedDepartments = catalog.branches[branchSelect.value]?.map(String) ?? [];

        departmentOptions.forEach((option) => {
            const isAllowed = !branchSelect.value || allowedDepartments.includes(option.value);

            option.hidden = !isAllowed;
            option.disabled = !isAllowed;
        });

        if (departmentSelect.value && departmentSelect.selectedOptions[0]?.disabled) {
            departmentSelect.value = '';
        }

        $(departmentSelect).trigger('change.select2');
        syncPositions();
    };

    branchSelect.addEventListener('change', syncDepartments);
    departmentSelect.addEventListener('change', syncPositions);

    syncDepartments();
});

// Inline exit verification: when an active employee's contract status is changed to
// a "closing" value and the form is saved, ask for the exit reason/date/notes in a
// modal and process the exit together with the edit — no need to open the detail page.
document.querySelectorAll('[data-exit-form]').forEach((stepper) => {
    const form = stepper.closest('form');
    const modal = stepper.querySelector('[data-exit-modal]');

    if (!form || !modal || stepper.dataset.exitActive !== 'true') {
        return;
    }

    const closingStatuses = JSON.parse(stepper.dataset.exitClosingStatuses || '[]');
    const contractStatus = stepper.querySelector('#contract_status');
    const reasonField = modal.querySelector('#exit_reason');
    const dateField = modal.querySelector('#exit_date');
    const confirmButton = modal.querySelector('[data-exit-confirm]');
    const cancelButton = modal.querySelector('[data-exit-cancel]');

    let exitConfirmed = false;

    const isClosing = () => Boolean(contractStatus) && closingStatuses.includes(contractStatus.value);
    const openModal = () => { modal.hidden = false; };
    const closeModal = () => { modal.hidden = true; };

    // Runs in the capture phase after the stepper's own validation handler, so all
    // steps are validated first. If the contract is being closed, show the exit modal
    // instead of submitting straight away.
    form.addEventListener('submit', (event) => {
        if (exitConfirmed || form.dataset.confirmed === 'true' || !isClosing()) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        openModal();
    }, true);

    confirmButton?.addEventListener('click', () => {
        if (!reasonField?.value) {
            reasonField?.focus();
            return;
        }

        if (!dateField?.value) {
            dateField?.reportValidity?.();
            dateField?.focus();
            return;
        }

        exitConfirmed = true;
        form.dataset.confirmed = 'true'; // skip the generic "Simpan perubahan?" confirmation
        closeModal();

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });

    cancelButton?.addEventListener('click', () => {
        closeModal();
        exitConfirmed = false;

        // Revert the contract status back to active so the user isn't forced to exit.
        if (contractStatus) {
            contractStatus.value = 'active';

            if (typeof $ === 'function') {
                $(contractStatus).trigger('change.select2');
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!modal.hidden && event.key === 'Escape') {
            cancelButton?.click();
        }
    });
});

// PKWTT (permanent) contracts don't need an end date; every other type does.
// Toggle the `required` attribute and the required/optional marker on the linked
// end-date field whenever the contract type changes.
document.querySelectorAll('[data-contract-type-toggle]').forEach((select) => {
    const endField = document.querySelector(select.dataset.contractTypeToggle);

    if (!endField) {
        return;
    }

    const label = endField.id
        ? document.querySelector(`label[for="${endField.id}"]`)
        : endField.closest('label');

    const syncRequirement = () => {
        const required = select.value !== 'PKWTT';

        endField.required = required;

        if (label) {
            let marker = label.querySelector('.field-requirement');

            if (!marker) {
                marker = document.createElement('span');
                label.append(' ', marker);
            }

            marker.className = required ? 'field-requirement is-required' : 'field-requirement is-optional';
            marker.setAttribute('aria-label', required ? 'Wajib diisi' : 'Opsional');
            marker.textContent = required ? '*' : 'Opsional';
        }
    };

    select.addEventListener('change', syncRequirement);
    syncRequirement();
});

// List-page action modals: renew/reactivate contract and process exit, opened from
// the row action menu so these don't require visiting the detail page.
(() => {
    const renewModal = document.querySelector('[data-list-modal="renew"]');
    const exitModal = document.querySelector('[data-list-modal="exit"]');

    if (!renewModal && !exitModal) {
        return;
    }

    const open = (modal) => {
        if (modal) {
            modal.hidden = false;
        }
    };

    const close = (modal) => {
        if (modal) {
            modal.hidden = true;
        }
    };

    const renewCopy = {
        renew: {
            heading: 'Perpanjang Kontrak',
            desc: 'Kontrak baru dibuat sebagai kontrak aktif. Kontrak sebelumnya ditandai "Diperpanjang".',
            submit: 'Simpan Kontrak Baru',
        },
        reactivate: {
            heading: 'Aktifkan Kembali',
            desc: 'Karyawan diaktifkan kembali (status Aktif) dan kontrak baru dibuat sebagai kontrak aktif.',
            submit: 'Aktifkan Kembali & Simpan',
        },
    };

    document.querySelectorAll('[data-open-renew]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!renewModal) {
                return;
            }

            const mode = button.dataset.mode === 'reactivate' ? 'reactivate' : 'renew';

            renewModal.querySelector('[data-list-renew-form]').action = button.dataset.url;
            renewModal.querySelector('[data-renew-heading]').textContent = renewCopy[mode].heading;
            renewModal.querySelector('[data-renew-name]').textContent = ' — ' + button.dataset.name;
            renewModal.querySelector('[data-renew-desc]').textContent = renewCopy[mode].desc;
            renewModal.querySelector('[data-renew-submit]').textContent = renewCopy[mode].submit;

            closeAllDropdowns();
            open(renewModal);
        });
    });

    document.querySelectorAll('[data-open-exit]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!exitModal) {
                return;
            }

            exitModal.querySelector('[data-list-exit-form]').action = button.dataset.url;
            exitModal.querySelector('[data-exit-name]').textContent = ' — ' + button.dataset.name;

            closeAllDropdowns();
            open(exitModal);
        });
    });

    [renewModal, exitModal].forEach((modal) => {
        if (!modal) {
            return;
        }

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                close(modal);
            }
        });

        modal.querySelectorAll('[data-modal-close]').forEach((button) => {
            button.addEventListener('click', () => close(modal));
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        if (renewModal && !renewModal.hidden) {
            close(renewModal);
        }

        if (exitModal && !exitModal.hidden) {
            close(exitModal);
        }
    });
})();

// Editing an employee who has left: if the contract status is set to an active/
// ongoing value, saving will reactivate them. Surface that in the confirmation
// dialog so it's clear the save also brings the employee back to "Aktif".
document.querySelectorAll('[data-reactivate-active="true"]').forEach((stepper) => {
    const form = stepper.closest('form');
    const contractStatus = stepper.querySelector('#contract_status');

    if (!form || !contractStatus) {
        return;
    }

    const closingStatuses = JSON.parse(stepper.dataset.reactivateClosingStatuses || '[]');

    const syncConfirmMessage = () => {
        if (! closingStatuses.includes(contractStatus.value)) {
            form.dataset.confirmMessage = 'Aktifkan kembali karyawan ini? Status karyawan akan menjadi Aktif dan kontrak baru disimpan.';
            form.dataset.confirmApprove = 'Ya, aktifkan kembali';
        } else {
            delete form.dataset.confirmMessage;
            delete form.dataset.confirmApprove;
        }
    };

    contractStatus.addEventListener('change', syncConfirmMessage);
    syncConfirmMessage();
});
