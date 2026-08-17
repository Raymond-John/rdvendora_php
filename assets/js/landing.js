/**
 * RD Vendora - Landing Page JavaScript
 * Pricing toggle, smooth scroll, and landing-specific interactions
 */

document.addEventListener('DOMContentLoaded', () => {
  // Pricing Toggle (Monthly/Yearly)
  const pricingToggle = document.getElementById('pricingToggle');
  const priceValues = document.querySelectorAll('.price-value');
  const toggleLabels = document.querySelectorAll('.toggle-label');
  let isYearly = false;

  if (pricingToggle) {
    pricingToggle.addEventListener('click', () => {
      isYearly = !isYearly;
      pricingToggle.classList.toggle('active', isYearly);

      toggleLabels.forEach(label => {
        label.classList.toggle('active', 
          (label.dataset.period === 'yearly' && isYearly) || 
          (label.dataset.period === 'monthly' && !isYearly)
        );
      });

      priceValues.forEach(el => {
        const target = isYearly ? el.dataset.yearly : el.dataset.monthly;
        animatePrice(el, parseInt(el.textContent), parseInt(target));
      });
    });
  }

  function animatePrice(el, from, to) {
    const duration = 300;
    const start = performance.now();
    
    function update(currentTime) {
      const elapsed = currentTime - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      const current = Math.round(from + (to - from) * eased);
      el.textContent = current;
      
      if (progress < 1) {
        requestAnimationFrame(update);
      }
    }
    requestAnimationFrame(update);
  }

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      if (href === '#') return;
      
      const target = document.querySelector(href);
      if (target) {
        e.preventDefault();
        const navHeight = document.querySelector('.navbar').offsetHeight;
        const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - navHeight;
        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
      }
    });
  });
});
