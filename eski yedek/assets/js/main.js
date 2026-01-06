// GTRANSLATE AYARLARI
window.gtranslateSettings = {
    "default_language": "tr",
    "languages": ["tr", "en", "de", "ar", "ru"],
    "wrapper_selector": ".gtranslate_wrapper",
    "flag_size": 24,
    "alt_flags": { "en": "usa", "pt": "brazil" }
};

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. ÇEREZ POLİTİKASI
    const cookieBanner = document.getElementById('cookieBanner');
    
    // Global scope'a atıyoruz ki HTML'den çağrılabilsin
    window.acceptCookies = function() {
        localStorage.setItem('cookiesAccepted', 'true');
        cookieBanner.classList.add('translate-y-full');
        setTimeout(() => { cookieBanner.classList.add('hidden'); }, 500);
    }

    if (!localStorage.getItem('cookiesAccepted')) {
        setTimeout(() => { 
            if(cookieBanner) cookieBanner.classList.remove('translate-y-full'); 
        }, 2000);
    } else {
        if(cookieBanner) cookieBanner.classList.add('hidden');
    }

    // 2. MOBİL MENÜ
    const menuBtn = document.querySelector('.ph-list')?.parentElement; 
    const mobileMenu = document.getElementById('mobile-menu');
    
    if(menuBtn && mobileMenu) {
        menuBtn.addEventListener('click', () => {
            if (mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; 
            } else {
                mobileMenu.classList.add('hidden');
                document.body.style.overflow = 'auto'; 
            }
        });

        mobileMenu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileMenu.classList.add('hidden');
                document.body.style.overflow = 'auto';
            });
        });
    }

    // 3. İLETİŞİM FORMU
    const contactForm = document.getElementById('contactForm');
    if(contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault(); 
            const form = this;
            const btn = document.getElementById('submitBtn');
            const statusDiv = document.getElementById('formStatus');
            const formData = new FormData(form);

            btn.disabled = true;
            btn.innerHTML = '<span class="animate-spin h-5 w-5 border-2 border-white border-t-transparent rounded-full"></span>';

            fetch('send.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                statusDiv.classList.remove('hidden');
                statusDiv.className = data.success ? "mt-4 p-4 rounded-xl text-center font-bold text-sm bg-green-100 text-green-700" : "mt-4 p-4 rounded-xl text-center font-bold text-sm bg-red-100 text-red-700";
                statusDiv.textContent = data.message;
                if(data.success) form.reset();
            })
            .catch(() => {
                statusDiv.classList.remove('hidden');
                statusDiv.className = "mt-4 p-4 rounded-xl text-center font-bold text-sm bg-red-100 text-red-700";
                statusDiv.textContent = "Bir hata oluştu.";
            })
            .finally(() => {
                btn.disabled = false;
                btn.textContent = "Beni Arayın";
            });
        });
    }

    // 4. SCROLL PROGRESS & STICKY NAVBAR
    const progressBar = document.getElementById('scroll-progress');
    const navbar = document.querySelector('nav');
    
    window.addEventListener('scroll', () => {
        const scrollTop = document.documentElement.scrollTop || document.body.scrollTop;
        const scrollHeight = document.documentElement.scrollHeight - document.documentElement.clientHeight;
        
        if(progressBar) progressBar.style.width = (scrollTop / scrollHeight) * 100 + "%";

        if (window.scrollY > 50) {
            navbar.classList.add('bg-white/90', 'shadow-md');
        } else {
            navbar.classList.remove('bg-white/90', 'shadow-md');
        }
    });

    // 5. FİYAT TOGGLE
    const pricingToggle = document.getElementById('pricing-toggle');
    const priceAmounts = document.querySelectorAll('.price-amount');
    const txtMonthly = document.getElementById('text-monthly');
    const txtYearly = document.getElementById('text-yearly');

    if(pricingToggle) {
        pricingToggle.addEventListener('change', function() {
            const isYearly = this.checked;
            if(isYearly) {
                txtMonthly.classList.replace('text-slate-600', 'text-slate-400');
                txtYearly.classList.replace('text-slate-400', 'text-slate-600');
            } else {
                txtYearly.classList.replace('text-slate-600', 'text-slate-400');
                txtMonthly.classList.replace('text-slate-400', 'text-slate-600');
            }
            priceAmounts.forEach(price => {
                price.innerText = isYearly ? '₺' + price.getAttribute('data-yearly') : '₺' + price.getAttribute('data-monthly');
            });
        });
    }

    // 6. DAKTİLO EFEKTİ
    const typeText = document.getElementById('typewriter');
    if(typeText) {
        const words = ["Dijitale Taşıyın", "Hızlandırın", "Modernleştirin"];
        let i = 0, j = 0, isDeleting = false;
        function type() {
            const current = words[i];
            if (isDeleting) {
                typeText.textContent = current.substring(0, j-1);
                j--;
                if (j == 0) { isDeleting = false; i = (i + 1) % words.length; }
            } else {
                typeText.textContent = current.substring(0, j+1);
                j++;
                if (j == current.length) { isDeleting = true; setTimeout(type, 2000); return; }
            }
            setTimeout(type, isDeleting ? 50 : 100);
        }
        type();
    }

    // 7. SAYAÇ ANİMASYONU
    const counters = document.querySelectorAll('.counter');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if(entry.isIntersecting) {
                const c = entry.target;
                const target = +c.getAttribute('data-target');
                let count = 0;
                const update = () => {
                    count += target / 50;
                    if(count < target) { c.innerText = Math.ceil(count); requestAnimationFrame(update); }
                    else c.innerText = target;
                };
                update();
                observer.unobserve(c);
            }
        });
    });
    counters.forEach(c => observer.observe(c));

});