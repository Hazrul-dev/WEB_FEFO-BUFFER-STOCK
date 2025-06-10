document.addEventListener('DOMContentLoaded', function() {
    // Navbar Scroll Effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Animate elements when they come into view
    const animatedElements = document.querySelectorAll('.fade-in, .slide-up, .scale-up');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, {threshold: 0.1});
    
    animatedElements.forEach(element => {
        observer.observe(element);
    });
    
    // Scroll To Top Button
    const scrollTopBtn = document.querySelector('.scroll-top');
    
    if (scrollTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                scrollTopBtn.classList.add('active');
            } else {
                scrollTopBtn.classList.remove('active');
            }
        });
        
        scrollTopBtn.addEventListener('click', function() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        });
    }
    
    // Toast Notification
    window.showToast = function(type, title, message) {
        const toastContainer = document.querySelector('.toast-container') || createToastContainer();
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        toast.innerHTML = `
            <div class="toast-header">
                <strong>${title}</strong>
                <button type="button" class="close ml-auto">&times;</button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        // Show toast
        setTimeout(() => {
            toast.classList.add('show');
        }, 100);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 500);
        }, 5000);
        
        // Close button
        toast.querySelector('.close').addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => {
                toast.remove();
            }, 500);
        });
    }
    
    function createToastContainer() {
        const container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
        return container;
    }
    
    // Dark mode toggle
    const darkModeToggle = document.querySelector('#darkModeToggle');
    
    if (darkModeToggle) {
        const isDarkMode = localStorage.getItem('darkMode') === 'true';
        
        if (isDarkMode) {
            document.body.classList.add('dark-mode');
            darkModeToggle.checked = true;
        }
        
        darkModeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'true');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'false');
            }
        });
    }
    
    // Auto calculate ROP when safety stock changes
    const safetyStockInputs = document.querySelectorAll('input[name="safety_stock"]');
    safetyStockInputs.forEach(input => {
        input.addEventListener('change', function() {
            const leadTime = this.closest('form').querySelector('input[name="lead_time"]').value;
            const ropInput = this.closest('form').querySelector('input[name="rop"]');
            if (leadTime && this.value) {
                ropInput.value = parseInt(leadTime) + parseInt(this.value);
            }
        });
    });
    
    // FEFO selection for outgoing items
    const bahanSelects = document.querySelectorAll('select[name="bahan_id"]');
    bahanSelects.forEach(select => {
        select.addEventListener('change', async function() {
            const form = this.closest('form');
            const fefoInfo = form.querySelector('.fefo-info');
            
            if (fefoInfo) {
                try {
                    const response = await fetch(`get_fefo.php?bahan_id=${this.value}`);
                    const data = await response.json();
                    
                    if (data.success) {
                        fefoInfo.innerHTML = `
                            <div class="alert alert-info">
                                <strong>FEFO:</strong> Gunakan batch yang expired pada 
                                ${new Date(data.expired_date).toLocaleDateString()} 
                                (${data.days_left} hari lagi)
                            </div>
                        `;
                    } else {
                        fefoInfo.innerHTML = `
                            <div class="alert alert-warning">
                                ${data.message}
                            </div>
                        `;
                    }
                } catch (error) {
                    console.error('Error:', error);
                    fefoInfo.innerHTML = `
                        <div class="alert alert-danger">
                            Gagal memeriksa data FEFO
                        </div>
                    `;
                }
            }
        });
    });
    
    // Real-time stock check
    const quantityInputs = document.querySelectorAll('input[name="kuantitas"]');
    quantityInputs.forEach(input => {
        input.addEventListener('input', function() {
            const form = this.closest('form');
            const bahanId = form.querySelector('select[name="bahan_id"]').value;
            const stokInfo = form.querySelector('.stok-info');
            
            if (stokInfo && bahanId) {
                fetch(`get_stok.php?id=${bahanId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.stok < this.value) {
                            stokInfo.innerHTML = `
                                <div class="alert alert-danger">
                                    Stok tidak mencukupi! Stok tersedia: ${data.stok}
                                </div>
                            `;
                        } else {
                            stokInfo.innerHTML = `
                                <div class="alert alert-success">
                                    Stok tersedia: ${data.stok}
                                </div>
                            `;
                        }
                    });
            }
        });
    });
});