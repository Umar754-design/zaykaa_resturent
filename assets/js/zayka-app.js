// ===================================================
// Zayka Client-Side Framework & Interactivity Suite
// Toasts, Modals, Cart Operations, Location, Favorites
// ===================================================

document.addEventListener('DOMContentLoaded', function() {
    // Ensure toast container exists
    if (!document.getElementById('toastContainer')) {
        const tc = document.createElement('div');
        tc.id = 'toastContainer';
        document.body.appendChild(tc);
    }

    // Modal backdrop click close
    document.querySelectorAll('.zayka-modal-backdrop').forEach(bd => {
        bd.addEventListener('click', function(e) {
            if (e.target === bd) {
                bd.classList.remove('active');
            }
        });
    });

    // Close button click in modals
    document.querySelectorAll('.modal-close, [data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = btn.closest('.zayka-modal-backdrop');
            if (modal) modal.classList.remove('active');
        });
    });

    // Fetch initial cart state and turn ADD buttons into quantity steppers
    initAddButtons();
    refreshCartBadge();
});

/**
 * Show custom toast alert (replaces native alert).
 * Only one toast is shown at a time: a repeated message bumps a "x n" counter
 * and restarts its timer instead of stacking new boxes.
 */
const ZAYKA_TOAST_MS = 2600;
let zaykaToastTimer = null;

function showToast(message, type = 'success') {
    let tc = document.getElementById('toastContainer');
    if (!tc) {
        tc = document.createElement('div');
        tc.id = 'toastContainer';
        document.body.appendChild(tc);
    }

    const icons = { success: '✓', error: '✕', warning: '⚠', info: 'ℹ' };
    let toast = tc.querySelector('.toast-msg');

    if (toast && toast.dataset.message === String(message) && toast.dataset.type === type) {
        const n = (parseInt(toast.dataset.count || '1', 10) || 1) + 1;
        toast.dataset.count = n;
        toast.querySelector('.toast-count').textContent = '×' + n;
    } else {
        tc.querySelectorAll('.toast-msg').forEach(t => t.remove());
        toast = document.createElement('div');
        toast.className = `toast-msg ${type}`;
        toast.dataset.message = String(message);
        toast.dataset.type = type;
        toast.dataset.count = '1';
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');

        const icon = document.createElement('span');
        icon.style.cssText = 'font-size:18px;font-weight:bold;';
        icon.textContent = icons[type] || icons.success;
        const text = document.createElement('span');
        text.style.flex = '1';
        text.textContent = message;
        const count = document.createElement('span');
        count.className = 'toast-count';
        count.style.cssText = 'font-weight:700;opacity:.8;';
        toast.append(icon, text, count);
        tc.appendChild(toast);
    }

    toast.style.opacity = '1';
    toast.style.transform = 'translateX(0)';
    clearTimeout(zaykaToastTimer);
    zaykaToastTimer = setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px)';
        setTimeout(() => toast.remove(), 300);
    }, ZAYKA_TOAST_MS);
}

/**
 * Modal helpers
 */
function openModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
}

function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
}

/**
 * Cart state shared by the badge, floating bar and ADD / quantity steppers.
 */
const zaykaCart = { qtyByItem: {}, keysByItem: {} };

function applyCartState(cart) {
    if (!cart) return;
    const count = cart.item_count || 0;

    document.querySelectorAll('.cart-count-badge').forEach(b => {
        b.innerText = count;
        b.style.display = count > 0 ? 'inline-flex' : 'none';
    });

    const floatingBar = document.getElementById('floatingCartBar');
    if (floatingBar) {
        if (count > 0) {
            floatingBar.style.display = 'flex';
            const summaryText = floatingBar.querySelector('.cart-summary-text');
            if (summaryText) {
                summaryText.innerText = `${count} ${count === 1 ? 'item' : 'items'} | ${cart.total_amount_fmt}`;
            }
        } else {
            floatingBar.style.display = 'none';
        }
    }

    // Quantity per menu item (one item can sit in the cart under several add-on combos)
    zaykaCart.qtyByItem = {};
    zaykaCart.keysByItem = {};
    (cart.items || []).forEach(it => {
        const id = String(it.item_id);
        zaykaCart.qtyByItem[id] = (zaykaCart.qtyByItem[id] || 0) + (parseInt(it.quantity, 10) || 0);
        (zaykaCart.keysByItem[id] = zaykaCart.keysByItem[id] || []).push(it.cart_key);
    });
    syncAddButtons();
}

/**
 * Refresh Cart Badge Counter
 */
function refreshCartBadge() {
    return fetch('api/cart.php?action=get')
        .then(res => res.json())
        .then(data => { if (data.success) applyCartState(data.cart); })
        .catch(() => {});
}

/**
 * Turn every plain "ADD" button (onclick = addToCart(id)) into an ADD <-> [ - qty + ] control.
 */
function initAddButtons(root = document) {
    root.querySelectorAll('button[onclick*="addToCart("]').forEach(btn => {
        if (btn.dataset.stepperReady) return;
        const m = (btn.getAttribute('onclick') || '').match(/addToCart\(\s*(\d+)\s*\)/);
        if (!m) return;
        const itemId = m[1];
        btn.dataset.stepperReady = '1';
        btn.dataset.addItem = itemId;

        const stepper = document.createElement('div');
        stepper.className = 'qty-stepper' + (btn.classList.contains('rail-card-add') ? ' qty-stepper-compact' : '');
        stepper.dataset.item = itemId;
        stepper.hidden = true;
        stepper.innerHTML =
            '<button type="button" class="qty-stepper-btn" data-step="-1" aria-label="Decrease quantity">−</button>' +
            '<span class="qty-stepper-val" aria-live="polite">1</span>' +
            '<button type="button" class="qty-stepper-btn" data-step="1" aria-label="Increase quantity">+</button>';
        stepper.addEventListener('click', e => {
            e.stopPropagation();
            e.preventDefault();
            const b = e.target.closest('.qty-stepper-btn');
            if (!b || stepper.classList.contains('busy')) return;
            stepCartItem(itemId, parseInt(b.dataset.step, 10));
        });
        btn.insertAdjacentElement('afterend', stepper);
    });
    syncAddButtons();
}

function syncAddButtons() {
    document.querySelectorAll('button[data-add-item]').forEach(btn => {
        const id = btn.dataset.addItem;
        const qty = zaykaCart.qtyByItem[id] || 0;
        const stepper = btn.nextElementSibling;
        if (!stepper || !stepper.classList.contains('qty-stepper')) return;
        if (qty > 0) {
            if (!stepper.style.minWidth && btn.offsetWidth && !stepper.classList.contains('qty-stepper-compact')) {
                stepper.style.minWidth = btn.offsetWidth + 'px';
            }
            stepper.querySelector('.qty-stepper-val').textContent = qty;
            btn.hidden = true;
            stepper.hidden = false;
        } else {
            btn.hidden = false;
            stepper.hidden = true;
        }
    });
}

function setStepperBusy(itemId, busy) {
    document.querySelectorAll(`.qty-stepper[data-item="${itemId}"]`).forEach(s => s.classList.toggle('busy', busy));
}

function stepCartItem(itemId, delta) {
    if (delta > 0) {
        addToCart(itemId);
        return;
    }
    const keys = zaykaCart.keysByItem[String(itemId)] || [];
    if (!keys.length) return;
    // Prefer the plain (no add-ons) line, otherwise the most recently added combo
    const key = keys.includes('item_' + itemId) ? 'item_' + itemId : keys[keys.length - 1];
    setStepperBusy(itemId, true);
    changeCartQty(key, -1).finally(() => setStepperBusy(itemId, false));
}

/**
 * Add item to cart with conflict resolution
 */
function addToCart(itemId, qty = 1, addons = [], forceClear = false) {
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('item_id', itemId);
    formData.append('quantity', qty);
    formData.append('force_clear', forceClear ? '1' : '0');

    if (addons && addons.length > 0) {
        addons.forEach(ad => formData.append('addons[]', ad));
    }

    // When the item has an on-page ADD/stepper, the stepper itself is the feedback: no toast
    const hasStepper = !!document.querySelector(`button[data-add-item="${itemId}"]`) && !(addons && addons.length);
    setStepperBusy(itemId, true);

    return fetch('api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            if (!hasStepper) showToast(data.message, 'success');
            applyCartState(data.cart);
            closeModal('customizationModal');
            if (typeof renderCartPage === 'function') renderCartPage();
        } else {
            showToast(data.message || 'Could not add item.', 'error');
        }
    })
    .catch(() => {
        showToast('Network error while updating cart.', 'error');
    })
    .finally(() => setStepperBusy(itemId, false));
}

/**
 * Update Cart Item Quantity
 */
function changeCartQty(cartKey, delta) {
    const formData = new FormData();
    formData.append('action', 'update_qty');
    formData.append('cart_key', cartKey);
    formData.append('delta', delta);

    return fetch('api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            applyCartState(data.cart);
            if (typeof renderCartPage === 'function') renderCartPage();
            if (typeof renderCheckoutPage === 'function') renderCheckoutPage();
        } else {
            showToast(data.message, 'error');
        }
    });
}

/**
 * Remove Item from Cart
 */
function removeCartItem(cartKey) {
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('cart_key', cartKey);

    fetch('api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'info');
            refreshCartBadge();
            if (typeof renderCartPage === 'function') renderCartPage();
            if (typeof renderCheckoutPage === 'function') renderCheckoutPage();
        }
    });
}

/**
 * Toggle Favorite Restaurant
 */
function toggleFavorite(restaurantId, btnEl) {
    const formData = new FormData();
    formData.append('type', 'favorite');
    formData.append('restaurant_id', restaurantId);

    fetch('api/toggle-status.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, data.is_favorite ? 'success' : 'info');
            if (btnEl) {
                btnEl.innerHTML = data.is_favorite ? '❤️' : '🤍';
            }
        } else {
            showToast(data.message, 'error');
        }
    });
}

/**
 * Set Active City
 */
function selectCity(city) {
    const formData = new FormData();
    formData.append('action', 'set_city');
    formData.append('city', city);

    fetch('api/location.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Location set to ' + city, 'success');
            setTimeout(() => window.location.reload(), 500);
        }
    });
}
