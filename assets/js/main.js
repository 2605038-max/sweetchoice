/**
 * Sweet Choice - Main Application JavaScript
 * Handles mobile nav, quantity controls, live price calculations, checkout interactions, and favorites.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Menu Toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');

    if (mobileMenuBtn && navLinks) {
        mobileMenuBtn.addEventListener('click', () => {
            navLinks.classList.toggle('active');
            mobileMenuBtn.classList.toggle('open');
        });
    }

    // 2. Quantity Controls (+ and - buttons)
    document.querySelectorAll('.qty-control').forEach(control => {
        const minusBtn = control.querySelector('.qty-minus');
        const plusBtn = control.querySelector('.qty-plus');
        const input = control.querySelector('.qty-input');

        if (minusBtn && plusBtn && input) {
            minusBtn.addEventListener('click', () => {
                let val = parseInt(input.value, 10) || 1;
                if (val > 1) {
                    input.value = val - 1;
                    input.dispatchEvent(new Event('change'));
                }
            });

            plusBtn.addEventListener('click', () => {
                let val = parseInt(input.value, 10) || 1;
                const max = parseInt(input.getAttribute('max'), 10) || 99;
                if (val < max) {
                    input.value = val + 1;
                    input.dispatchEvent(new Event('change'));
                }
            });
        }
    });

    // 3. Product Details - Live Price Calculator with Customizations
    const productForm = document.getElementById('product-order-form');
    if (productForm) {
        const basePrice = parseInt(productForm.getAttribute('data-base-price'), 10) || 0;
        const priceDisplay = document.getElementById('dynamic-total-price');
        const qtyInput = productForm.querySelector('.qty-input');
        const customRadios = productForm.querySelectorAll('.custom-radio');

        function updateProductTotal() {
            let extraTotal = 0;
            customRadios.forEach(radio => {
                if (radio.checked) {
                    extraTotal += parseInt(radio.getAttribute('data-extra-price'), 10) || 0;
                }
            });

            const qty = parseInt(qtyInput ? qtyInput.value : 1, 10) || 1;
            const unitPrice = basePrice + extraTotal;
            const grandTotal = unitPrice * qty;

            if (priceDisplay) {
                priceDisplay.textContent = '¥' + grandTotal.toLocaleString('en-US');
            }
        }

        customRadios.forEach(radio => radio.addEventListener('change', updateProductTotal));
        if (qtyInput) qtyInput.addEventListener('change', updateProductTotal);
        updateProductTotal();
    }

    // 4. Checkout - Pickup vs Delivery Toggle & Dynamic Distance Tier Calculation
    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        const tabPickup = document.getElementById('tab-pickup');
        const tabDelivery = document.getElementById('tab-delivery');
        const inputFulfillment = document.getElementById('fulfillment-type-input');
        const pickupSection = document.getElementById('pickup-details-section');
        const deliverySection = document.getElementById('delivery-details-section');
        const distanceSelect = document.getElementById('distance-tier-select');
        const deliveryFeeRow = document.getElementById('checkout-delivery-fee-row');
        const deliveryFeeDisplay = document.getElementById('checkout-delivery-fee');
        const grandTotalDisplay = document.getElementById('checkout-grand-total');

        const baseSubtotal = parseInt(checkoutForm.getAttribute('data-subtotal'), 10) || 0;
        const customTotal = parseInt(checkoutForm.getAttribute('data-custom'), 10) || 0;
        const serviceFee = parseInt(checkoutForm.getAttribute('data-service-fee'), 10) || 0;

        function updateCheckoutTotals() {
            const isDelivery = inputFulfillment.value === 'delivery';
            let deliveryFee = 0;

            if (isDelivery && distanceSelect) {
                const selectedOption = distanceSelect.options[distanceSelect.selectedIndex];
                deliveryFee = parseInt(selectedOption ? selectedOption.getAttribute('data-fee') : 0, 10) || 0;
            }

            if (deliveryFeeRow) {
                deliveryFeeRow.style.display = isDelivery ? 'flex' : 'none';
            }
            if (deliveryFeeDisplay) {
                deliveryFeeDisplay.textContent = '¥' + deliveryFee.toLocaleString('en-US');
            }

            const grand = baseSubtotal + customTotal + serviceFee + deliveryFee;
            if (grandTotalDisplay) {
                grandTotalDisplay.textContent = '¥' + grand.toLocaleString('en-US');
            }
        }

        if (tabPickup && tabDelivery && inputFulfillment) {
            tabPickup.addEventListener('click', () => {
                tabPickup.classList.add('active');
                tabDelivery.classList.remove('active');
                inputFulfillment.value = 'pickup';
                if (pickupSection) pickupSection.style.display = 'block';
                if (deliverySection) deliverySection.style.display = 'none';
                updateCheckoutTotals();
            });

            tabDelivery.addEventListener('click', () => {
                tabDelivery.classList.add('active');
                tabPickup.classList.remove('active');
                inputFulfillment.value = 'delivery';
                if (pickupSection) pickupSection.style.display = 'none';
                if (deliverySection) deliverySection.style.display = 'block';
                updateCheckoutTotals();
            });
        }

        if (distanceSelect) {
            distanceSelect.addEventListener('change', updateCheckoutTotals);
        }

        // Initialize state
        updateCheckoutTotals();
    }

    // 5. Favorite Buttons Toggle (AJAX with fallback)
    document.querySelectorAll('.btn-favorite').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const prodId = btn.getAttribute('data-product-id');
            if (!prodId) return;

            fetch(`api/favorites.php?action=toggle&product_id=${prodId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        btn.classList.toggle('active', data.is_favorited);
                        const counter = document.querySelector('.fav-counter');
                        if (counter && data.total_count !== undefined) {
                            counter.textContent = data.total_count;
                        }
                    }
                })
                .catch(err => {
                    // Fallback to page navigation if API not ready
                    window.location.href = `favorites.php?toggle=${prodId}`;
                });
        });
    });
});
