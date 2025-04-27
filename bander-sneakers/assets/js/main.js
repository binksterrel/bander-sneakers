/**
 * Bander-Sneakers - Main JavaScript File
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize functionality when DOM is loaded
    initSliders();
    initProductImageGallery();
    initQuantityInputs();
    initSizeSelection();
    initAddToCart();
    initMobileMenu();
    initFilters();
});

/**
 * Initialize any sliders on the page
 */
function initSliders() {
    // Basic implementation - can be enhanced with a slider library if needed
    const heroSliders = document.querySelectorAll('.hero-slider');

    heroSliders.forEach(slider => {
        const slides = slider.querySelectorAll('.slide');
        const totalSlides = slides.length;
        let currentSlide = 0;

        if (totalSlides <= 1) return;

        // Create navigation dots
        const dotsContainer = document.createElement('div');
        dotsContainer.className = 'slider-dots';

        for (let i = 0; i < totalSlides; i++) {
            const dot = document.createElement('span');
            dot.className = 'dot';
            if (i === 0) dot.classList.add('active');
            dot.addEventListener('click', () => {
                currentSlide = i;
                updateSlider();
            });
            dotsContainer.appendChild(dot);
        }

        slider.appendChild(dotsContainer);

        // Create prev/next buttons
        const prevBtn = document.createElement('button');
        prevBtn.className = 'slider-btn prev-btn';
        prevBtn.innerHTML = '<i class="fas fa-chevron-left"></i>';
        prevBtn.addEventListener('click', () => {
            currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
            updateSlider();
        });

        const nextBtn = document.createElement('button');
        nextBtn.className = 'slider-btn next-btn';
        nextBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
        nextBtn.addEventListener('click', () => {
            currentSlide = (currentSlide + 1) % totalSlides;
            updateSlider();
        });

        slider.appendChild(prevBtn);
        slider.appendChild(nextBtn);

        // Auto-slide
        let interval = setInterval(() => {
            currentSlide = (currentSlide + 1) % totalSlides;
            updateSlider();
        }, 5000);

        // Pause auto-slide on hover
        slider.addEventListener('mouseenter', () => {
            clearInterval(interval);
        });

        slider.addEventListener('mouseleave', () => {
            interval = setInterval(() => {
                currentSlide = (currentSlide + 1) % totalSlides;
                updateSlider();
            }, 5000);
        });

        // Update slider function
        function updateSlider() {
            slides.forEach((slide, index) => {
                slide.style.transform = `translateX(${100 * (index - currentSlide)}%)`;
            });

            const dots = dotsContainer.querySelectorAll('.dot');
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
            });
        }

        // Initialize slider positions
        updateSlider();
    });
}

/**
 * Initialize product image gallery functionality
 */
function initProductImageGallery() {
    const mainImage = document.querySelector('.main-image img');
    const thumbnails = document.querySelectorAll('.thumbnail');

    if (!mainImage || thumbnails.length === 0) return;

    thumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            // Update main image
            const imageUrl = this.querySelector('img').getAttribute('src');
            mainImage.setAttribute('src', imageUrl);

            // Update active thumbnail
            thumbnails.forEach(th => th.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

/**
 * Initialize quantity input functionality
 */
function initQuantityInputs() {
    const quantityInputs = document.querySelectorAll('.quantity-input');

    quantityInputs.forEach(container => {
        const input = container.querySelector('input');
        const decreaseBtn = container.querySelector('.decrease');
        const increaseBtn = container.querySelector('.increase');

        if (!input || !decreaseBtn || !increaseBtn) return;

        decreaseBtn.addEventListener('click', () => {
            const currentValue = parseInt(input.value);
            if (currentValue > 1) {
                input.value = currentValue - 1;
            }
        });

        increaseBtn.addEventListener('click', () => {
            const currentValue = parseInt(input.value);
            const maxValue = parseInt(input.getAttribute('max') || 99);
            if (currentValue < maxValue) {
                input.value = currentValue + 1;
            }
        });

        // Validate input on change
        input.addEventListener('change', () => {
            const currentValue = parseInt(input.value);
            const minValue = parseInt(input.getAttribute('min') || 1);
            const maxValue = parseInt(input.getAttribute('max') || 99);

            if (isNaN(currentValue) || currentValue < minValue) {
                input.value = minValue;
            } else if (currentValue > maxValue) {
                input.value = maxValue;
            }
        });
    });
}

/**
 * Initialize size selection functionality
 */
function initSizeSelection() {
    const sizeOptions = document.querySelectorAll('.size-option');

    sizeOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove active class from all options
            sizeOptions.forEach(opt => opt.classList.remove('active'));

            // Add active class to clicked option
            this.classList.add('active');

            // Update hidden input if it exists
            const sizeInput = document.querySelector('input[name="size_id"]');
            if (sizeInput) {
                sizeInput.value = this.getAttribute('data-size-id');
            }
        });
    });
}

/**
 * Initialize add to cart functionality
 */
function initAddToCart() {
    const addToCartForms = document.querySelectorAll('.add-to-cart-form');

    addToCartForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Check if size is selected
            const sizeInput = this.querySelector('input[name="size_id"]');
            if (sizeInput && !sizeInput.value) {
                showMessage('Veuillez sélectionner une taille.', 'error');
                return;
            }

            // Submit form via AJAX
            const formData = new FormData(this);

            fetch(this.getAttribute('action'), {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message || 'Produit ajouté au panier !', 'success');

                    // Update cart count if applicable
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount && data.cart_count) {
                        cartCount.textContent = data.cart_count;
                        cartCount.style.display = data.cart_count > 0 ? 'flex' : 'none';
                    }
                } else {
                    showMessage(data.message || 'Erreur lors de l\'ajout au panier.', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Une erreur est survenue. Veuillez réessayer plus tard.', 'error');
            });
        });
    });
}

/**
 * Initialize mobile menu functionality
 */
function initMobileMenu() {
    const menuToggle = document.querySelector('.mobile-menu-toggle');
    const mainNav = document.querySelector('.main-nav');

    if (!menuToggle || !mainNav) return;

    menuToggle.addEventListener('click', function() {
        mainNav.classList.toggle('active');
        this.classList.toggle('active');
    });
}

/**
 * Initialize product filters functionality
 */
function initFilters() {
    const filterForm = document.querySelector('.filter-form');
    if (!filterForm) return;

    // Handle filter form submission
    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        applyFilters();
    });

    // Handle filter changes
    const filterInputs = filterForm.querySelectorAll('input, select');
    filterInputs.forEach(input => {
        if (input.type === 'checkbox' || input.type === 'radio' || input.tagName === 'SELECT') {
            input.addEventListener('change', function() {
                applyFilters();
            });
        }
    });

    // Apply filters function
    function applyFilters() {
        const formData = new FormData(filterForm);
        const searchParams = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            if (value) {
                searchParams.append(key, value);
            }
        }

        // Redirect to filtered URL
        window.location.href = `${filterForm.getAttribute('action')}?${searchParams.toString()}`;
    }
}

/**
 * Show message to user
 * @param {string} message - Message to display
 * @param {string} type - Message type (success, error, info)
 */
function showMessage(message, type = 'info') {
    // Create or get message container
    let messageContainer = document.querySelector('.message-container');

    if (!messageContainer) {
        messageContainer = document.createElement('div');
        messageContainer.className = 'message-container';
        document.body.appendChild(messageContainer);
    }

    // Create message element
    const messageElement = document.createElement('div');
    messageElement.className = `message message-${type}`;
    messageElement.textContent = message;

    // Add close button
    const closeBtn = document.createElement('button');
    closeBtn.className = 'message-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', () => messageElement.remove());
    messageElement.appendChild(closeBtn);

    // Add to container
    messageContainer.appendChild(messageElement);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        messageElement.classList.add('fade-out');
        setTimeout(() => messageElement.remove(), 300);
    }, 5000);
}

