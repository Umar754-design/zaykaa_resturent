<?php
// ===================================================
// Shopping Cart Page - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
include 'includes/header.php';
?>

<div style="background: #FAF8F2; min-height: 80vh; padding: 40px 0 80px;">
    <div class="container">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 24px;">
            My Food Cart 🛒
        </h1>

        <div id="cartContentWrap" class="cart-content-wrap" style="display: flex; gap: 30px; flex-wrap: wrap; align-items: flex-start;">
            <!-- Cart Items Column -->
            <div class="cart-items-col" style="flex: 2; min-width: 0; width: 100%;" id="cartItemsCol">
                <div style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 16px; border: 1px solid var(--light-border);">
                    <div style="font-size: 24px;">Loading your cart...</div>
                </div>
            </div>

            <!-- Bill Summary Column -->
            <div class="cart-summary-col" style="flex: 1; min-width: 0; width: 100%;" id="cartSummaryCol">
                <!-- Populated via JS -->
            </div>
        </div>
    </div>
</div>

<script>
function renderCartPage() {
    fetch('api/cart.php?action=get')
        .then(res => res.json())
        .then(data => {
            const itemsCol = document.getElementById('cartItemsCol');
            const summaryCol = document.getElementById('cartSummaryCol');

            if (!data.success || !data.cart || data.cart.items.length === 0) {
                itemsCol.innerHTML = `
                    <div style="text-align: center; padding: 60px 20px; background: #fff; border-radius: 16px; border: 1px solid var(--light-border);">
                        <div style="font-size: 54px; margin-bottom: 12px;">🛍️</div>
                        <h3 style="font-family: 'Playfair Display', serif; color: var(--primary-green); font-size: 24px; margin: 0 0 8px;">Your Cart is Empty</h3>
                        <p style="color: #666; font-size: 14px; margin-bottom: 24px;">Good food is always just a few clicks away. Explore our delicious menu!</p>
                        <a href="menu.php" style="display: inline-block; padding: 12px 28px; background: var(--primary-green); color: #fff; border-radius: 24px; font-weight: 600; text-decoration: none;">
                            Explore Menu
                        </a>
                    </div>
                `;
                summaryCol.innerHTML = '';
                return;
            }

            const cart = data.cart;
            
            // Build items HTML
            let itemsHtml = `
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm); margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 14px; margin-bottom: 16px;">
                        <div>
                            <div style="font-size: 12px; color: #888; font-weight: 600; text-transform: uppercase;">Ordering from</div>
                            <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 2px 0 0;">
                                ${cart.restaurant_name || 'Zaykaa Café & Restaurant'}
                            </h3>
                        </div>
                        <button onclick="clearCart()" style="font-size: 13px; color: #DC2626; font-weight: 600; cursor: pointer;">Clear Cart</button>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 16px;">
            `;

            cart.items.forEach(item => {
                let addonsHtml = '';
                if (item.addons && item.addons.length > 0) {
                    addonsHtml = `<div style="font-size: 12px; color: #777; margin-top: 4px;">+ ` + 
                        item.addons.map(a => `${a.name} (₹${parseFloat(a.price).toFixed(2)})`).join(', ') + 
                    `</div>`;
                }

                const itemPriceFmt = '₹' + parseFloat(item.price).toFixed(2);
                itemsHtml += `
                    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 14px; border-bottom: 1px dashed #eee;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span class="${item.is_veg ? 'veg-icon' : 'non-veg-icon'}"></span>
                            <div>
                                <div style="font-weight: 600; color: #333; font-size: 15px;">${item.name}</div>
                                ${item.serving_size ? `<div style="font-size: 12px; color: #555; margin-top: 2px;"><strong style="color: var(--gold); font-size: 11px;">Serving:</strong> ${item.serving_size}</div>` : ''}
                                <div style="font-size: 13px; color: var(--primary-green); font-weight: 700; margin-top: 2px;">${itemPriceFmt}</div>
                                ${addonsHtml}
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div class="qty-stepper">
                                <button class="qty-btn" onclick="changeCartQty('${item.cart_key}', -1)">-</button>
                                <span class="qty-num">${item.quantity}</span>
                                <button class="qty-btn" onclick="changeCartQty('${item.cart_key}', 1)">+</button>
                            </div>
                            <button onclick="removeCartItem('${item.cart_key}')" style="color: #999; font-size: 16px; cursor: pointer;" title="Remove">✕</button>
                        </div>
                    </div>
                `;
            });

            // Cooking instructions input
            itemsHtml += `
                    </div>
                    <div style="margin-top: 20px;">
                        <label style="font-size: 13px; font-weight: 600; color: #555; display: block; margin-bottom: 6px;">
                            Special Cooking or Delivery Instructions (Optional):
                        </label>
                        <input type="text" id="cartInstructions" placeholder="e.g. Please make it extra spicy, ring doorbell twice" 
                               value="${cart.instructions || ''}" 
                               onblur="saveInstructions(this.value)"
                               style="width: 100%; padding: 10px 14px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px; background: #FAF8F2;">
                    </div>
                </div>
            `;
            itemsCol.innerHTML = itemsHtml;

            // Build Bill Summary HTML
            let couponBox = '';
            if (cart.coupon) {
                couponBox = `
                    <div style="background: #DEF7EC; border: 1px dashed #046C4E; border-radius: 10px; padding: 10px 14px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div>
                            <strong style="color: #046C4E; font-size: 13px;">${cart.coupon.code} Applied!</strong>
                            <div style="font-size: 11px; color: #046C4E;">Saved ${cart.discount_fmt}</div>
                        </div>
                        <button onclick="removeCoupon()" style="color: #DC2626; font-size: 12px; font-weight: 700; cursor: pointer;">Remove</button>
                    </div>
                `;
            } else {
                couponBox = `
                    <div style="display: flex; gap: 8px; margin-bottom: 16px;">
                        <input type="text" id="couponCodeInput" placeholder="Enter coupon (e.g. ZAYKA50)" 
                               style="flex: 1; padding: 8px 12px; border: 1px solid var(--light-border); border-radius: 8px; font-size: 13px; text-transform: uppercase;">
                        <button onclick="applyCoupon()" style="padding: 8px 16px; background: var(--gold); color: #fff; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer;">Apply</button>
                    </div>
                `;
            }

            summaryCol.innerHTML = `
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm); position: sticky; top: 95px;">
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 16px;">Bill Summary</h3>
                    
                    ${couponBox}

                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px; color: #555;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Item Subtotal</span>
                            <span style="font-weight: 600; color: #333;">${cart.subtotal_fmt}</span>
                        </div>
                        ${cart.addons_total > 0 ? `
                        <div style="display: flex; justify-content: space-between;">
                            <span>Add-ons Total</span>
                            <span style="font-weight: 600; color: #333;">${cart.addons_total_fmt}</span>
                        </div>` : ''}
                        ${cart.discount > 0 ? `
                        <div style="display: flex; justify-content: space-between; color: #16A34A;">
                            <span>Coupon Discount</span>
                            <span style="font-weight: 600;">-${cart.discount_fmt}</span>
                        </div>` : ''}
                        <div style="display: flex; justify-content: space-between;">
                            <span>Taxes & GST (5%)</span>
                            <span style="font-weight: 600; color: #333;">${cart.tax_amount_fmt}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Delivery Fee</span>
                            <span style="font-weight: 600; color: #333;">${cart.delivery_fee_fmt}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span>Platform Fee</span>
                            <span style="font-weight: 600; color: #333;">${cart.platform_fee_fmt}</span>
                        </div>
                        <hr style="border: 0; border-top: 1px solid #eee; margin: 8px 0;">
                        <div style="display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; color: var(--primary-green);">
                            <span>Total Payable</span>
                            <span>${cart.total_amount_fmt}</span>
                        </div>
                    </div>

                    <a href="checkout.php" style="display: block; width: 100%; text-align: center; margin-top: 24px; padding: 14px; background: var(--primary-green); color: #fff; border-radius: 25px; font-weight: 700; text-decoration: none; font-size: 15px; box-shadow: var(--shadow-sm); transition: 0.2s;">
                        Proceed to Checkout →
                    </a>
                </div>
            `;
        });
}

function clearCart() {
    if (confirm('Are you sure you want to empty your cart?')) {
        fetch('api/cart.php?action=clear', { method: 'POST' })
            .then(res => res.json())
            .then(() => {
                refreshCartBadge();
                renderCartPage();
            });
    }
}

function applyCoupon() {
    const code = document.getElementById('couponCodeInput').value.trim();
    if (!code) {
        showToast('Please enter a coupon code.', 'warning');
        return;
    }
    const formData = new FormData();
    formData.append('action', 'apply_coupon');
    formData.append('code', code);

    fetch('api/cart.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                renderCartPage();
            } else {
                showToast(data.message, 'error');
            }
        });
}

function removeCoupon() {
    fetch('api/cart.php?action=remove_coupon', { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            showToast('Coupon removed.', 'info');
            renderCartPage();
        });
}

function saveInstructions(text) {
    const formData = new FormData();
    formData.append('action', 'set_instructions');
    formData.append('instructions', text);
    fetch('api/cart.php', { method: 'POST', body: formData });
}

document.addEventListener('DOMContentLoaded', renderCartPage);
</script>

<?php include 'includes/footer.php'; ?>
