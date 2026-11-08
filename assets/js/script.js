// Main JavaScript - Zaykaa Café & Restaurant

document.addEventListener('DOMContentLoaded', function() {
    // ==========================================
    // Mobile Menu Toggle & Navigation Controller
    // ==========================================
    const mobileToggle = document.getElementById('mobileToggle');
    const navMenu = document.getElementById('navMenu');
    const navBackdrop = document.getElementById('navBackdrop');
    const drawerCloseBtn = document.getElementById('drawerCloseBtn');
    
    window.closeMobileMenu = function() {
        const toggle = document.getElementById('mobileToggle');
        const menu = document.getElementById('navMenu');
        const backdrop = document.getElementById('navBackdrop');
        if (toggle) {
            toggle.classList.remove('active');
            toggle.setAttribute('aria-expanded', 'false');
        }
        if (menu) {
            menu.classList.remove('active');
            menu.setAttribute('aria-hidden', 'true');
        }
        if (backdrop) {
            backdrop.classList.remove('active');
            backdrop.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('mobile-menu-active');
        document.body.style.overflow = '';
    };

    window.openMobileMenu = function() {
        const toggle = document.getElementById('mobileToggle');
        const menu = document.getElementById('navMenu');
        const backdrop = document.getElementById('navBackdrop');
        if (toggle) {
            toggle.classList.add('active');
            toggle.setAttribute('aria-expanded', 'true');
        }
        if (menu) {
            menu.classList.add('active');
            menu.setAttribute('aria-hidden', 'false');
        }
        if (backdrop) {
            backdrop.classList.add('active');
            backdrop.setAttribute('aria-hidden', 'false');
        }
        document.body.classList.add('mobile-menu-active');
        document.body.style.overflow = 'hidden';
    };

    window.toggleMobileMenu = function() {
        const menu = document.getElementById('navMenu');
        if (menu && menu.classList.contains('active')) {
            window.closeMobileMenu();
        } else {
            window.openMobileMenu();
        }
    };

    if (mobileToggle) {
        mobileToggle.setAttribute('aria-controls', 'navMenu');
        mobileToggle.setAttribute('aria-expanded', 'false');
        mobileToggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.toggleMobileMenu();
        });
    }

    if (drawerCloseBtn) {
        drawerCloseBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.closeMobileMenu();
        });
    }
    
    // Close menu when clicking backdrop overlay
    if (navBackdrop) {
        navBackdrop.addEventListener('click', function(e) {
            e.preventDefault();
            window.closeMobileMenu();
        });
    }
    
    // Navigation link handling inside the drawer
    if (navMenu) {
        const drawerLinks = navMenu.querySelectorAll('a');
        drawerLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (!href) return;

                // Handle in-page hash links (e.g., #categoriesSection)
                if (href.startsWith('#')) {
                    e.preventDefault();
                    window.closeMobileMenu();
                    if (href !== '#' && href !== '#!') {
                        const target = document.querySelector(href);
                        if (target) {
                            const headerOffset = 80;
                            const elementPosition = target.getBoundingClientRect().top;
                            const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                            window.scrollTo({
                                top: offsetPosition,
                                behavior: 'smooth'
                            });
                        }
                    }
                    return;
                }

                // If user clicks a link to the exact current page, close drawer smoothly
                const currentFile = window.location.pathname.split('/').pop() || 'index.php';
                const targetFile = href.split('?')[0].split('#')[0].split('/').pop();
                if (targetFile === currentFile) {
                    window.closeMobileMenu();
                }
                // For other pages (menu.php, about.php, contact.php, cart.php, login.php, etc.)
                // Allow default navigation to proceed naturally without interference!
            });
        });
    }
    
    // Close menu when clicking outside drawer
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('navMenu');
        const toggle = document.getElementById('mobileToggle');
        if (menu && menu.classList.contains('active')) {
            if (!menu.contains(event.target) && (!toggle || !toggle.contains(event.target))) {
                window.closeMobileMenu();
            }
        }
    });

    // Close on Escape key press
    document.addEventListener('keydown', function(event) {
        const menu = document.getElementById('navMenu');
        const toggle = document.getElementById('mobileToggle');
        if (event.key === 'Escape' && menu && menu.classList.contains('active')) {
            window.closeMobileMenu();
            if (toggle) toggle.focus();
        }
    });
    
    // Sticky Header
    const header = document.getElementById('header');
    let lastScroll = 0;
    
    if (header) {
        window.addEventListener('scroll', function() {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            
            lastScroll = currentScroll;
        });
    }
    
    // Smooth Scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            
            if (href !== '#' && href !== '#!') {
                e.preventDefault();
                const target = document.querySelector(href);
                
                if (target) {
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                    
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
    
    // Scroll Reveal Animation
    const revealElements = document.querySelectorAll('.reveal');
    
    const revealOnScroll = function() {
        const windowHeight = window.innerHeight;
        const elementVisible = 150;
        
        revealElements.forEach(element => {
            const elementTop = element.getBoundingClientRect().top;
            
            if (elementTop < windowHeight - elementVisible) {
                element.classList.add('active');
            }
        });
    };
    
    // Initial check
    revealOnScroll();
    
    // Check on scroll
    window.addEventListener('scroll', revealOnScroll);
    
    // Add reveal class to sections for animation (disabled - was hiding content)
    // const sections = document.querySelectorAll('section');
    // sections.forEach(section => {
    //     section.classList.add('reveal');
    // });
    
    // Category Card Hover Enhancement
    const categoryCards = document.querySelectorAll('.category-card');
    
    categoryCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-8px)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Button Ripple Effect
    const buttons = document.querySelectorAll('.order-btn, .cta-button');
    
    buttons.forEach(button => {
        button.addEventListener('click', function(e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s linear;
                pointer-events: none;
                left: ${x}px;
                top: ${y}px;
                width: 100px;
                height: 100px;
                margin-left: -50px;
                margin-top: -50px;
            `;
            
            this.style.position = 'relative';
            this.style.overflow = 'hidden';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });
    
    // Add ripple animation keyframes if not present
    if (!document.querySelector('#ripple-styles')) {
        const style = document.createElement('style');
        style.id = 'ripple-styles';
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
    
    // Lazy Loading Images
    const lazyImages = document.querySelectorAll('img[data-src]');
    
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    observer.unobserve(img);
                }
            });
        });
        
        lazyImages.forEach(img => {
            imageObserver.observe(img);
        });
    } else {
        // Fallback for browsers that don't support IntersectionObserver
        lazyImages.forEach(img => {
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
        });
    }
    
    // Prevent horizontal scrolling on touch devices
    document.addEventListener('touchmove', function(e) {
        if (e.touches.length > 1) {
            e.preventDefault();
        }
    }, { passive: false });
    
    // Handle resize events for responsive adjustments
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            // Close the mobile drawer only after leaving the mobile/tablet breakpoint.
            if (window.innerWidth > 991) {
                if (mobileToggle && navMenu) {
                    closeMobileMenu();
                }
            }
        }, 250);
    });
    
    // Add loading state for images
    const images = document.querySelectorAll('img');
    images.forEach(img => {
        img.addEventListener('load', function() {
            this.classList.add('loaded');
        });
        
        // If image is already loaded
        if (img.complete) {
            img.classList.add('loaded');
        }
    });

    // ==========================================
    // Real-Time Smart Food Search & Autocomplete
    // Supports: Instant suggestions on any letter (e.g. 'b'),
    // partial words, case-insensitive, category matching,
    // keyboard navigation, dish selection, and 'No dishes found'
    // ==========================================
    function initSearchAutocomplete(inputId, dropdownId, formId) {
        const input = document.getElementById(inputId);
        const dropdown = document.getElementById(dropdownId);
        const form = document.getElementById(formId);
        if (!input || !dropdown) return;

        let debounceTimer = null;
        let abortController = null;
        let highlightedIndex = -1;

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function highlightMatch(text, query) {
            if (!query || !text) return escapeHtml(text);
            const escaped = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const regex = new RegExp(`(${escaped})`, 'gi');
            const parts = text.split(regex);
            return parts.map(part => {
                if (part.toLowerCase() === query.toLowerCase()) {
                    return `<span class="search-highlight">${escapeHtml(part)}</span>`;
                }
                return escapeHtml(part);
            }).join('');
        }

        function closeDropdown() {
            dropdown.classList.remove('active');
            dropdown.innerHTML = '';
            highlightedIndex = -1;
            input.removeAttribute('aria-activedescendant');
        }

        function updateHighlightedItem(items) {
            items.forEach((item, idx) => {
                if (idx === highlightedIndex) {
                    item.classList.add('highlighted');
                    item.setAttribute('aria-selected', 'true');
                    input.setAttribute('aria-activedescendant', item.id || `searchItem-${idx}`);
                    item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                } else {
                    item.classList.remove('highlighted');
                    item.setAttribute('aria-selected', 'false');
                }
            });
        }

        function renderResults(results, query) {
            highlightedIndex = -1;
            if (!results || results.length === 0) {
                dropdown.innerHTML = `
                    <div class="search-dropdown-empty">
                        <div class="search-empty-icon">🍽️</div>
                        <p class="search-empty-title">No dishes found</p>
                        <p class="search-empty-desc">We couldn't find any dishes matching "<strong>${escapeHtml(query)}</strong>". Explore our menu.</p>
                    </div>
                    <a href="menu.php" class="search-dropdown-footer">Browse Master Menu →</a>
                `;
                dropdown.classList.add('active');
                return;
            }

            let html = `
                <div class="search-dropdown-header">
                    <span>Matching Dishes (${results.length})</span>
                    <span style="font-size: 10.5px; opacity: 0.85; text-transform: none;">Click to view</span>
                </div>
                <ul class="search-dropdown-list" role="listbox">
            `;

            results.forEach((dish, idx) => {
                const highlightedName = highlightMatch(dish.name, query);
                const vegClass = dish.is_veg ? 'veg-icon' : 'non-veg-icon';
                const bestsellerBadge = dish.is_bestseller ? '<span class="search-item-bestseller">★ BESTSELLER</span>' : '';
                const originalPriceHtml = dish.original_price ? `<span class="search-item-original">${dish.original_price}</span>` : '';
                const fallbackImg = 'assets/images/categories/biryani.png';
                const dishImg = dish.image ? dish.image : fallbackImg;

                html += `
                    <li class="search-dropdown-item" id="${inputId}-item-${idx}" role="option" tabindex="-1" data-url="${escapeHtml(dish.url)}" data-name="${escapeHtml(dish.name)}">
                        <img src="${escapeHtml(dishImg)}" alt="${escapeHtml(dish.name)}" class="search-item-thumb" onerror="this.src='${fallbackImg}'">
                        <div class="search-item-info">
                            <div class="search-item-title-row">
                                <span class="${vegClass}" style="flex-shrink:0;"></span>
                                <span class="search-item-name">${highlightedName}</span>
                            </div>
                            <div class="search-item-meta">
                                <span class="search-item-cat">${escapeHtml(dish.cat_name)}</span>
                                ${dish.serving_size ? `<span class="search-item-serving" style="font-size: 11px; color: #64748B;">• ${escapeHtml(dish.serving_size)}</span>` : ''}
                                ${bestsellerBadge}
                            </div>
                        </div>
                        <div class="search-item-price-col">
                            <span class="search-item-price">${dish.price_fmt}</span>
                            ${originalPriceHtml}
                        </div>
                    </li>
                `;
            });

            html += `
                </ul>
                <a href="search.php?query=${encodeURIComponent(query)}" class="search-dropdown-footer">
                    View all results for "${escapeHtml(query)}" →
                </a>
            `;

            dropdown.innerHTML = html;
            dropdown.classList.add('active');

            // Click listener for items
            const itemEls = dropdown.querySelectorAll('.search-dropdown-item');
            itemEls.forEach((el, index) => {
                el.addEventListener('mouseenter', () => {
                    highlightedIndex = index;
                    updateHighlightedItem(itemEls);
                });
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    const targetUrl = el.getAttribute('data-url');
                    const dishName = el.getAttribute('data-name');
                    input.value = dishName;
                    closeDropdown();
                    if (targetUrl) {
                        window.location.href = targetUrl;
                    }
                });
            });
        }

        function executeSearch(query) {
            const trimmed = query.trim();
            if (!trimmed) {
                closeDropdown();
                return;
            }

            if (abortController) {
                abortController.abort();
            }
            abortController = new AbortController();

            fetch(`api/search.php?q=${encodeURIComponent(trimmed)}`, { signal: abortController.signal })
                .then(res => {
                    if (!res.ok) throw new Error('Search HTTP error');
                    return res.json();
                })
                .then(data => {
                    if (data && data.success) {
                        renderResults(data.results, trimmed);
                    }
                })
                .catch(err => {
                    if (err.name !== 'AbortError') {
                        console.error('Zaykaa search error:', err);
                    }
                });
        }

        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const val = this.value;
            if (!val.trim()) {
                closeDropdown();
                return;
            }
            debounceTimer = setTimeout(() => {
                executeSearch(val);
            }, 140);
        });

        input.addEventListener('focus', function() {
            if (this.value.trim() && !dropdown.classList.contains('active')) {
                executeSearch(this.value);
            }
        });

        input.addEventListener('keydown', function(e) {
            const items = dropdown.querySelectorAll('.search-dropdown-item');
            if (!dropdown.classList.contains('active') || items.length === 0) {
                if (e.key === 'Enter' && form) {
                    return; // allow normal submission
                }
                return;
            }

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = (highlightedIndex + 1) % items.length;
                updateHighlightedItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = (highlightedIndex - 1 + items.length) % items.length;
                updateHighlightedItem(items);
            } else if (e.key === 'Enter') {
                if (highlightedIndex >= 0 && items[highlightedIndex]) {
                    e.preventDefault();
                    items[highlightedIndex].click();
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                closeDropdown();
            }
        });

        // Close when clicking outside form
        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target) && !input.contains(e.target) && (form && !form.contains(e.target))) {
                closeDropdown();
            }
        });
    }

    // Initialize both Desktop & Mobile food search bars
    initSearchAutocomplete('desktopSearchInput', 'desktopSearchDropdown', 'desktopSearchForm');
    initSearchAutocomplete('mobileSearchInput', 'mobileSearchDropdown', 'mobileSearchForm');
});

