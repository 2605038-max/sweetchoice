/**
 * Sweet Choice - Main Application JavaScript
 * Handles mobile nav, quantity controls, live price calculations, checkout interactions, and favorites.
 */

document.addEventListener('DOMContentLoaded', () => {
    // 0. Sync active website language to localStorage for cross-tab Mini Game updates
    try {
        const docLang = document.documentElement.getAttribute('lang');
        if (docLang === 'en' || docLang === 'ja') {
            localStorage.setItem('sweetchoice_lang', docLang);
        }
    } catch (e) {}

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

    // 4. Checkout - Fulfillment Options (Dine-in, Store Pickup, Local Delivery), Dynamic Payments & Service Fee
    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        const isJa = checkoutForm.getAttribute('data-lang') === 'ja';
        const fulfillmentRadios = checkoutForm.querySelectorAll('.fulfillment-radio');
        const fulfillmentCards = checkoutForm.querySelectorAll('.fulfillment-option-card');

        const dineInSection = document.getElementById('dine-in-details-section');
        const pickupSection = document.getElementById('pickup-details-section');
        const deliverySection = document.getElementById('delivery-details-section');
        const distanceSelect = document.getElementById('distance-tier-select');

        const fulfillmentDisplay = document.getElementById('checkout-fulfillment-display');
        const paymentDisplay = document.getElementById('checkout-payment-display');
        const serviceFeeRow = document.getElementById('checkout-service-fee-row');
        const serviceFeeDisplay = document.getElementById('checkout-service-fee');
        const deliveryFeeRow = document.getElementById('checkout-delivery-fee-row');
        const deliveryFeeDisplay = document.getElementById('checkout-delivery-fee');
        const grandTotalDisplay = document.getElementById('checkout-grand-total');

        const paymentPromptMsg = document.getElementById('payment-select-fulfillment-msg');
        const paymentMethodsList = document.getElementById('payment-methods-list');
        const paymentOptionLabels = checkoutForm.querySelectorAll('.payment-method-option');
        const paymentRadios = checkoutForm.querySelectorAll('.payment-method-radio');
        const cashUnavailableNote = document.getElementById('cash-unavailable-note');

        const cardPaymentForm = document.getElementById('card-payment-form');
        const onlinePaymentForm = document.getElementById('online-payment-form');
        const cashPaymentInfo = document.getElementById('cash-payment-info');

        const cardholderInput = document.getElementById('cardholder-name');
        const cardNumberInput = document.getElementById('card-number');
        const cardExpiryInput = document.getElementById('card-expiry');
        const cardCvvInput = document.getElementById('card-cvv');

        const providerCards = checkoutForm.querySelectorAll('.online-provider-card');
        const providerRadios = checkoutForm.querySelectorAll('.online-provider-radio');
        const providerSelectedBox = document.getElementById('online-provider-selected-box');
        const providerSelectedText = document.getElementById('online-provider-selected-text');

        const clientErrorBox = document.getElementById('checkout-client-error');
        const clientErrorList = document.getElementById('checkout-client-error-list');

        const baseSubtotal = parseInt(checkoutForm.getAttribute('data-subtotal'), 10) || 0;
        const customTotal = parseInt(checkoutForm.getAttribute('data-custom'), 10) || 0;
        const configuredServiceFee = parseInt(checkoutForm.getAttribute('data-service-fee'), 10) || 0;
        const gameDiscount = parseInt(checkoutForm.getAttribute('data-game-discount'), 10) || 0;

        const allowedByFulfillment = {
            dine_in: ['credit_card', 'online_pay', 'cash'],
            pickup: ['credit_card', 'online_pay'],
            delivery: ['credit_card', 'online_pay']
        };

        const fulfillmentLabels = {
            dine_in: checkoutForm.getAttribute('data-label-dine-in') || 'Dine-in (店内)',
            pickup: checkoutForm.getAttribute('data-label-pickup') || 'Store Pickup',
            delivery: checkoutForm.getAttribute('data-label-delivery') || 'Local Delivery',
            '': checkoutForm.getAttribute('data-label-not-selected') || 'Not selected'
        };

        const paymentMethodLabels = {
            credit_card: isJa ? 'クレジットカード / デビットカード' : 'Credit / Debit Card',
            online_pay: isJa ? 'オンライン決済' : 'Online Payment',
            cash: isJa ? '現金' : 'Cash',
            '': fulfillmentLabels['']
        };

        function getSelectedFulfillment() {
            const checked = checkoutForm.querySelector('.fulfillment-radio:checked');
            return checked ? checked.value : '';
        }

        function getSelectedPaymentMethod() {
            const checked = checkoutForm.querySelector('.payment-method-radio:checked:not(:disabled)');
            return checked ? checked.value : '';
        }

        function getSelectedOnlineProvider() {
            const checked = checkoutForm.querySelector('.online-provider-radio:checked:not(:disabled)');
            return checked ? checked.value : '';
        }

        function setSectionInputsEnabled(sectionEl, enabled) {
            if (!sectionEl) return;
            sectionEl.style.display = enabled ? 'block' : 'none';
            sectionEl.querySelectorAll('input, select, textarea').forEach(field => {
                field.disabled = !enabled;
            });
        }

        function updatePaymentSummaryDisplay() {
            if (!paymentDisplay) return;
            const method = getSelectedPaymentMethod();
            if (!method) {
                paymentDisplay.textContent = paymentMethodLabels[''];
                return;
            }
            let label = paymentMethodLabels[method] || method;
            if (method === 'online_pay') {
                const prov = getSelectedOnlineProvider();
                if (prov) {
                    label += ' (' + prov + ')';
                }
            }
            paymentDisplay.textContent = label;
        }

        function updatePaymentSubpanels() {
            const fulfillment = getSelectedFulfillment();
            const method = getSelectedPaymentMethod();

            paymentOptionLabels.forEach(lbl => {
                const radio = lbl.querySelector('.payment-method-radio');
                lbl.classList.toggle('selected', Boolean(radio && radio.checked && !radio.disabled));
            });

            const isCard = (method === 'credit_card');
            const isOnline = (method === 'online_pay');
            const isCash = (method === 'cash' && fulfillment === 'dine_in');

            if (cardPaymentForm) {
                cardPaymentForm.style.display = isCard ? 'block' : 'none';
                cardPaymentForm.querySelectorAll('input').forEach(inp => {
                    inp.disabled = !isCard;
                });
            }
            if (onlinePaymentForm) {
                onlinePaymentForm.style.display = isOnline ? 'block' : 'none';
                providerRadios.forEach(radio => {
                    radio.disabled = !isOnline;
                    if (!isOnline && radio.checked) {
                        radio.checked = false;
                    }
                });
            }
            if (cashPaymentInfo) {
                cashPaymentInfo.style.display = isCash ? 'block' : 'none';
            }

            updateOnlineProviderUI();
            updatePaymentSummaryDisplay();
        }

        function updateOnlineProviderUI() {
            const selectedProvider = getSelectedOnlineProvider();
            providerCards.forEach(card => {
                const radio = card.querySelector('.online-provider-radio');
                card.classList.toggle('active', Boolean(radio && radio.checked && !radio.disabled));
            });

            if (providerSelectedBox && providerSelectedText) {
                if (selectedProvider) {
                    providerSelectedText.textContent = '✓ ' + selectedProvider + ': Demo Payment Information';
                    providerSelectedBox.style.display = 'block';
                } else {
                    providerSelectedText.textContent = '';
                    providerSelectedBox.style.display = 'none';
                }
            }
            updatePaymentSummaryDisplay();
        }

        function updateAvailablePaymentMethods(fulfillment) {
            const hasFulfillment = Boolean(fulfillment && allowedByFulfillment[fulfillment]);

            if (paymentPromptMsg) {
                paymentPromptMsg.style.display = hasFulfillment ? 'none' : 'block';
            }
            if (paymentMethodsList) {
                paymentMethodsList.style.display = hasFulfillment ? 'block' : 'none';
            }

            const allowed = hasFulfillment ? allowedByFulfillment[fulfillment] : [];

            paymentOptionLabels.forEach(label => {
                const code = label.getAttribute('data-payment-code');
                const radio = label.querySelector('.payment-method-radio');
                const isAllowed = allowed.includes(code);

                label.style.display = isAllowed ? 'flex' : 'none';
                if (radio) {
                    radio.disabled = !isAllowed;
                    if (!isAllowed && radio.checked) {
                        radio.checked = false;
                    }
                }
            });

            if (cashUnavailableNote) {
                cashUnavailableNote.style.display = (fulfillment === 'pickup' || fulfillment === 'delivery') ? 'block' : 'none';
            }

            updatePaymentSubpanels();
        }

        function updateCheckoutTotals() {
            const fulfillment = getSelectedFulfillment();
            const isDelivery = fulfillment === 'delivery';

            // Service fee and delivery fee apply ONLY to Local Delivery
            const appliedServiceFee = isDelivery ? configuredServiceFee : 0;
            let deliveryFee = 0;

            if (isDelivery && distanceSelect) {
                const selectedOption = distanceSelect.options[distanceSelect.selectedIndex];
                deliveryFee = parseInt(selectedOption ? selectedOption.getAttribute('data-fee') : 0, 10) || 0;
            }

            if (fulfillmentDisplay) {
                fulfillmentDisplay.textContent = fulfillmentLabels[fulfillment] || fulfillmentLabels[''];
            }

            if (serviceFeeRow) {
                serviceFeeRow.style.display = isDelivery ? 'flex' : 'none';
            }
            if (serviceFeeDisplay) {
                serviceFeeDisplay.textContent = '¥' + appliedServiceFee.toLocaleString('en-US');
            }

            if (deliveryFeeRow) {
                deliveryFeeRow.style.display = isDelivery ? 'flex' : 'none';
            }
            if (deliveryFeeDisplay) {
                deliveryFeeDisplay.textContent = '¥' + deliveryFee.toLocaleString('en-US');
            }

            const grand = Math.max(0, baseSubtotal + customTotal + appliedServiceFee + deliveryFee - gameDiscount);
            if (grandTotalDisplay) {
                grandTotalDisplay.textContent = '¥' + grand.toLocaleString('en-US');
            }
        }

        function syncFulfillmentUI() {
            const fulfillment = getSelectedFulfillment();

            fulfillmentCards.forEach(card => {
                const cardType = card.getAttribute('data-fulfillment');
                card.classList.toggle('active', cardType === fulfillment);
            });

            setSectionInputsEnabled(dineInSection, fulfillment === 'dine_in');
            setSectionInputsEnabled(pickupSection, fulfillment === 'pickup');
            setSectionInputsEnabled(deliverySection, fulfillment === 'delivery');

            updateAvailablePaymentMethods(fulfillment);
            updateCheckoutTotals();
        }

        fulfillmentRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                hideClientErrors();
                syncFulfillmentUI();
            });
        });

        fulfillmentCards.forEach(card => {
            card.addEventListener('click', () => {
                const radio = card.querySelector('.fulfillment-radio');
                if (radio && !radio.checked) {
                    radio.checked = true;
                    hideClientErrors();
                    syncFulfillmentUI();
                }
            });
        });

        paymentRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                hideClientErrors();
                updatePaymentSubpanels();
            });
        });

        paymentOptionLabels.forEach(label => {
            label.addEventListener('click', (e) => {
                const radio = label.querySelector('.payment-method-radio');
                if (!radio || radio.disabled) {
                    e.preventDefault();
                    return;
                }
                if (!radio.checked) {
                    radio.checked = true;
                    hideClientErrors();
                    updatePaymentSubpanels();
                }
            });
        });

        providerRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                hideClientErrors();
                updateOnlineProviderUI();
            });
        });

        providerCards.forEach(card => {
            card.addEventListener('click', (e) => {
                const radio = card.querySelector('.online-provider-radio');
                if (!radio || radio.disabled) {
                    e.preventDefault();
                    return;
                }
                if (!radio.checked) {
                    radio.checked = true;
                    hideClientErrors();
                    updateOnlineProviderUI();
                }
            });
        });

        if (distanceSelect) {
            distanceSelect.addEventListener('change', updateCheckoutTotals);
        }

        // Input formatting helpers for Credit / Debit Card fields
        if (cardNumberInput) {
            cardNumberInput.addEventListener('input', () => {
                const digits = cardNumberInput.value.replace(/\D/g, '').slice(0, 16);
                cardNumberInput.value = digits.replace(/(\d{4})(?=\d)/g, '$1 ');
            });
        }

        if (cardExpiryInput) {
            cardExpiryInput.addEventListener('input', () => {
                const digits = cardExpiryInput.value.replace(/\D/g, '').slice(0, 4);
                if (digits.length >= 3) {
                    cardExpiryInput.value = digits.slice(0, 2) + '/' + digits.slice(2);
                } else {
                    cardExpiryInput.value = digits;
                }
            });
        }

        if (cardCvvInput) {
            cardCvvInput.addEventListener('input', () => {
                cardCvvInput.value = cardCvvInput.value.replace(/\D/g, '').slice(0, 4);
            });
        }

        function hideClientErrors() {
            if (clientErrorBox) clientErrorBox.style.display = 'none';
            if (clientErrorList) clientErrorList.innerHTML = '';
            checkoutForm.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        }

        function showClientErrors(messages) {
            if (!clientErrorBox || !clientErrorList) return;
            clientErrorList.innerHTML = '';
            messages.forEach(msg => {
                const li = document.createElement('li');
                li.textContent = msg;
                clientErrorList.appendChild(li);
            });
            clientErrorBox.style.display = 'block';
            clientErrorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Client-side validation before submitting order
        checkoutForm.addEventListener('submit', (e) => {
            hideClientErrors();
            const errs = [];
            const fulfillment = getSelectedFulfillment();
            const paymentMethod = getSelectedPaymentMethod();

            // 1. Validate fulfillment option
            if (!fulfillment || !allowedByFulfillment[fulfillment]) {
                errs.push(isJa
                    ? 'お受け取り方法（店内・店頭受取・デリバリー）を選択してください。'
                    : 'Please select a fulfillment option (Dine-in, Store Pickup, or Local Delivery).');
            }

            // 2. Validate fulfillment-specific fields
            if (fulfillment === 'pickup') {
                const pDate = document.getElementById('pickup-date');
                const pTime = document.getElementById('pickup-time');
                if (!pDate || !pDate.value || !pTime || !pTime.value) {
                    errs.push(isJa
                        ? '店頭受取の日付と時間帯を選択してください。'
                        : 'Please select a pickup date and time slot.');
                    if (pDate && !pDate.value) pDate.classList.add('input-error');
                    if (pTime && !pTime.value) pTime.classList.add('input-error');
                }
            } else if (fulfillment === 'delivery') {
                const dDate = document.getElementById('delivery-date');
                const dTime = document.getElementById('delivery-time');
                const postal = document.getElementById('postal-code');
                const addr = document.getElementById('street-address');
                if (!dDate || !dDate.value || !dTime || !dTime.value) {
                    errs.push(isJa
                        ? '配達希望日と時間帯を選択してください。'
                        : 'Please select a delivery date and time slot.');
                }
                if (!postal || !postal.value.trim() || !addr || !addr.value.trim()) {
                    errs.push(isJa
                        ? '配達先の郵便番号とご住所を入力してください。'
                        : 'Please provide a full delivery postal code and street address.');
                    if (postal && !postal.value.trim()) postal.classList.add('input-error');
                    if (addr && !addr.value.trim()) addr.classList.add('input-error');
                }
            }

            // 3. Validate customer contact info
            const custName = document.getElementById('customer-name');
            const custEmail = document.getElementById('customer-email');
            const custPhone = document.getElementById('customer-phone');
            if (!custName || !custName.value.trim() || !custEmail || !custEmail.value.trim() || !custPhone || !custPhone.value.trim()) {
                errs.push(isJa
                    ? 'お名前、メールアドレス、お電話番号を入力してください。'
                    : 'Please fill in your name, email, and phone number.');
                if (custName && !custName.value.trim()) custName.classList.add('input-error');
                if (custEmail && !custEmail.value.trim()) custEmail.classList.add('input-error');
                if (custPhone && !custPhone.value.trim()) custPhone.classList.add('input-error');
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(custEmail.value.trim())) {
                errs.push(isJa
                    ? '有効なメールアドレスを入力してください。'
                    : 'Please enter a valid email address.');
                custEmail.classList.add('input-error');
            }

            // 4. Validate payment method
            if (!paymentMethod) {
                errs.push(isJa
                    ? 'お支払い方法を選択してください。'
                    : 'Please select a payment method.');
            } else if (fulfillment && !allowedByFulfillment[fulfillment].includes(paymentMethod)) {
                errs.push(isJa
                    ? '選択されたお受け取り方法では、このお支払い方法はご利用いただけません。'
                    : 'The selected payment method is not available for the chosen fulfillment option.');
            }

            // 5. Validate Credit / Debit Card fields
            if (paymentMethod === 'credit_card') {
                const holderVal = cardholderInput ? cardholderInput.value.trim() : '';
                const numVal = cardNumberInput ? cardNumberInput.value.replace(/[\s\-]+/g, '') : '';
                const expVal = cardExpiryInput ? cardExpiryInput.value.trim() : '';
                const cvvVal = cardCvvInput ? cardCvvInput.value.trim() : '';

                if (holderVal.length < 2) {
                    errs.push(isJa ? 'カード名義人を入力してください。' : 'Please enter the cardholder name.');
                    if (cardholderInput) cardholderInput.classList.add('input-error');
                }
                if (!/^\d{13,19}$/.test(numVal)) {
                    errs.push(isJa ? '有効なカード番号（13〜19桁の数字）を入力してください。' : 'Please enter a valid card number (13 to 19 digits).');
                    if (cardNumberInput) cardNumberInput.classList.add('input-error');
                }
                const expMatch = expVal.match(/^(0[1-9]|1[0-2])\/(\d{2})$/);
                if (!expMatch) {
                    errs.push(isJa ? '有効期限は MM/YY 形式で入力してください（例: 08/28）。' : 'Please enter a valid expiration date in MM/YY format (e.g. 08/28).');
                    if (cardExpiryInput) cardExpiryInput.classList.add('input-error');
                } else {
                    const expMonth = parseInt(expMatch[1], 10);
                    const expYear = 2000 + parseInt(expMatch[2], 10);
                    const now = new Date();
                    const curYear = now.getFullYear();
                    const curMonth = now.getMonth() + 1;
                    if (expYear < curYear || (expYear === curYear && expMonth < curMonth)) {
                        errs.push(isJa ? 'カードの有効期限が切れています。' : 'The card expiration date has passed.');
                        if (cardExpiryInput) cardExpiryInput.classList.add('input-error');
                    }
                }
                if (!/^\d{3,4}$/.test(cvvVal)) {
                    errs.push(isJa ? '有効なセキュリティコード（CVV：3〜4桁の数字）を入力してください。' : 'Please enter a valid CVV (3 or 4 digits).');
                    if (cardCvvInput) cardCvvInput.classList.add('input-error');
                }
            }

            // 6. Validate Online Payment Provider
            if (paymentMethod === 'online_pay') {
                const selectedProv = getSelectedOnlineProvider();
                if (!selectedProv) {
                    errs.push(isJa
                        ? 'オンライン決済サービス（PayPay、au PAY、Rakuten Pay）のいずれかを選択してください。'
                        : 'Please select an online payment provider (PayPay, au PAY, or Rakuten Pay).');
                }
            }

            if (errs.length > 0) {
                e.preventDefault();
                showClientErrors(errs);
            }
        });

        // Initialize UI state on page load
        syncFulfillmentUI();
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

    // 6. Header Scroll Transition (Transparent to Solid on homepage)
    const siteNavbar = document.querySelector('.site-navbar');
    if (siteNavbar && document.body.classList.contains('page-home')) {
        const handleScroll = () => {
            if (window.scrollY > 40) {
                siteNavbar.classList.add('scrolled');
            } else {
                siteNavbar.classList.remove('scrolled');
            }
        };
        window.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll();
    }
});
