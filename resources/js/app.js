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

// Keep the topbar notification badge fresh without a full reload by polling the
// lightweight unread-count endpoint every minute.
(() => {
    const badge = document.querySelector('[data-notif-count]');
    const url = badge?.dataset.notifPoll;

    if (!badge || !url) {
        return;
    }

    const refresh = async () => {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            if (!response.ok) {
                return;
            }

            const { count } = await response.json();

            if (count > 0) {
                badge.textContent = count > 9 ? '9+' : String(count);
                badge.hidden = false;
            } else {
                badge.hidden = true;
            }
        } catch (_) {
            // Network hiccup — try again on the next tick.
        }
    };

    window.setInterval(refresh, 60000);
})();

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

// Collapsible sidebar groups (single-open accordion) with smooth, exact-height
// animation and memory. The group that owns the active page is always opened so the
// current location stays visible; on pages that belong to no group (Dashboard,
// Laporan) the last manually-opened group is restored from localStorage. Height is
// animated to the real content height for a smooth open/close. Icon-only mode forces
// everything visible via CSS (!important beats the inline max-height set here).
(() => {
    const STORAGE_KEY = 'sidebar-open-group';
    const nav = document.querySelector('.sidebar-nav');
    const groups = Array.from(document.querySelectorAll('[data-sidebar-group]'));

    if (!groups.length) {
        return;
    }

    const itemsOf = (group) => group.querySelector('[data-sidebar-group-items]');

    const setOpen = (group, open, animate) => {
        const items = itemsOf(group);
        const toggle = group.querySelector('[data-sidebar-group-toggle]');

        if (!items) {
            return;
        }

        toggle?.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            group.classList.add('is-open');

            if (!animate) {
                items.style.maxHeight = 'none';
                return;
            }

            items.style.maxHeight = `${items.scrollHeight}px`;
            items.addEventListener('transitionend', function done(event) {
                if (event.target === items && event.propertyName === 'max-height') {
                    // Release the cap so the group can grow if its contents change.
                    items.style.maxHeight = 'none';
                    items.removeEventListener('transitionend', done);
                }
            });
        } else {
            group.classList.remove('is-open');

            if (!animate) {
                items.style.maxHeight = '0px';
                return;
            }

            // From an uncapped height, pin the current height, force a reflow, then
            // collapse to 0 so the transition has two concrete values to animate.
            items.style.maxHeight = `${items.scrollHeight}px`;
            void items.offsetHeight;
            items.style.maxHeight = '0px';
        }
    };

    const openOnly = (group, animate) => {
        groups.forEach((candidate) => setOpen(candidate, candidate === group, animate));
    };

    // Initial state (no animation): honour the remembered open/closed choice. Only on
    // the very first visit (nothing stored yet) do we fall back to the group that owns
    // the active page, which the server rendered open.
    const activeGroup = () => groups.find((group) => group.classList.contains('is-open')) || null;
    const storedKey = window.localStorage.getItem(STORAGE_KEY);

    if (storedKey === null) {
        openOnly(activeGroup(), false);
    } else if (storedKey === '') {
        openOnly(null, false);
    } else {
        const remembered = groups.find((group) => group.dataset.sidebarGroup === storedKey) || null;
        // If the remembered group isn't available here (e.g. permissions), fall back.
        openOnly(remembered || activeGroup(), false);
    }

    // Enable transitions only after the initial state is painted.
    requestAnimationFrame(() => nav?.classList.add('is-ready'));

    groups.forEach((group) => {
        group.querySelector('[data-sidebar-group-toggle]')?.addEventListener('click', () => {
            const willOpen = !group.classList.contains('is-open');

            if (willOpen) {
                openOnly(group, true);
                window.localStorage.setItem(STORAGE_KEY, group.dataset.sidebarGroup || '');
            } else {
                setOpen(group, false, true);
                window.localStorage.setItem(STORAGE_KEY, '');
            }
        });
    });
})();

// Mobile navigation drawer: the hamburger opens the sidebar; tapping the overlay,
// a menu link, Escape, or resizing to desktop closes it.
(() => {
    const openNav = () => document.body.setAttribute('data-mobile-nav', 'open');
    const closeNav = () => document.body.removeAttribute('data-mobile-nav');

    document.querySelectorAll('[data-mobile-nav-toggle]').forEach((button) => button.addEventListener('click', openNav));
    document.querySelectorAll('[data-mobile-nav-overlay]').forEach((overlay) => overlay.addEventListener('click', closeNav));
    document.querySelectorAll('.admin-sidebar a').forEach((link) => link.addEventListener('click', closeNav));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeNav();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) {
            closeNav();
        }
    });
})();

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

        // Reveal first so the height can be measured, then decide the direction.
        menu.style.position = 'fixed';
        menu.style.left = 'auto';
        menu.style.zIndex = '50';
        menu.hidden = false;

        const rect = trigger.getBoundingClientRect();
        const menuHeight = menu.offsetHeight;
        const spaceBelow = window.innerHeight - rect.bottom;

        // Flip upward when the menu would overflow below the viewport (e.g. the last
        // row on a page that can't scroll further) and there is more room above.
        const openUp = spaceBelow < menuHeight + 12 && rect.top > spaceBelow;

        menu.style.top = openUp
            ? `${Math.round(Math.max(6, rect.top - menuHeight - 6))}px`
            : `${Math.round(rect.bottom + 6)}px`;
        menu.style.right = `${Math.round(window.innerWidth - rect.right)}px`;
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

const dateBaseOptions = {
    allowInput: true,
    dateFormat: 'Y-m-d',
    disableMobile: true,
    onReady: (selectedDates, dateStr, instance) => syncFlatpickrYearDropdown(instance),
    onYearChange: (selectedDates, dateStr, instance) => syncFlatpickrYearDropdown(instance),
    onMonthChange: (selectedDates, dateStr, instance) => syncFlatpickrYearDropdown(instance),
};

const timeBaseOptions = {
    allowInput: true,
    dateFormat: 'H:i',
    disableMobile: true,
    enableTime: true,
    noCalendar: true,
    time_24hr: true,
};

// A native <dialog> opened with showModal() renders in the browser's top
// layer, above everything else regardless of z-index. flatpickr's default
// calendar is appended to <body> (outside the top layer), so inside a dialog
// it would be painted *behind* the modal. `static: true` renders the calendar
// inline within the field's wrapper — i.e. inside the dialog — so it stacks
// above the modal. Fields outside a dialog keep the default floating calendar.
const initFlatpickr = (selector, baseOptions) => {
    document.querySelectorAll(selector).forEach((element) => {
        flatpickr(element, {
            ...baseOptions,
            static: Boolean(element.closest('dialog')),
        });
    });
};

initFlatpickr('input[type="date"], [data-flatpickr-date]', dateBaseOptions);
initFlatpickr('input[type="time"], [data-flatpickr-time]', timeBaseOptions);

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

        // A native <dialog> opened with showModal() renders in the browser's
        // top layer. select2's default body-level dropdown is painted *behind*
        // the dialog there, so render the dropdown inside the dialog instead —
        // that keeps it within the top layer and above the modal.
        const dialog = this.closest('dialog');

        $select.select2({
            allowClear: !$select.prop('required'),
            dropdownAutoWidth: false,
            dropdownParent: dialog ? $(dialog) : $(document.body),
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

// Role matrix (Kontrol Akses): the checkbox next to a menu name ticks/unticks every
// action available on that row, and reflects whether the row is already complete.
document.querySelectorAll('[data-role-matrix] [data-row-toggle]').forEach((toggle) => {
    const row = toggle.closest('tr');

    if (!row) {
        return;
    }

    const cells = row.querySelectorAll('input[name="permissions[]"]');

    const syncToggle = () => {
        const checked = [...cells].filter((cell) => cell.checked).length;

        toggle.checked = checked === cells.length && cells.length > 0;
        toggle.indeterminate = checked > 0 && checked < cells.length;
    };

    toggle.addEventListener('change', () => {
        cells.forEach((cell) => {
            if (!cell.disabled) {
                cell.checked = toggle.checked;
            }
        });
    });

    cells.forEach((cell) => cell.addEventListener('change', syncToggle));
    syncToggle();
});

// Generic instant list filter. A search input [data-list-filter="<scope>"] (and an
// optional select [data-list-filter-select="<scope>"]) hide every [data-filter-item]
// inside [data-filter-scope="<scope>"] that doesn't match. Matching is against the
// row's data-filter-text (search) and data-filter-tags (select, comma-separated).
document.querySelectorAll('[data-filter-scope]').forEach((scope) => {
    const key = scope.dataset.filterScope;
    const input = document.querySelector(`[data-list-filter="${key}"]`);
    const select = document.querySelector(`[data-list-filter-select="${key}"]`);
    const items = [...scope.querySelectorAll('[data-filter-item]')];
    const empty = scope.querySelector('[data-filter-empty]');

    if (!input && !select) {
        return;
    }

    const apply = () => {
        const q = (input?.value || '').trim().toLowerCase();
        const tag = select?.value || '';
        let shown = 0;

        items.forEach((item) => {
            const text = (item.dataset.filterText || '').toLowerCase();
            const tags = (item.dataset.filterTags || '').split(',');
            const matchText = !q || text.includes(q);
            const matchTag = !tag || tags.includes(tag);
            const visible = matchText && matchTag;

            item.hidden = !visible;
            if (visible) shown += 1;
        });

        if (empty) empty.hidden = shown !== 0;
    };

    input?.addEventListener('input', apply);
    select?.addEventListener('change', apply);
    apply();
});

// Show/hide password: [data-password-toggle="<input id>"] flips that input between
// password and text, so a typed password can be checked before saving.
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);

    if (!input) {
        return;
    }

    const eye = button.querySelector('[data-password-show]');
    const eyeOff = button.querySelector('[data-password-hide]');

    button.addEventListener('click', () => {
        const revealed = input.type === 'text';

        input.type = revealed ? 'password' : 'text';
        button.setAttribute('aria-pressed', String(!revealed));
        button.setAttribute('aria-label', revealed ? 'Tampilkan password' : 'Sembunyikan password');

        if (eye) {
            eye.hidden = !revealed;
        }

        if (eyeOff) {
            eyeOff.hidden = revealed;
        }

        input.focus();
    });
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

    // Forms inside a native <dialog> are skipped: showModal() puts the dialog in the
    // browser top layer, above the confirmation overlay, so the overlay would be
    // hidden behind it. Modal forms are a deliberate action and don't need it anyway.
    if (method === 'GET' || form.dataset.noConfirm === 'true' || form.closest('dialog') || action.includes('/login') || action.includes('/logout')) {
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
    const positionSelect = form.querySelector('[data-placement-position]');
    const departmentOptions = [...form.querySelectorAll('[data-department-option]')];

    if (!branchSelect || !positionSelect || departmentOptions.length === 0) {
        return;
    }

    const catalog = JSON.parse(form.dataset.placementCatalog || '{"branches":{},"departments":{}}');
    const positionOptions = [...positionSelect.querySelectorAll('[data-placement-position-option]')];

    // Only the divisions available at the chosen branch may be checked.
    const syncDepartments = () => {
        const allowed = catalog.branches[branchSelect.value]?.map(String) ?? [];

        departmentOptions.forEach((option) => {
            const id = option.dataset.departmentId;
            const isAllowed = !branchSelect.value || allowed.includes(id);
            const box = option.querySelector('input[type="checkbox"]');

            option.hidden = !isAllowed;
            if (box) {
                box.disabled = !isAllowed;
                if (!isAllowed) box.checked = false;
            }
        });
    };

    // Checked divisions (all equal).
    const selectedDepartmentIds = () => departmentOptions
        .map((option) => option.querySelector('input[type="checkbox"]'))
        .filter((box) => box && box.checked && !box.disabled)
        .map((box) => box.value);

    // Jabatan diambil dari gabungan divisi yang dicentang.
    const syncPositions = () => {
        const deptIds = selectedDepartmentIds();
        const allowed = new Set();
        deptIds.forEach((id) => (catalog.departments[id] ?? []).forEach((pid) => allowed.add(String(pid))));

        positionOptions.forEach((option) => {
            const isAllowed = deptIds.length === 0 || allowed.has(option.value);

            option.hidden = !isAllowed;
            option.disabled = !isAllowed;
        });

        if (positionSelect.value && positionSelect.selectedOptions[0]?.disabled) {
            positionSelect.value = '';
        }

        $(positionSelect).trigger('change.select2');
    };

    branchSelect.addEventListener('change', () => { syncDepartments(); syncPositions(); });
    departmentOptions.forEach((option) => {
        const box = option.querySelector('input[type="checkbox"]');
        if (box) box.addEventListener('change', syncPositions);
    });

    syncDepartments();
    syncPositions();
});

// Inline exit verification: when the "Status Kepegawaian" field is set to "Nonaktif"
// and the form is saved, ask for the exit reason/date/notes in a modal and process
// the exit together with the save — no need to open the detail page.
document.querySelectorAll('[data-exit-form]').forEach((stepper) => {
    const form = stepper.closest('form');
    const modal = stepper.querySelector('[data-exit-modal]');

    if (!form || !modal || stepper.dataset.exitActive !== 'true') {
        return;
    }

    const statusSelect = stepper.querySelector('#employment_status');
    const reasonField = modal.querySelector('#exit_reason');
    const dateField = modal.querySelector('#exit_date');
    const confirmButton = modal.querySelector('[data-exit-confirm]');
    const cancelButton = modal.querySelector('[data-exit-cancel]');

    let exitConfirmed = false;

    const isExiting = () => Boolean(statusSelect) && statusSelect.value === 'inactive';
    const openModal = () => { modal.hidden = false; };
    const closeModal = () => { modal.hidden = true; };

    // Runs in the capture phase after the stepper's own validation handler, so all
    // steps are validated first. If the status is being set to Nonaktif, show the
    // exit modal instead of submitting straight away.
    form.addEventListener('submit', (event) => {
        if (exitConfirmed || form.dataset.confirmed === 'true' || !isExiting()) {
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

        // Revert the status back to Aktif so the user isn't forced to exit.
        if (statusSelect) {
            statusSelect.value = 'active';

            if (typeof $ === 'function') {
                $(statusSelect).trigger('change.select2');
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

// Editing an employee who has left: if the status is set back to "Aktif", saving
// will reactivate them. Surface that in the confirmation dialog so it's clear the
// save also brings the employee back to "Aktif".
document.querySelectorAll('[data-reactivate-active="true"]').forEach((stepper) => {
    const form = stepper.closest('form');
    const statusSelect = stepper.querySelector('#employment_status');

    if (!form || !statusSelect) {
        return;
    }

    const syncConfirmMessage = () => {
        if (statusSelect.value === 'active') {
            form.dataset.confirmMessage = 'Aktifkan kembali karyawan ini? Status karyawan akan menjadi Aktif.';
            form.dataset.confirmApprove = 'Ya, aktifkan kembali';
        } else {
            delete form.dataset.confirmMessage;
            delete form.dataset.confirmApprove;
        }
    };

    statusSelect.addEventListener('change', syncConfirmMessage);
    syncConfirmMessage();
});

// Global quick-search (command palette): Ctrl/Cmd+K or "/" opens an employee search
// overlay from anywhere. Results come from the scoped /search endpoint.
(() => {
    const overlay = document.querySelector('[data-search-overlay]');

    if (!overlay) {
        return;
    }

    const input = overlay.querySelector('[data-search-input]');
    const results = overlay.querySelector('[data-search-results]');
    const endpoint = overlay.querySelector('[data-search-endpoint]')?.dataset.searchEndpoint;

    let items = [];
    let active = -1;
    let timer = null;

    const hint = (text) => {
        results.innerHTML = '';
        const p = document.createElement('p');
        p.className = 'px-4 py-8 text-center text-sm text-gray-400';
        p.textContent = text;
        results.append(p);
    };

    const open = () => {
        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
        input.value = '';
        items = [];
        active = -1;
        hint('Ketik minimal 2 huruf untuk mencari karyawan.');
        window.setTimeout(() => input.focus(), 0);
    };

    const close = () => {
        overlay.hidden = true;
        document.body.style.overflow = '';
        items = [];
        active = -1;
    };

    const highlight = () => {
        [...results.querySelectorAll('[data-search-result]')].forEach((el, i) => {
            el.classList.toggle('bg-primary-soft', i === active);
        });
        results.querySelector(`[data-search-result="${active}"]`)?.scrollIntoView({ block: 'nearest' });
    };

    const render = (employees) => {
        items = employees;
        active = employees.length ? 0 : -1;

        if (!employees.length) {
            hint('Tidak ada karyawan yang cocok.');
            return;
        }

        results.innerHTML = '';

        employees.forEach((employee, index) => {
            const link = document.createElement('a');
            link.href = employee.url;
            link.dataset.searchResult = String(index);
            link.className = 'flex items-center gap-3 border-b border-gray-50 px-4 py-2.5 transition hover:bg-primary-soft';

            const avatar = document.createElement('span');
            avatar.className = 'flex size-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-[11px] font-semibold text-gray-700';
            avatar.textContent = (employee.name || '?').slice(0, 1).toUpperCase();

            const info = document.createElement('span');
            info.className = 'min-w-0 flex-1';
            const name = document.createElement('span');
            name.className = 'block truncate text-[13px] font-medium text-gray-900';
            name.textContent = employee.name;
            const meta = document.createElement('span');
            meta.className = 'block truncate text-xs text-gray-500';
            meta.textContent = [employee.number, employee.position, employee.branch].filter(Boolean).join(' · ');
            info.append(name, meta);

            link.append(avatar, info);

            if (!employee.active) {
                const badge = document.createElement('span');
                badge.className = 'shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500';
                badge.textContent = 'Nonaktif';
                link.append(badge);
            }

            results.append(link);
        });

        highlight();
    };

    const search = async (term) => {
        if (!endpoint) {
            return;
        }

        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('q', term);

            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            render(data.employees || []);
        } catch (_) {
            // Network hiccup — leave the previous results in place.
        }
    };

    input?.addEventListener('input', () => {
        const term = input.value.trim();
        window.clearTimeout(timer);

        if (term.length < 2) {
            items = [];
            active = -1;
            hint('Ketik minimal 2 huruf untuk mencari karyawan.');
            return;
        }

        timer = window.setTimeout(() => search(term), 220);
    });

    input?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (items.length) {
                active = (active + 1) % items.length;
                highlight();
            }
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (items.length) {
                active = (active - 1 + items.length) % items.length;
                highlight();
            }
        } else if (event.key === 'Enter') {
            if (active >= 0 && items[active]) {
                event.preventDefault();
                window.location.href = items[active].url;
            }
        }
    });

    document.querySelectorAll('[data-search-open]').forEach((button) => button.addEventListener('click', open));
    overlay.addEventListener('click', (event) => {
        if (event.target === overlay) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        const typing = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement?.tagName || '') || document.activeElement?.isContentEditable;

        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            overlay.hidden ? open() : close();
        } else if (event.key === '/' && overlay.hidden && !typing) {
            event.preventDefault();
            open();
        } else if (event.key === 'Escape' && !overlay.hidden) {
            close();
        }
    });
})();

// Generic bulk-approve: inside a [data-approve-scope] container, ticking row
// checkboxes reveals a bar whose "approve" button posts the selected ids to the
// bulk-approve endpoint. Mirrors the employees bulk-action pattern.
document.querySelectorAll('[data-approve-scope]').forEach((scope) => {
    const boxes = () => [...scope.querySelectorAll('[data-approve-checkbox]')];
    const all = scope.querySelector('[data-approve-all]');
    const bar = scope.querySelector('[data-approve-bar]');
    const countEls = scope.querySelectorAll('[data-approve-count]');
    const form = scope.querySelector('[data-approve-form]');
    const idsHolder = form?.querySelector('[data-approve-ids]');
    const submitBtn = scope.querySelector('[data-approve-submit]');
    const clearBtn = scope.querySelector('[data-approve-clear]');

    if (!form || !idsHolder) {
        return;
    }

    const selected = () => boxes().filter((box) => box.checked);

    const sync = () => {
        const n = selected().length;

        countEls.forEach((el) => (el.textContent = String(n)));

        if (bar) {
            bar.hidden = n === 0;
        }

        if (all) {
            const total = boxes().length;
            all.checked = n > 0 && n === total;
            all.indeterminate = n > 0 && n < total;
        }
    };

    all?.addEventListener('change', () => {
        boxes().forEach((box) => (box.checked = all.checked));
        sync();
    });

    boxes().forEach((box) => box.addEventListener('change', sync));

    clearBtn?.addEventListener('click', () => {
        boxes().forEach((box) => (box.checked = false));
        if (all) {
            all.checked = false;
            all.indeterminate = false;
        }
        sync();
    });

    submitBtn?.addEventListener('click', () => {
        const ids = selected().map((box) => box.value);

        if (!ids.length) {
            return;
        }

        idsHolder.innerHTML = '';
        ids.forEach((id) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            idsHolder.append(input);
        });

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.submit();
        }
    });

    sync();
});

// ── Jadwal Kerja (roster): input jadwal tanpa reload halaman ────────────────
// Override sel, generate ulang, dan hapus penugasan dikirim lewat fetch; server
// membalas JSON (sel dirender dari partial yang sama dengan grid) sehingga hanya
// bagian yang berubah yang disentuh.
(() => {
    const grid = document.querySelector('[data-roster-grid]');

    if (!grid) {
        return;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const send = async (url, body) => {
        const response = await fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        });

        let payload = null;

        try {
            payload = await response.json();
        } catch {
            // Bukan JSON (mis. halaman error) — pakai pesan umum di bawah.
        }

        if (!response.ok) {
            throw new Error(
                payload?.message
                    || Object.values(payload?.errors || {}).flat()[0]
                    || 'Terjadi kesalahan. Coba lagi.',
            );
        }

        return payload || {};
    };

    // Roster bisa berubah menyeluruh setelah generate: ambil ulang halaman ini lalu
    // tukar isi gridnya saja.
    const refreshGrid = async () => {
        const response = await fetch(window.location.href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        const fresh = new DOMParser()
            .parseFromString(await response.text(), 'text/html')
            .querySelector('[data-roster-grid]');

        if (fresh) {
            grid.innerHTML = fresh.innerHTML;
        }
    };

    // --- Dialog "Ubah Jadwal Harian" ---------------------------------------
    const dialog = document.getElementById('override-dialog');
    const overrideForm = document.querySelector('[data-override-form]');

    if (dialog && overrideForm) {
        const field = (id) => document.getElementById(id);
        const dayOff = field('ov-day-off');
        const shift = field('ov-shift');
        const shiftWrap = field('ov-shift-wrap');
        const wfh = field('ov-wfh');
        let activeSlot = null;

        const syncOff = () => {
            shiftWrap.style.display = dayOff.checked ? 'none' : '';
            shift.disabled = dayOff.checked;
            // Shift wajib dipilih kecuali hari ini ditandai libur.
            shift.required = !dayOff.checked;

            // WFH tidak berlaku pada hari libur.
            if (dayOff.checked) {
                wfh.checked = false;
            }

            wfh.disabled = dayOff.checked;
        };

        // Didelegasikan: isi sel diganti setelah AJAX, jadi jangan ikat per elemen.
        grid.addEventListener('click', (event) => {
            const cell = event.target.closest('[data-cell]');

            if (!cell) {
                return;
            }

            activeSlot = cell.closest('[data-cell-slot]');
            field('ov-employee-id').value = cell.dataset.emp;
            field('ov-work-date').value = cell.dataset.date;
            field('ov-emp-name').textContent = cell.dataset.empName;
            field('ov-date-label').textContent = cell.dataset.dateLabel;
            dayOff.checked = cell.dataset.off === '1';
            shift.value = cell.dataset.shift || '';
            wfh.checked = cell.dataset.wfh === '1';
            field('ov-note').value = '';
            // Ingatkan bila hari itu karyawan sudah disetujui cuti/izin.
            field('ov-leave-type').textContent = cell.dataset.leave || '';
            field('ov-leave').hidden = !cell.dataset.leave;
            syncOff();
            dialog.showModal();
        });

        dayOff.addEventListener('change', syncOff);
        dialog.querySelector('[data-close-dialog]')?.addEventListener('click', () => dialog.close());

        overrideForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submit = overrideForm.querySelector('button[type="submit"]');
            submit.disabled = true;

            try {
                const data = await send(overrideForm.action, new FormData(overrideForm));

                if (activeSlot && data.cell) {
                    activeSlot.innerHTML = data.cell;
                }

                dialog.close();
                showFlashToast({ message: data.status });
            } catch (error) {
                showFlashToast({ message: error.message, type: 'error' });
            } finally {
                submit.disabled = false;
            }
        });
    }

    // --- Generate ulang roster ---------------------------------------------
    const generateForm = document.querySelector('[data-generate-form]');

    generateForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submit = generateForm.querySelector('button[type="submit"]');
        submit.disabled = true;
        loadingOverlay.show('Menyusun roster...', 'Membuat jadwal untuk bulan ini.');

        try {
            const data = await send(generateForm.action, new FormData(generateForm));
            await refreshGrid();
            showFlashToast({ message: data.status });
        } catch (error) {
            showFlashToast({ message: error.message, type: 'error' });
        } finally {
            loadingOverlay.hide();
            submit.disabled = false;
        }
    });

    // --- Hapus penugasan pola ----------------------------------------------
    document.querySelector('[data-assignments-body]')?.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-assignment-delete]');

        if (!form) {
            return;
        }

        event.preventDefault();

        if (!window.confirm('Hapus penugasan ini? Jadwal yang sudah dibuat tetap tersimpan.')) {
            return;
        }

        const submit = form.querySelector('button[type="submit"]');
        submit.disabled = true;

        try {
            // _method=DELETE ikut terkirim lewat FormData (method spoofing Laravel).
            const data = await send(form.action, new FormData(form));
            const row = form.closest('[data-assignment-row]');
            const body = row?.parentElement;
            row?.remove();

            if (body && !body.querySelector('[data-assignment-row]')) {
                body.innerHTML = '<tr data-assignments-empty><td colspan="4" class="cell-empty">Belum ada penugasan pada periode ini.</td></tr>';
            }

            showFlashToast({ message: data.status });
        } catch (error) {
            showFlashToast({ message: error.message, type: 'error' });
            submit.disabled = false;
        }
    });
})();
