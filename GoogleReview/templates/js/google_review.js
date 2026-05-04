(() => {
  function init() {
    const track = document.getElementById('bremerReviewsTrack');
    const prevBtn = document.getElementById('bremerPrevBtn');
    const nextBtn = document.getElementById('bremerNextBtn');
    const sliderContainer = document.querySelector('.bremer-grw-reviews-slider');
    if (!track || !prevBtn || !nextBtn || !sliderContainer) {
      return;
    }

    let currentIndex = 0;
    let isTransitioning = false;
    let visibleCards = 4;

    const getCards = () => track.querySelectorAll('.bremer-grw-review-card');
    const getTotal = () => getCards().length;
    const getMaxIndex = () => Math.max(0, getTotal() - visibleCards);

    function calculateVisibleCards() {
      const width = window.innerWidth;
      if (width <= 480) return 1;
      if (width <= 768) return 2;
      if (width <= 1024) return 3;
      return 4;
    }

    function getCardWidth() {
      const card = track.querySelector('.bremer-grw-review-card');
      if (!card) return 300;
      const rect = card.getBoundingClientRect();
      // CSS gap is 20px; approximate with width + gap.
      return Math.max(220, Math.round(rect.width + 20));
    }

    function updateSlider() {
      const cardWidth = getCardWidth();
      const translateX = -currentIndex * cardWidth;
      track.style.transform = `translateX(${translateX}px)`;
    }

    function updateButtonStates() {
      const maxIndex = getMaxIndex();
      const disablePrev = currentIndex === 0 && maxIndex === 0;
      const disableNext = currentIndex === maxIndex && maxIndex > 0;
      prevBtn.classList.toggle('bremer-grw-disabled', disablePrev);
      nextBtn.classList.toggle('bremer-grw-disabled', disableNext);
    }

    function prevSlide() {
      if (isTransitioning) return;
      const maxIndex = getMaxIndex();
      if (currentIndex > 0) currentIndex -= 1;
      else currentIndex = maxIndex;
      isTransitioning = true;
      updateSlider();
      updateButtonStates();
      setTimeout(() => { isTransitioning = false; }, 400);
    }

    function nextSlide() {
      if (isTransitioning) return;
      const maxIndex = getMaxIndex();
      if (currentIndex < maxIndex) currentIndex += 1;
      else currentIndex = 0;
      isTransitioning = true;
      updateSlider();
      updateButtonStates();
      setTimeout(() => { isTransitioning = false; }, 400);
    }

    prevBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (!prevBtn.classList.contains('bremer-grw-disabled')) prevSlide();
    });
    nextBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (!nextBtn.classList.contains('bremer-grw-disabled')) nextSlide();
    });

    // Touch swipe
    let touchStartX = 0;
    let touchEndX = 0;
    track.addEventListener('touchstart', (e) => {
      touchStartX = e.touches[0].clientX;
      track.style.transition = 'none';
    }, { passive: true });

    track.addEventListener('touchmove', (e) => {
      // We keep it simple; prevent jitter by not applying live transform.
      // (CSS handles the smooth transition on click.)
    }, { passive: true });

    track.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].clientX;
      const diff = touchStartX - touchEndX;
      track.style.transition = 'transform 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
      const threshold = 50;
      if (Math.abs(diff) > threshold) {
        if (diff > 0) nextSlide();
        else prevSlide();
      }
    }, { passive: true });

    window.addEventListener('resize', () => {
      visibleCards = calculateVisibleCards();
      currentIndex = Math.min(currentIndex, getMaxIndex());
      updateSlider();
      updateButtonStates();
    });

    // Optional: wheel horizontal navigation
    if (sliderContainer) {
      sliderContainer.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) {
          e.preventDefault();
          if (e.deltaX > 0) nextSlide();
          else prevSlide();
        }
      }, { passive: false });
    }

    visibleCards = calculateVisibleCards();
    updateSlider();
    updateButtonStates();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

