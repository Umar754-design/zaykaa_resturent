<?php
// ===================================================
// Checkout & Payment Page - Zayka Platform
// ===================================================
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Ensure user is logged in
$user = current_user();
if (!$user) {
    header("Location: login.php?redirect=checkout.php");
    exit;
}

// Ensure cart is not empty
if (empty($_SESSION['cart']['items'])) {
    header("Location: cart.php");
    exit;
}

include 'includes/header.php';

// Fetch user addresses
$addrStmt = $pdo->prepare("SELECT * FROM user_addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC");
$addrStmt->execute([$user['id']]);
$addresses = $addrStmt->fetchAll();

// Fetch restaurant info (always Zayka)
$_SESSION['cart']['restaurant_id'] = 1;
$_SESSION['cart']['restaurant_name'] = 'Zaykaa Café & Restaurant';
$restaurant = get_main_restaurant();
?>

<div style="background: #FAF8F2; min-height: 85vh; padding: 40px 0 80px;">
    <div class="container">
        <h1 style="font-family: 'Playfair Display', serif; font-size: 32px; color: var(--primary-green); margin: 0 0 24px;">
            Secure Checkout 💳
        </h1>

        <div class="checkout-columns-wrap" style="display: flex; gap: 30px; flex-wrap: wrap; align-items: flex-start;">
            <!-- Left Options Column -->
            <div class="checkout-col-left" style="flex: 2; min-width: 0; width: 100%; display: flex; flex-direction: column; gap: 24px;">
                
                <!-- 1. Delivery Address Card -->
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0; display: flex; align-items: center; gap: 8px;">
                            <span>📍</span> Delivery Address
                        </h3>
                        <button onclick="openModal('addAddressModal')" style="font-size: 13px; color: var(--primary-green); font-weight: 700; cursor: pointer; text-decoration: underline;">
                            + Add New Address
                        </button>
                    </div>

                    <?php if (!empty($addresses)): ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px;">
                            <?php foreach ($addresses as $idx => $addr): ?>
                            <label style="border: 2px solid <?php echo ($idx === 0) ? 'var(--primary-green)' : 'var(--light-border)'; ?>; border-radius: 12px; padding: 16px; cursor: pointer; display: flex; flex-direction: column; gap: 6px; background: <?php echo ($idx === 0) ? '#FAFDF9' : '#fff'; ?>;" class="addr-card">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-weight: 700; color: var(--primary-green); font-size: 14px;">
                                        <?php echo ($addr['label'] === 'Home') ? '🏠 Home' : (($addr['label'] === 'Work') ? '🏢 Work' : '📍 ' . htmlspecialchars($addr['label'])); ?>
                                    </span>
                                    <input type="radio" name="selected_address" value="<?php echo $addr['id']; ?>" <?php echo ($idx === 0) ? 'checked' : ''; ?> onchange="highlightSelectedAddress(this)" style="accent-color: var(--primary-green);">
                                </div>
                                <div style="font-size: 13px; color: #444; line-height: 1.4;">
                                    <?php echo htmlspecialchars($addr['address_line']); ?><br>
                                    <?php if ($addr['landmark']): ?><em>Near <?php echo htmlspecialchars($addr['landmark']); ?></em><br><?php endif; ?>
                                    <?php echo htmlspecialchars($addr['city'] . ' - ' . $addr['pincode']); ?>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px; background: #FAF8F2; border-radius: 12px;">
                            <p style="color: #666; margin: 0 0 14px;">No addresses saved yet. Please add your delivery location.</p>
                            <button onclick="openModal('addAddressModal')" style="padding: 10px 20px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 600; cursor: pointer;">
                                + Add Address
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 2. Delivery Partner Tip -->
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 8px; display: flex; align-items: center; gap: 8px;">
                        <span>💖</span> Support Your Delivery Partner
                    </h3>
                    <p style="font-size: 13px; color: #666; margin: 0 0 16px;">
                        100% of your tip goes directly to your rider for their dedicated service.
                    </p>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <?php foreach ([0 => 'No Tip', 20 => '₹20', 30 => '₹30', 50 => '₹50'] as $val => $lbl): ?>
                            <button type="button" class="tip-btn <?php echo ($val === 0) ? 'active' : ''; ?>" 
                                    onclick="selectTip(<?php echo $val; ?>, this)"
                                    style="padding: 8px 18px; border-radius: 20px; border: 1px solid var(--light-border); background: <?php echo ($val === 0) ? 'var(--primary-green)' : '#fff'; ?>; color: <?php echo ($val === 0) ? '#fff' : '#333'; ?>; font-weight: 600; font-size: 13px; cursor: pointer;">
                                <?php echo $lbl; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 3. Payment Method Selection -->
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm);">
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 16px; display: flex; align-items: center; gap: 8px;">
                        <span>💳</span> Select Payment Method
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 14px;">
                        <!-- Online Payment -->
                        <label style="border: 1px solid var(--light-border); border-radius: 12px; padding: 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <span style="font-size: 24px;">⚡</span>
                                <div>
                                    <div style="font-weight: 700; color: #333; font-size: 15px;">UPI / Cards / Net Banking</div>
                                    <div style="font-size: 12px; color: #666;">Instant online verification via secure gateway simulation</div>
                                </div>
                            </div>
                            <input type="radio" name="payment_method" value="online" checked style="accent-color: var(--primary-green); width: 18px; height: 18px;">
                        </label>

                        <!-- Cash on Delivery -->
                        <label style="border: 1px solid var(--light-border); border-radius: 12px; padding: 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <span style="font-size: 24px;">💵</span>
                                <div>
                                    <div style="font-weight: 700; color: #333; font-size: 15px;">Cash on Delivery (COD)</div>
                                    <div style="font-size: 12px; color: #666;">Pay via cash or UPI directly to rider upon delivery</div>
                                </div>
                            </div>
                            <input type="radio" name="payment_method" value="cod" style="accent-color: var(--primary-green); width: 18px; height: 18px;">
                        </label>

                        <!-- Zayka Wallet -->
                        <label style="border: 1px solid var(--light-border); border-radius: 12px; padding: 16px; display: flex; align-items: center; justify-content: space-between; cursor: pointer;">
                            <div style="display: flex; align-items: center; gap: 14px;">
                                <span style="font-size: 24px;">🪙</span>
                                <div>
                                    <div style="font-weight: 700; color: #333; font-size: 15px;">Zayka Wallet Balance</div>
                                    <div style="font-size: 12px; color: #666;">Available Balance: <strong style="color: #059669;">₹<?php echo number_format($user['wallet_balance'], 2); ?></strong></div>
                                </div>
                            </div>
                            <input type="radio" name="payment_method" value="wallet" style="accent-color: var(--primary-green); width: 18px; height: 18px;">
                        </label>
                    </div>
                </div>

            </div>

            <!-- Right Column: Final Bill Summary -->
            <div class="checkout-col-right" style="flex: 1; min-width: 0; width: 100%;" id="checkoutSummaryCol">
                <!-- Dynamically populated -->
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add New Address -->
<div class="zayka-modal-backdrop" id="addAddressModal">
    <div class="zayka-modal">
        <div class="modal-header">
            <h3>Add New Delivery Address</h3>
            <button class="modal-close" onclick="closeModal('addAddressModal')">✕</button>
        </div>
        <form id="newAddressForm" onsubmit="submitNewAddress(event)">
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Save Address As</label>
                    <select name="label" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px;">
                        <option value="Home">Home</option>
                        <option value="Work">Work</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Full Address Line *</label>
                    <input type="text" name="address_line" placeholder="House/Flat No., Building Name, Street" required style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px;">
                </div>
                <div>
                    <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Landmark (Optional)</label>
                    <input type="text" name="landmark" placeholder="Near City Mall, Opposite Bank" style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px;">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">City</label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($currentCity); ?>" required style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px;">
                    </div>
                    <div>
                        <label style="font-size: 13px; font-weight: 600; color: #444; display: block; margin-bottom: 4px;">Pincode</label>
                        <input type="text" name="pincode" value="826001" required style="width: 100%; padding: 10px; border: 1px solid var(--light-border); border-radius: 8px;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-close" onclick="closeModal('addAddressModal')" style="padding: 10px 18px; border-radius: 20px; font-size: 13px;">Cancel</button>
                <button type="submit" style="padding: 10px 24px; background: var(--primary-green); color: #fff; border-radius: 20px; font-weight: 700; border: none; cursor: pointer;">Save Address</button>
            </div>
        </form>
    </div>
</div>

<script>
function renderCheckoutPage() {
    fetch('api/cart.php?action=get')
        .then(res => res.json())
        .then(data => {
            if (!data.success || !data.cart || data.cart.items.length === 0) {
                window.location.href = 'cart.php';
                return;
            }

            const cart = data.cart;
            const summaryCol = document.getElementById('checkoutSummaryCol');

            summaryCol.innerHTML = `
                <div style="background: #fff; border-radius: 16px; border: 1px solid var(--light-border); padding: 24px; box-shadow: var(--shadow-sm); position: sticky; top: 95px;">
                    <h3 style="font-family: 'Playfair Display', serif; font-size: 20px; color: var(--primary-green); margin: 0 0 14px;">Order Summary</h3>
                    
                    <div style="font-size: 13px; color: #777; margin-bottom: 14px;">
                        From: <strong style="color: var(--primary-green);">${cart.restaurant_name}</strong>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 8px; font-size: 13px; color: #555; max-height: 180px; overflow-y: auto; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #eee;">
                        ${cart.items.map(it => `
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span><strong>${it.quantity}x</strong> ${it.name} ${it.serving_size ? `<span style="color: #666; font-size: 11px;">(${it.serving_size})</span>` : ''}</span>
                                <span style="font-weight: 600; color: #333;">₹${(parseFloat(it.price) * it.quantity).toFixed(2)}</span>
                            </div>
                        `).join('')}
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 14px; color: #555;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>Subtotal</span>
                            <span style="font-weight: 600; color: #333;">${cart.subtotal_fmt}</span>
                        </div>
                        ${cart.discount > 0 ? `
                        <div style="display: flex; justify-content: space-between; color: #16A34A;">
                            <span>Coupon Discount (${cart.coupon ? cart.coupon.code : ''})</span>
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
                        ${cart.tip > 0 ? `
                        <div style="display: flex; justify-content: space-between; color: var(--gold);">
                            <span>Rider Tip</span>
                            <span style="font-weight: 600;">${cart.tip_fmt}</span>
                        </div>` : ''}
                        <hr style="border: 0; border-top: 1px solid #eee; margin: 8px 0;">
                        <div style="display: flex; justify-content: space-between; font-size: 19px; font-weight: 700; color: var(--primary-green);">
                            <span>Total Payable</span>
                            <span>${cart.total_amount_fmt}</span>
                        </div>
                    </div>

                    <button onclick="placeOrder()" id="placeOrderBtn" style="display: block; width: 100%; border: none; margin-top: 24px; padding: 15px; background: var(--primary-green); color: #fff; border-radius: 25px; font-weight: 700; font-size: 16px; box-shadow: var(--shadow-md); cursor: pointer; transition: 0.2s;">
                        Place Order 🚀
                    </button>
                    <div style="text-align: center; font-size: 11px; color: #888; margin-top: 10px;">
                        Safe and encrypted 256-bit payment transaction
                    </div>
                </div>
            `;
        });
}

function highlightSelectedAddress(radio) {
    document.querySelectorAll('.addr-card').forEach(card => {
        card.style.borderColor = 'var(--light-border)';
        card.style.background = '#fff';
    });
    const parent = radio.closest('.addr-card');
    if (parent) {
        parent.style.borderColor = 'var(--primary-green)';
        parent.style.background = '#FAFDF9';
    }
}

function selectTip(amount, btn) {
    document.querySelectorAll('.tip-btn').forEach(b => {
        b.style.background = '#fff';
        b.style.color = '#333';
    });
    btn.style.background = 'var(--primary-green)';
    btn.style.color = '#fff';

    const formData = new FormData();
    formData.append('action', 'set_tip');
    formData.append('tip', amount);
    fetch('api/cart.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(() => renderCheckoutPage());
}

function submitNewAddress(e) {
    e.preventDefault();
    const form = e.target;
    const formData = new FormData(form);
    formData.append('action', 'add_address');

    fetch('api/location.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                closeModal('addAddressModal');
                setTimeout(() => window.location.reload(), 400);
            } else {
                showToast(data.message, 'error');
            }
        });
}

function placeOrder() {
    const selectedAddr = document.querySelector('input[name="selected_address"]:checked');
    if (!selectedAddr) {
        showToast('Please select or add a delivery address.', 'warning');
        return;
    }

    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    const btn = document.getElementById('placeOrderBtn');
    btn.disabled = true;
    btn.innerText = 'Processing Order...';

    const formData = new FormData();
    formData.append('action', 'place_order');
    formData.append('address_id', selectedAddr.value);
    formData.append('payment_method', paymentMethod);

    fetch('api/orders.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message, 'success');
                refreshCartBadge();
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 600);
            } else {
                btn.disabled = false;
                btn.innerText = 'Place Order 🚀';
                showToast(data.message || 'Order placement failed.', 'error');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerText = 'Place Order 🚀';
            showToast('Network error. Please try again.', 'error');
        });
}

document.addEventListener('DOMContentLoaded', renderCheckoutPage);
</script>

<?php include 'includes/footer.php'; ?>
