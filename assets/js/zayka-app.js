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

    // Fetch initial cart count
    refreshCartBadge();
});

/**
 * Show custom toast alert (replaces native alert)
 */
function showToast(message, type = 'success') {
    let tc = document.getElementById('toastContainer');
    if (!tc) {
        tc = document.createElement('div');
        tc.id = 'toastContainer';
        document.body.appendChild(tc);
    }

    const toast = document.createElement('div');
    toast.className = `toast-msg ${type}`;
    
    let icon = '✓';
    if (type === 'error') icon = '✕';
    if (type === 'warning') icon = '⚠';
    if (type === 'info') icon = 'ℹ';

    toast.innerHTML = `<span style="font-size:18px;font-weight:bold;">${icon}</span><span>${message}</span>`;
    tc.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(50px)';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
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
 * Refresh Cart Badge Counter
 */
function refreshCartBadge() {
    fetch('api/cart.php?action=get')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.cart) {
                const count = data.cart.item_count;
                const badges = document.querySelectorAll('.cart-count-badge');
                badges.forEach(b => {
                    b.innerText = count;
                    b.style.display = count > 0 ? 'inline-flex' : 'none';
                });

                const floatingBar = document.getElementById('floatingCartBar');
                if (floatingBar) {
                    if (count > 0) {
                        floatingBar.style.display = 'flex';
                        const summaryText = floatingBar.querySelector('.cart-summary-text');
                        if (summaryText) {
                            summaryText.innerText = `${count} ${count === 1 ? 'item' : 'items'} | ${data.cart.total_amount_fmt}`;
                        }
                    } else {
                        floatingBar.style.display = 'none';
                    }
                }
            }
        })
        .catch(() => {});
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

    fetch('api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            refreshCartBadge();
            closeModal('customizationModal');
            if (typeof renderCartPage === 'function') renderCartPage();
        } else {
            showToast(data.message || 'Could not add item.', 'error');
        }
    })
    .catch(err => {
        showToast('Network error while updating cart.', 'error');
    });
}

/**
 * Update Cart Item Quantity
 */
function changeCartQty(cartKey, delta) {
    const formData = new FormData();
    formData.append('action', 'update_qty');
    formData.append('cart_key', cartKey);
    formData.append('delta', delta);

    fetch('api/cart.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            refreshCartBadge();
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
