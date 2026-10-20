/**
 * Sweet Choice - AI Dessert Recommendation Frontend Controller
 * Manages interactive mood and flavor taste selection and two-step vertically stacked live recommendations.
 */
function initRecommendation() {
    const aiSection = document.getElementById('ai-recommendation-section');
    if (!aiSection) return;

    const moodPills = aiSection.querySelectorAll('.ai-mood-pill');
    const tastePills = aiSection.querySelectorAll('.ai-taste-pill');
    const findBtn = document.getElementById('ai-find-btn');
    const selectionCard = document.getElementById('ai-selection-card');
    const resultSection = document.getElementById('ai-result-section');
    const resultContainer = document.getElementById('ai-result-container');
    const chooseAgainBtn = document.getElementById('ai-choose-again-btn');

    // Localized text dictionary from data attributes
    const i18n = {
        viewDetails: aiSection.dataset.langViewDetails || 'View Details',
        addToCart: aiSection.dataset.langAddToCart || 'Add to Cart',
        soldOut: aiSection.dataset.langSoldOut || 'Sold Out',
        aiSommelier: aiSection.dataset.langAiSommelier || 'AI Sommelier',
        loading: aiSection.dataset.langLoading || 'Analyzing flavor profiles & pairing desserts...',
        chooseAgain: aiSection.dataset.langChooseAgain || 'Choose Again',
        noMatch: aiSection.dataset.langNoMatch || 'No desserts found matching this pairing. Try another mood or flavor!'
    };

    // Determine initial active mood & taste
    let selectedMood = 'happy';
    let selectedTaste = 'cake';

    const initialActiveMood = aiSection.querySelector('.ai-mood-pill.active');
    if (initialActiveMood && initialActiveMood.getAttribute('data-mood')) {
        selectedMood = initialActiveMood.getAttribute('data-mood');
    }

    const initialActiveTaste = aiSection.querySelector('.ai-taste-pill.active');
    if (initialActiveTaste && initialActiveTaste.getAttribute('data-taste')) {
        selectedTaste = initialActiveTaste.getAttribute('data-taste');
    }

    let hasRevealed = false;

    // Mood pill selection
    moodPills.forEach(pill => {
        pill.addEventListener('click', () => {
            moodPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            selectedMood = pill.getAttribute('data-mood');

            // If result is already revealed, highlight find button
            if (hasRevealed && findBtn) {
                findBtn.classList.add('pulse-ready');
            }
        });
    });

    // Taste pill selection
    tastePills.forEach(pill => {
        pill.addEventListener('click', () => {
            tastePills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            selectedTaste = pill.getAttribute('data-taste');

            // If result is already revealed, highlight find button
            if (hasRevealed && findBtn) {
                findBtn.classList.add('pulse-ready');
            }
        });
    });

    // Step 2 Trigger: Find My Perfect Dessert
    if (findBtn) {
        findBtn.addEventListener('click', (e) => {
            e.preventDefault();
            findBtn.classList.remove('pulse-ready');
            revealAndFetch();
        });
    }

    // "Choose Again" button action (scroll smoothly back to selection form)
    if (chooseAgainBtn) {
        chooseAgainBtn.addEventListener('click', (e) => {
            e.preventDefault();
            scrollToSelectionForm();
        });
    }

    function scrollToSelectionForm() {
        if (!selectionCard) return;
        selectionCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        selectionCard.classList.remove('ai-highlight-card');
        void selectionCard.offsetWidth; // force reflow for re-animation
        selectionCard.classList.add('ai-highlight-card');
        setTimeout(() => {
            selectionCard.classList.remove('ai-highlight-card');
        }, 1500);
    }

    function formatPrice(amount) {
        return '¥' + parseInt(amount, 10).toLocaleString('en-US');
    }

    function revealAndFetch() {
        if (!resultSection || !resultContainer) return;

        // Ensure result section is visible below the form
        resultSection.removeAttribute('hidden');
        resultSection.style.display = 'block';
        resultSection.classList.remove('ai-result-hidden');
        resultSection.classList.remove('ai-result-card-wrapper-animate');
        void resultSection.offsetWidth; // trigger reflow
        resultSection.classList.add('ai-result-card-wrapper-animate');
        hasRevealed = true;

        // Show loading state with elegant spinner
        resultContainer.innerHTML = `
            <div class="ai-prompt-placeholder">
                <div class="ai-loading-spinner">✨</div>
                <p><strong>${escapeHtml(i18n.loading)}</strong></p>
            </div>
        `;

        // Smooth scroll to the recommendation result
        resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Fetch recommendations from API
        const apiUrl = `api/recommend.php?mood=${encodeURIComponent(selectedMood)}&taste=${encodeURIComponent(selectedTaste)}`;
        fetch(apiUrl)
            .then(res => {
                if (!res.ok) {
                    throw new Error(`HTTP error! status: ${res.status}`);
                }
                return res.json();
            })
            .then(data => {
                if (data.success && data.recommendations && data.recommendations.length > 0) {
                    renderRecommendations(data.recommendations);
                } else {
                    resultContainer.innerHTML = `
                        <div class="ai-prompt-placeholder">
                            <div class="ai-placeholder-icon">🍃</div>
                            <p>${escapeHtml(i18n.noMatch)}</p>
                            <button type="button" class="btn btn-secondary btn-sm" style="margin-top:14px;" onclick="document.getElementById('ai-selection-card').scrollIntoView({behavior:'smooth',block:'center'})">
                                ↺ ${escapeHtml(i18n.chooseAgain)}
                            </button>
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error("AI Sommelier Error:", err);
                resultContainer.innerHTML = `
                    <div class="ai-prompt-placeholder">
                        <div class="ai-placeholder-icon">⚠️</div>
                        <p>Could not retrieve recommendation at this time. Please try again.</p>
                        <button type="button" class="btn btn-secondary btn-sm" style="margin-top:14px;" onclick="document.getElementById('ai-find-btn').click()">
                            ↺ Try Again
                        </button>
                    </div>
                `;
            });
    }

    function renderRecommendations(items) {
        resultContainer.innerHTML = '';

        items.forEach((item, index) => {
            const card = document.createElement('div');
            card.classList.add('ai-result-card');
            if (index === 0) {
                card.classList.add('ai-result-primary');
            }

            const isSoldOut = item.availability === 'sold_out';

            card.innerHTML = `
                <img src="../uploads/products/${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}" class="ai-result-img" onerror="this.src='../assets/images/logo_banner.jpg'">
                <div class="ai-result-info">
                    <div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px; gap:8px;">
                            <span class="product-category-tag">${escapeHtml(item.category_name)}</span>
                            ${index === 0 ? '<span class="badge" style="background:#FFF0F2; color:var(--color-deep-plum); font-weight:700;">★ Best Match</span>' : ''}
                        </div>
                        <h4 class="ai-result-name">
                            <a href="product.php?id=${item.id}" style="color:inherit; text-decoration:none;">${escapeHtml(item.name)}</a>
                        </h4>
                        <div class="ai-result-price">${formatPrice(item.price)}</div>
                        <div class="ai-result-reason">
                            <strong>${escapeHtml(i18n.aiSommelier)}:</strong> "${escapeHtml(item.recommendation_reason)}"
                        </div>
                    </div>
                    <div class="ai-result-actions">
                        <a href="product.php?id=${item.id}" class="btn btn-secondary btn-sm">${escapeHtml(i18n.viewDetails)}</a>
                        ${!isSoldOut ? `
                            <form action="cart.php" method="POST" style="display:inline; margin:0;">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="${item.id}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-primary btn-sm">${escapeHtml(i18n.addToCart)}</button>
                            </form>
                        ` : `<span class="badge badge-sold-out">${escapeHtml(i18n.soldOut)}</span>`}
                    </div>
                </div>
            `;
            resultContainer.appendChild(card);
        });

        // Add a bottom "Choose Again" action row
        const bottomAction = document.createElement('div');
        bottomAction.classList.add('ai-bottom-actions');
        bottomAction.innerHTML = `
            <button type="button" class="btn btn-secondary btn-sm ai-choose-again-bottom">
                ↺ ${escapeHtml(i18n.chooseAgain)}
            </button>
        `;
        bottomAction.querySelector('.ai-choose-again-bottom').addEventListener('click', () => {
            scrollToSelectionForm();
        });
        resultContainer.appendChild(bottomAction);
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.innerText = str;
        return div.innerHTML;
    }
}

// Ensure execution whether loaded before or after DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRecommendation);
} else {
    initRecommendation();
}
