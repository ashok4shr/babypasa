document.addEventListener('DOMContentLoaded', function() {
    // CSS Snap Slider Logic
    const sliders = document.querySelectorAll('.bp-slider-container');

    sliders.forEach(slider => {
        const track = slider.querySelector('.bp-slider-track');
        
        // Find buttons relative to parent section
        const section = slider.closest('section');
        if (!section) return;

        const btnPrev = section.querySelector('.bp-prev-btn');
        const btnNext = section.querySelector('.bp-next-btn');

        if (btnPrev && btnNext && track) {
            btnPrev.addEventListener('click', () => {
                const slide = track.firstElementChild;
                if (!slide) return;

                if (slider.classList.contains('bp-hero-slider')) {
                    if (track.scrollLeft <= 0) {
                        track.style.scrollBehavior = 'auto';
                        track.prepend(track.lastElementChild);
                        track.scrollLeft += slide.offsetWidth;
                        // force reflow
                        void track.offsetWidth;
                        track.style.scrollBehavior = 'smooth';
                    }
                    track.scrollBy({ left: -slide.offsetWidth, behavior: 'smooth' });
                } else {
                    let gap = slider.classList.contains('bp-product-slider') ? 20 : 0;
                    track.scrollBy({ left: -(slide.offsetWidth + gap), behavior: 'smooth' });
                }
            });

            btnNext.addEventListener('click', () => {
                const slide = track.firstElementChild;
                if (!slide) return;

                if (slider.classList.contains('bp-hero-slider')) {
                    if (Math.ceil(track.scrollLeft + track.clientWidth) >= track.scrollWidth - 5) {
                        track.style.scrollBehavior = 'auto';
                        track.appendChild(track.firstElementChild);
                        track.scrollLeft -= slide.offsetWidth;
                        // force reflow
                        void track.offsetWidth;
                        track.style.scrollBehavior = 'smooth';
                    }
                    track.scrollBy({ left: slide.offsetWidth, behavior: 'smooth' });
                } else {
                    let gap = slider.classList.contains('bp-product-slider') ? 20 : 0;
                    track.scrollBy({ left: slide.offsetWidth + gap, behavior: 'smooth' });
                }
            });
        }
    });
});
