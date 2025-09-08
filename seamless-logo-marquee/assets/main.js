/**
 * Seamless Logo Marquee with Magnifying Glass Effect
 *
 * This script creates a smooth, infinitely scrolling marquee.
 * It also applies a "magnifying glass" effect, scaling up logos
 * as they pass through the center of the container.
 */
document.addEventListener('DOMContentLoaded', function() {
    // Find all marquee tracks on the page to support multiple sliders
    const allTracks = document.querySelectorAll('.marquee-track');

    allTracks.forEach(track => {
        const logoSlide = track.querySelector('.logo-slide');
        if (!logoSlide || logoSlide.children.length === 0) {
            // If there are no logos, do nothing.
            return;
        }

        // Find the main container to calculate the center point for the effect
        const container = track.closest('.marquee-container');
        if (!container) {
            console.error('Marquee container not found for track:', track);
            return;
        }

        // Get parameters from PHP (passed via wp_localize_script)
        // Use default values if the parameters are not set
        const params = window.slm_params || { speed: 1, direction: 'rtl' };

        // Clone the logo slide to create a seamless loop
        // The number of clones depends on the viewport width to ensure no gaps
        const cloneCount = Math.ceil(window.innerWidth / logoSlide.offsetWidth) + 2;
        for (let i = 0; i < cloneCount; i++) {
            track.appendChild(logoSlide.cloneNode(true));
        }

        // Get all individual logo wrappers, including the cloned ones
        const allLogos = track.querySelectorAll('.logo-wrapper');

        // Animation control variables
        let scrollPosition = 0;
        const speed = parseFloat(params.speed) || 1;
        let isPlaying = true;
        let animationFrameId = null;

        // The main animation loop, rewritten for the new effect
        function animate() {
            if (!isPlaying) return; // Pause the animation if not playing

            // --- TASK 1: MOVE THE ENTIRE TRACK (Original scrolling logic) ---
            if (params.direction === 'ltr') { // Left to Right
                scrollPosition += speed;
            } else { // Right to Left (default)
                scrollPosition -= speed;
            }
            
            const slideWidth = logoSlide.offsetWidth;

            // Reset the position to create an infinite loop
            if (params.direction === 'ltr' && scrollPosition >= 0) {
                 scrollPosition -= slideWidth;
            } else if (params.direction === 'rtl' && Math.abs(scrollPosition) >= slideWidth) {
                 scrollPosition += slideWidth;
            }

            track.style.transform = `translateX(${scrollPosition}px)`;

            // --- TASK 2: CALCULATE AND APPLY THE MAGNIFYING GLASS EFFECT ---
            const containerRect = container.getBoundingClientRect();
            const containerCenter = containerRect.left + containerRect.width / 2;

            // Parameters for the magnifying effect
            const maxScale = 1.5;      // The maximum scale (150%)
            const effectRadius = 150;  // The radius of the effect from the center (in pixels)

            // Loop through each logo to calculate its scale
            allLogos.forEach(logo => {
                const logoRect = logo.getBoundingClientRect();
                const logoCenter = logoRect.left + logoRect.width / 2;
                
                // Calculate the distance from the logo's center to the container's center
                const distance = Math.abs(containerCenter - logoCenter);
                
                let scale = 1.0; // Default scale

                // If the logo is within the effect radius...
                if (distance < effectRadius) {
                    // ...calculate its scale. The closer to the center, the larger the scale.
                    scale = maxScale - (distance / effectRadius) * (maxScale - 1);
                }
                
                // Apply the scale transformation to the individual logo
                logo.style.transform = `scale(${scale})`;
            });


            // Request the browser to draw the next frame
            animationFrameId = requestAnimationFrame(animate);
        }

        // Pause/resume animation on hover
        track.addEventListener("mouseenter", () => { isPlaying = false; });
        track.addEventListener("mouseleave", () => { isPlaying = true; requestAnimationFrame(animate); });
        
        // Start the animation
        requestAnimationFrame(animate);
    });
});