    <style>
        /* Footer Styles */
        .site-footer {
            background-color: #000000;
            color: var(--white);
            padding: 60px 40px 20px;
            margin-top: 50px;
            font-weight: 700;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .footer-logo {
            font-family: var(--font-heading);
            font-size: 2rem;
            color: var(--gold);
            margin-bottom: 15px;
            display: inline-block;
        }
        
        .footer-desc {
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 20px;
            color: #f5e4ea; /* lightened pink-ish white for text */
        }
        
        .social-icons a {
            color: var(--white);
            background-color: rgba(255, 255, 255, 0.1);
            width: 35px;
            height: 35px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            margin-right: 10px;
            transition: background 0.3s, color 0.3s;
        }
        
        .social-icons a:hover {
            background-color: var(--gold);
            color: var(--maroon);
        }
        
        .footer-heading {
            font-family: var(--font-heading);
            font-size: 1.2rem;
            color: var(--gold);
            margin-bottom: 20px;
            position: relative;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: #f5e4ea;
            font-size: 0.9rem;
            font-weight: 700;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: var(--gold);
        }
        
        .contact-info li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            font-size: 0.9rem;
            color: #f5e4ea;
        }
        
        .contact-info i {
            color: var(--gold);
            margin-right: 10px;
            margin-top: 4px;
        }
        
        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 0.85rem;
            color: #f5e4ea;
            font-weight: 700;
        }
        
        .payment-methods {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .payment-methods .payment-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.8rem;
            color: white;
            letter-spacing: 0.5px;
        }
        
        .badge-jazzcash {
            background-color: #e51e25; 
        }
        
        .badge-easypaisa {
            background-color: #3bb54a;
        }
        
        .badge-cod {
            background-color: #333;
            border: 1px solid #555;
        }

        @media (max-width: 768px) {
            .site-footer {
                padding: 40px 20px 20px;
            }
            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>

    <footer class="site-footer">
        <div class="footer-content">
            <!-- Brand Section -->
            <div class="footer-col">
                <a href="/" class="footer-logo">Rubkhar</a>
                <p class="footer-desc">
                    Your premier destination for elegant women's fashion in Pakistan. Discover our luxurious collection of clothing, jewelry, abayas, and more.
                </p>
                <div class="social-icons">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-pinterest-p"></i></a>
                    <a href="#"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-col">
                <h4 class="footer-heading">Quick Links</h4>
                <ul class="footer-links">
                    <li><a href="/about.php">About Us</a></li>
                    <li><a href="/contact.php">Contact Us</a></li>
                    <li><a href="/faq.php">FAQs</a></li>
                    <li><a href="/shipping-policy.php">Shipping Policy</a></li>
                    <li><a href="/return-policy.php">Return & Exchange</a></li>
                    <li><a href="/privacy-policy.php">Privacy Policy</a></li>
                </ul>
            </div>

            <!-- Categories -->
            <div class="footer-col">
                <h4 class="footer-heading">Categories</h4>
                <ul class="footer-links">
                    <li><a href="/category.php?slug=womens-clothing">Women Clothing</a></li>
                    <li><a href="/category.php?slug=jewelry">Jewelry</a></li>
                    <li><a href="/category.php?slug=abayas">Abayas</a></li>
                    <li><a href="/category.php?slug=gift-items">Gift Items</a></li>
                    <li><a href="/sale.php">Sale</a></li>
                    <li><a href="/new-arrivals.php">New Arrivals</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="footer-col">
                <h4 class="footer-heading">Contact Info</h4>
                <ul class="contact-info">
                    <li>
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Gulberg III, Lahore, Pakistan</span>
                    </li>
                    <li>
                        <i class="fas fa-phone-alt"></i>
                        <span>03450474651</span>
                    </li>
                    <li>
                        <i class="fas fa-envelope"></i>
                        <span>support@rubkhar.com</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="copyright">
                &copy; <?php echo date("Y"); ?> Rubkhar. All Rights Reserved.
            </div>
            <div class="payment-methods">
                <span style="font-size: 0.8rem;">We Accept:</span>
                <div class="payment-badge badge-jazzcash">JazzCash</div>
                <div class="payment-badge badge-easypaisa">EasyPaisa</div>
                <div class="payment-badge badge-cod">COD</div>
            </div>
        </div>
    </footer>

    <!-- Floating Buttons -->
    <a href="https://wa.me/923450474651" target="_blank" class="floating-whatsapp">
        <i class="fab fa-whatsapp"></i>
    </a>
    
    <div class="back-to-top" id="backToTop">
        <i class="fas fa-chevron-up"></i>
    </div>

    <!-- Add script for mobile menu toggle and back to top -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
            const backToTopBtn = document.getElementById('backToTop');

            // Back to top logic
            if (backToTopBtn) {
                window.addEventListener('scroll', () => {
                    if (window.scrollY > 300) {
                        backToTopBtn.classList.add('show');
                    } else {
                        backToTopBtn.classList.remove('show');
                    }
                });
                backToTopBtn.addEventListener('click', () => {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                });
            }
            const navList = document.querySelector('.nav-list');
            
            if (mobileMenuBtn && navList) {
                mobileMenuBtn.addEventListener('click', function() {
                    if (navList.style.display === 'flex') {
                        navList.style.display = 'none';
                    } else {
                        navList.style.display = 'flex';
                        navList.style.flexDirection = 'column';
                    }
                });
            }
        });
    </script>
</body>
</html>
