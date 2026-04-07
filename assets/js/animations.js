document.addEventListener('DOMContentLoaded', function () {
    // Initialize AOS (Animate On Scroll)
    AOS.init({
        duration: 800, // Default duration 800ms
        easing: 'ease-out-cubic',
        once: true, // Whether animation should happen only once - while scrolling down
        offset: 100, // Offset (in px) from the original trigger point
    });

    // --- Custom Scroll Effects ---

    // Parallax Effect for Hero Section
    const heroSection = document.querySelector('.hero-section');
    if (heroSection) {
        window.addEventListener('scroll', () => {
            const scrolled = window.pageYOffset;
            const rate = 0.5;
            heroSection.style.backgroundPositionY = (scrolled * rate) + 'px';
        });
    }

    // Transparency Scroll Effect (Fade out elements as they scroll out of view)
    const fadeElements = document.querySelectorAll('.scroll-fade-out');
    if (fadeElements.length > 0) {
        window.addEventListener('scroll', () => {
            fadeElements.forEach(el => {
                const rect = el.getBoundingClientRect();
                const viewHeight = Math.max(document.documentElement.clientHeight, window.innerHeight);

                // Calculate opacity based on position (starts fading out when top reaches 10% from top)
                if (rect.top < viewHeight * 0.1) {
                    const opacity = 1 - (viewHeight * 0.1 - rect.top) / 300;
                    el.style.opacity = Math.max(0, opacity);
                } else {
                    el.style.opacity = 1;
                }
            });
        });
    }

    // Blur Scroll Effect
    const blurElements = document.querySelectorAll('.scroll-blur');
    if (blurElements.length > 0) {
        window.addEventListener('scroll', () => {
            blurElements.forEach(el => {
                const rect = el.getBoundingClientRect();
                const viewHeight = window.innerHeight;

                // Blur increases as element moves out of center
                const distanceFromCenter = Math.abs(rect.top + rect.height / 2 - viewHeight / 2);
                const blurAmount = Math.min(10, distanceFromCenter / 100); // Max 10px blur

                // Only apply if requested (maybe too heavy for all elements, use selectively)
                // el.style.filter = `blur(${blurAmount}px)`; 
            });
        });
    }

    // Number Counter Animation
    const counters = document.querySelectorAll('.counter');
    const speed = 200; // The lower the slower

    const animateCounters = () => {
        counters.forEach(counter => {
            const updateCount = () => {
                const target = +counter.getAttribute('data-target');
                const count = +counter.innerText.replace(/,/g, '').replace(/\+/g, ''); // Remove commas and plus signs for calculation

                // Lower inc to slow and higher to slow
                const inc = target / speed;

                if (count < target) {
                    // Add inc to count and output in counter
                    counter.innerText = Math.ceil(count + inc);
                    // Call function every ms
                    setTimeout(updateCount, 20);
                } else {
                    counter.innerText = target;
                    // Re-append suffix if present (like + or %)
                    const originalText = counter.getAttribute('data-original-text');
                    if (originalText && originalText.includes('+')) counter.innerText += '+';
                    if (originalText && originalText.includes('%')) counter.innerText += '%';
                }
            };
            updateCount();
        });
    };

    // Trigger counter animation when stats section is in view
    let counterTriggered = false;
    const statsSection = document.querySelector('.about-section'); // Or specific stats container
    if (statsSection) {
        window.addEventListener('scroll', () => {
            const sectionPos = statsSection.getBoundingClientRect().top;
            const screenPos = window.innerHeight / 1.3;

            if (sectionPos < screenPos && !counterTriggered) {
                animateCounters();
                counterTriggered = true;
            }
        });
    }
});
