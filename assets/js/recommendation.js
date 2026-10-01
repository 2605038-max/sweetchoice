/**
 * Sweet Choice - AI Dessert Recommendation Frontend Controller
 * Manages interactive mood and flavor taste selection and live sommelier recommendations.
 */
document.addEventListener('DOMContentLoaded', () => {
    const aiSection = document.getElementById('ai-recommendation-section');
    if (!aiSection) return;

    const moodPills = aiSection.querySelectorAll('.ai-mood-pill');
    const tastePills = aiSection.querySelectorAll('.ai-taste-pill');
    const findBtn = document.getElementById('ai-find-btn');
    const resultContainer = document.getElementById('ai-result-container');

    let selectedMood = 'happy';
    let selectedTaste = 'cake';

    moodPills.forEach(pill => {
        pill.addEventListener('click', () => {
            moodPills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            selectedMood = pill.getAttribute('data-mood');
            fetchRecommendation();
        });
    });

    tastePills.forEach(pill => {
        pill.addEventListener('click', () => {
            tastePills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            selectedTaste = pill.getAttribute('data-taste');
            fetchRecommendation();
        });
    });

    if (findBtn) {
        findBtn.addEventListener('click', (e) => {
            e.preventDefault();
            fetchRecommendation();
        });
    }

    function formatPrice(amount) {
        return '¥' + parseInt(amount, 10).toLocaleString('en-US');
    }

    function fetchRecommendation() {
        if (!resultContainer) return;

        resultContainer.innerHTML = `
            <div class="ai-prompt-placeholder">
                <div class="ai-placeholder-icon">✨</div>
                <p><strong>Analyzing flavor profiles & pairing desserts...</strong></p>
            </div>
        `;

        fetch(`api/recommend.php?mood=${encodeURIComponent(selectedMood)}&taste=${encodeURIComponent(selectedTaste)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.recommendations && data.recommendations.length > 0) {
                    renderRecommendations(data.recommendations);
                } else {
                    resultContainer.innerHTML = `
                        <div class="ai-prompt-placeholder">
                            <p>No desserts found for this combination. Try another mood or taste!</p>
                        </div>
                    `;
                }
            })
            .catch(err => {
                console.error("AI Sommelier Error:", err);
                resultContainer.innerHTML = `
                    <div class="ai-prompt-placeholder">
                        <p>Could not retrieve recommendation at this time. Please try again.</p>
                    </div>
                `;
            });
    }

    function renderRecommendations(items) {
        resultContainer.innerHTML = '';
        items.forEach(item => {
            const card = document.createElement('div');
            card.classList.add('ai-result-card');

            const isSoldOut = item.availability === 'sold_out';

            card.innerHTML = `
                <img src="../uploads/products/${item.image}" alt="${escapeHtml(item.name)}" class="ai-result-img" onerror="this.src='../assets/images/logo_banner.jpg'">
                <div class="ai-result-info">
                    <div>
                        <div class="product-category-tag">${escapeHtml(item.category_name)}</div>
                        <h4 class="ai-result-name">${escapeHtml(item.name)}</h4>
                        <div class="ai-result-price">${formatPrice(item.price)}</div>
                        <div class="ai-result-reason">
                            <strong>AI Sommelier:</strong> "${escapeHtml(item.recommendation_reason)}"
                        </div>
                    </div>
                    <div class="ai-result-actions">
                        <a href="product.php?id=${item.id}" class="btn btn-secondary btn-sm">View Details</a>
                        ${!isSoldOut ? `
                            <form action="cart.php" method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="${item.id}">
                                <input type="hidden" name="quantity" value="1">
                                <button type="submit" class="btn btn-primary btn-sm">Add to Cart</button>
                            </form>
                        ` : `<span class="badge badge-sold-out">Sold Out</span>`}
                    </div>
                </div>
            `;
            resultContainer.appendChild(card);
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.innerText = str;
        return div.innerHTML;
    }
});
