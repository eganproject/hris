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
    loadingOverlay.hide();
    resetLoadingControls();
});
window.addEventListener('beforeunload', () => {
    loadingOverlay.show('Memuat halaman...', 'Menyiapkan tampilan berikutnya.');
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

flatpickr('input[type="date"], [data-flatpickr-date]', {
    allowInput: true,
    dateFormat: 'Y-m-d',
    disableMobile: true,
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
    $('select').each(function () {
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

        const [title, message] = loadingMessageForForm(form);

        markFormAsLoading(form, event.submitter);
        loadingOverlay.show(form.dataset.loadingTitle || title, form.dataset.loadingMessage || message);
    });
});

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');

    if (!link || event.defaultPrevented) {
        return;
    }

    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) {
        return;
    }

    if (link.target && link.target !== '_self') {
        return;
    }

    if (link.hasAttribute('download') || link.dataset.noLoading === 'true') {
        return;
    }

    const url = new URL(link.href, window.location.href);

    if (url.origin !== window.location.origin || url.href === window.location.href || url.hash && url.pathname === window.location.pathname) {
        return;
    }

    loadingOverlay.show(link.dataset.loadingTitle || 'Memuat data...', link.dataset.loadingMessage || 'Menyiapkan halaman yang dipilih.');
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
            const hasInvalidField = relatedPanel ? controlsFor(relatedPanel).some((control) => !control.checkValidity()) : false;

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
            activate(Number(button.dataset.stepperButton));
        });
    });

    previousButton?.addEventListener('click', () => activate(activeStep - 1));
    nextButton?.addEventListener('click', () => activate(activeStep + 1));
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
