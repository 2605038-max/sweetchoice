/**
 * Sweet Choice - Hero Banner Slider
 * Auto-sliding, touch-compatible, responsive banner carousel with dots and arrows.
 */
document.addEventListener('DOMContentLoaded', () => {
    const slider = document.querySelector('.hero-slider-section');
    if (!slider) return;

    const wrapper = slider.querySelector('.slides-wrapper');
    const slides = slider.querySelectorAll('.hero-slide');
    const prevBtn = slider.querySelector('.slider-prev');
    const nextBtn = slider.querySelector('.slider-next');
    const dotsContainer = slider.querySelector('.slider-dots');

    if (!slides.length) return;

    let currentIndex = 0;
    let autoSlideInterval = null;
    const totalSlides = slides.length;

    // Create dots if container exists
    if (dotsContainer) {
        dotsContainer.innerHTML = '';
        slides.forEach((_, idx) => {
            const dot = document.createElement('div');
            dot.classList.add('slider-dot');
            if (idx === 0) dot.classList.add('active');
            dot.addEventListener('click', () => goToSlide(idx));
            dotsContainer.appendChild(dot);
        });
    }

    const dots = dotsContainer ? dotsContainer.querySelectorAll('.slider-dot') : [];

    function updateSlide() {
        wrapper.style.transform = `translateX(-${currentIndex * 100}%)`;
        dots.forEach((dot, idx) => {
            dot.classList.toggle('active', idx === currentIndex);
        });
    }

    function goToSlide(index) {
        currentIndex = (index + totalSlides) % totalSlides;
        updateSlide();
        resetTimer();
    }

    function nextSlide() {
        goToSlide(currentIndex + 1);
    }

    function prevSlide() {
        goToSlide(currentIndex - 1);
    }

    if (nextBtn) nextBtn.addEventListener('click', nextSlide);
    if (prevBtn) prevBtn.addEventListener('click', prevSlide);

    function startTimer() {
        if (!autoSlideInterval && totalSlides > 1) {
            autoSlideInterval = setInterval(nextSlide, 5000);
        }
    }

    function stopTimer() {
        if (autoSlideInterval) {
            clearInterval(autoSlideInterval);
            autoSlideInterval = null;
        }
    }

    function resetTimer() {
        stopTimer();
        startTimer();
    }

    // Pause on mouse hover
    slider.addEventListener('mouseenter', stopTimer);
    slider.addEventListener('mouseleave', startTimer);

    // Mobile touch swipe gestures
    let startX = 0;
    let endX = 0;

    slider.addEventListener('touchstart', (e) => {
        startX = e.touches[0].clientX;
        stopTimer();
    }, { passive: true });

    slider.addEventListener('touchend', (e) => {
        endX = e.changedTouches[0].clientX;
        const diffX = startX - endX;
        if (Math.abs(diffX) > 40) {
            if (diffX > 0) nextSlide();
            else prevSlide();
        }
        startTimer();
    }, { passive: true });

    startTimer();
});
