// Small, framework-free form behaviour shared by every page.

// ---------------------------------------------------------------------------
// One submission per click
// ---------------------------------------------------------------------------
// A double click on Approve, Post to ledger or Submit used to send the request
// twice. The server already refuses the second one (Phase 3 locks), but the
// person was left looking at a "this was already decided" notice for their own
// click. Once a form is on its way, its submit buttons are disabled and marked
// busy until the next page loads.
//
// Left alone: GET forms (filters, search), forms another handler already
// cancelled (a confirm() answered "Cancel", an Alpine @submit.prevent), forms
// or buttons that open in a new tab (previews, exports), and any form marked
// data-allow-resubmit.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if ((form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
    if (form.hasAttribute('data-allow-resubmit')) return;

    const submitter = event.submitter;
    const target = (submitter && submitter.getAttribute('formtarget')) || form.getAttribute('target') || '';
    if (target && target !== '_self') return;

    if (form.hasAttribute('data-submitting')) {
        event.preventDefault();
        return;
    }

    form.setAttribute('data-submitting', '');
    form.setAttribute('aria-busy', 'true');

    // Disabled on the next tick, not now: the browser builds the request
    // after this handler, and a button disabled before then would drop its own
    // name and value (e.g. which card or action was chosen).
    setTimeout(() => {
        form.querySelectorAll('[type="submit"], button:not([type])').forEach((button) => {
            button.disabled = true;
        });
    }, 0);
});

// Coming back with the browser's Back button can restore a page from cache
// with its buttons still disabled; make them usable again.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-submitting]').forEach((form) => {
        form.removeAttribute('data-submitting');
        form.removeAttribute('aria-busy');
        form.querySelectorAll('[type="submit"], button:not([type])').forEach((button) => {
            button.disabled = false;
        });
    });
});

// ---------------------------------------------------------------------------
// Labels that name their field
// ---------------------------------------------------------------------------
// Many forms set a visible <label class="label"> above its field without
// for/id, so a screen reader announced only "edit text" and clicking the
// label did nothing. Each such label is tied to the first field after it in
// the same container — only when that field has no accessible name of its
// own. Giving the field an id here is safe: ids are generated and unique.
let generated = 0;

const FIELD = 'input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea';

function isNamed(field) {
    if (field.getAttribute('aria-label') || field.getAttribute('aria-labelledby')) return true;
    if (field.closest('label')) return true;

    return Boolean(field.id && document.querySelector(`label[for="${CSS.escape(field.id)}"]`));
}

function linkLabels(root = document) {
    root.querySelectorAll('label:not([for])').forEach((label) => {
        if (label.querySelector(FIELD) || !label.parentElement) return;

        // The first field that follows the label inside the same parent.
        const field = [...label.parentElement.querySelectorAll(FIELD)].find(
            (candidate) => label.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_FOLLOWING,
        );

        if (!field || isNamed(field)) return;

        if (!field.id) field.id = `nx-field-${++generated}`;
        label.htmlFor = field.id;
    });
}

// Quill (the policy editor) draws its style and alignment pickers as bare
// spans with role=button; give them names a screen reader can read out.
const QUILL_PICKERS = { 'ql-header': 'Text style', 'ql-align': 'Alignment' };

function nameQuillPickers(root = document) {
    root.querySelectorAll('.ql-picker-label:not([aria-label])').forEach((label) => {
        const picker = label.closest('.ql-picker');
        const kind = picker && Object.keys(QUILL_PICKERS).find((cls) => picker.classList.contains(cls));
        label.setAttribute('aria-label', kind ? QUILL_PICKERS[kind] : 'Formatting options');
    });
}

// ---------------------------------------------------------------------------
// A dialog form that failed validation
// ---------------------------------------------------------------------------
// The page comes back with the dialog closed, so what was typed and why it
// was refused used to vanish into the alert at the top. The layout passes the
// dialog's id, old input and errors (window.NexDialogState); the dialog is
// reopened, refilled, and each message is placed under its own field.
function restoreDialog() {
    const state = window.NexDialogState;
    const dialog = state && document.getElementById(state.id);
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const form = dialog.querySelector('form');
    const fieldFor = (key) => {
        // "details.location" is posted as "details[location]".
        const name = key.replace(/\.(\w+)/g, '[$1]');
        return form && form.querySelector(`[name="${CSS.escape(name)}"]:not([type=hidden])`);
    };

    Object.entries(state.old || {}).forEach(([key, value]) => {
        const field = fieldFor(key);
        if (!field || field.type === 'file' || field.type === 'password') return;

        if (field.type === 'checkbox' || field.type === 'radio') {
            form.querySelectorAll(`[name="${CSS.escape(field.name)}"]`).forEach((box) => {
                box.checked = String(box.value) === String(value);
                box.dispatchEvent(new Event('change', { bubbles: true }));
            });
        } else {
            field.value = value;
            // Alpine's x-model listens for these.
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    // A checkbox that was unticked is not in the old input at all.
    form && form.querySelectorAll('input[type=checkbox]:not([name$="[]"])').forEach((box) => {
        if (!(box.name in (state.old || {}))) {
            box.checked = false;
            box.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });

    let first = null;
    Object.entries(state.errors || {}).forEach(([key, messages]) => {
        const field = fieldFor(key);
        if (!field) return;

        const id = `${field.id || field.name.replace(/\W+/g, '-')}-error`;
        const note = document.createElement('span');
        note.className = 'error-text';
        note.id = id;
        note.textContent = messages[0];
        (field.closest('label') || field).insertAdjacentElement('afterend', note);

        field.classList.add('input-error');
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), id].filter(Boolean).join(' '));
        first ??= field;
    });

    dialog.showModal();
    first?.focus();
}

document.addEventListener('DOMContentLoaded', () => {
    linkLabels();
    nameQuillPickers();
    restoreDialog();

    // Fields Alpine adds later (rows, templates, dialogs).
    let queued = false;
    new MutationObserver(() => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            linkLabels();
            nameQuillPickers();
        });
    }).observe(document.body, { childList: true, subtree: true });
});
