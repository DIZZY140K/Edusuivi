// ===== EduSuivi - JavaScript Enrichi =====

// ---- Sidebar Toggle ----
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('.mobile-overlay');
    sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('active');
}

// ---- Demo Login Fill avec effet visuel ----
function fillDemo(email, password) {
    const emailEl = document.getElementById('email');
    const passEl = document.getElementById('password');
    if (!emailEl || !passEl) return;

    // Petite animation de remplissage TypeWriter
    emailEl.value = '';
    passEl.value = '';

    const typeStr = (el, str, cb) => {
        let i = 0;
        el.focus();
        const iv = setInterval(() => {
            el.value += str[i++];
            if (i >= str.length) { clearInterval(iv); if (cb) cb(); }
        }, 25);
    };

    typeStr(emailEl, email, () => {
        typeStr(passEl, password, () => {
            // Flash vert sur les champs
            [emailEl, passEl].forEach(el => {
                el.style.borderColor = '#16a34a';
                el.style.boxShadow = '0 0 0 3px rgba(22,163,74,0.15)';
                setTimeout(() => {
                    el.style.borderColor = '';
                    el.style.boxShadow = '';
                }, 1500);
            });
        });
    });
}

// ---- Modal ----
function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('active');
    modal.querySelector('.modal')?.classList.add('modal-enter');
    setTimeout(() => modal.querySelector('.modal')?.classList.remove('modal-enter'), 300);
    // Bloquer le scroll de la page
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    const inner = modal.querySelector('.modal');
    if (inner) {
        inner.classList.add('modal-exit');
        setTimeout(() => {
            modal.classList.remove('active');
            inner.classList.remove('modal-exit');
            // Rétablir le scroll uniquement si plus aucun modal actif
            if (!document.querySelector('.modal-overlay.active')) {
                document.body.style.overflow = '';
                document.documentElement.style.overflow = '';
            }
        }, 220);
    } else {
        modal.classList.remove('active');
        if (!document.querySelector('.modal-overlay.active')) {
            document.body.style.overflow = '';
            document.documentElement.style.overflow = '';
        }
    }
}

// Fermer modal avec Échap
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.active').forEach(m => {
            m.classList.remove('active');
        });
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
    }
});

// Fermer modal en cliquant sur l'overlay
document.addEventListener('DOMContentLoaded', () => {
    // Déplacer les overlays modaux à la racine de body pour corriger le centrage causé par les animations des conteneurs parents
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        document.body.appendChild(overlay);
    });

    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => {
            if (e.target === overlay) {
                const id = overlay.id;
                if (id) closeModal(id);
            }
        });
    });
});

// ---- Tabs ----
function switchTab(tabName) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(tc => {
        tc.classList.remove('active');
        tc.style.opacity = '0';
        tc.style.transform = 'translateY(8px)';
    });
    const activeTab = document.querySelector(`.tab[data-tab="${tabName}"]`);
    const activeContent = document.getElementById('tab-' + tabName);
    if (activeTab) activeTab.classList.add('active');
    if (activeContent) {
        activeContent.classList.add('active');
        requestAnimationFrame(() => {
            activeContent.style.transition = 'opacity 0.25s, transform 0.25s';
            activeContent.style.opacity = '1';
            activeContent.style.transform = 'translateY(0)';
        });
    }
}

// ---- Confirm delete ----
function confirmDelete(msg, url) {
    showConfirmDialog(msg, () => { window.location.href = url; });
}

// ---- Custom Confirm Dialog ----
function showConfirmDialog(message, onConfirm) {
    let dialog = document.getElementById('_confirmDialog');
    if (!dialog) {
        dialog = document.createElement('div');
        dialog.id = '_confirmDialog';
        dialog.innerHTML = `
          <div class="modal-overlay active" style="z-index:9999">
            <div class="modal" style="max-width:380px;text-align:center;">
              <div style="font-size:40px;margin-bottom:12px">⚠️</div>
              <h3 id="_confirmMsg" style="margin-bottom:20px;font-size:16px;font-weight:500;color:#1e293b;"></h3>
              <div class="modal-actions" style="justify-content:center;">
                <button class="btn btn-outline" id="_confirmCancel">Annuler</button>
                <button class="btn btn-danger" id="_confirmOk">Supprimer</button>
              </div>
            </div>
          </div>`;
        document.body.appendChild(dialog);
    }
    document.getElementById('_confirmMsg').textContent = message;
    dialog.style.display = 'flex';
    const cleanup = (confirmed) => {
        dialog.style.display = 'none';
        if (confirmed && onConfirm) onConfirm();
    };
    document.getElementById('_confirmOk').onclick = () => cleanup(true);
    document.getElementById('_confirmCancel').onclick = () => cleanup(false);
}

// ---- Auto-hide alerts ----
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s, transform 0.5s';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-8px)';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });
});

// ---- Compteurs animés ----
function animateCounter(el, target, duration = 1200) {
    const start = performance.now();
    const isFloat = target % 1 !== 0;
    const update = (now) => {
        const elapsed = now - start;
        const progress = Math.min(elapsed / duration, 1);
        // Easing easeOutExpo
        const eased = 1 - Math.pow(2, -10 * progress);
        const current = eased * target;
        el.textContent = isFloat ? current.toFixed(1) : Math.round(current);
        if (progress < 1) requestAnimationFrame(update);
    };
    requestAnimationFrame(update);
}

// ---- Intersection Observer pour animations d'entrée ----
const appearObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            appearObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });

document.addEventListener('DOMContentLoaded', () => {
    // Animer les stat-cards
    document.querySelectorAll('.stat-card').forEach((card, i) => {
        card.style.animationDelay = `${i * 80}ms`;
        card.classList.add('fade-in-up');
        appearObserver.observe(card);

        // Compteur animé
        const valueEl = card.querySelector('.value');
        if (valueEl) {
            const raw = valueEl.textContent.replace(/[^0-9.]/g, '');
            const num = parseFloat(raw);
            const suffix = valueEl.textContent.replace(raw, '').replace(num, '');
            if (!isNaN(num) && num > 0) {
                valueEl.dataset.target = num;
                valueEl.dataset.suffix = suffix || '';
                const observer = new IntersectionObserver(([entry]) => {
                    if (entry.isIntersecting) {
                        animateCounter(valueEl, num, 1200);
                        setTimeout(() => { valueEl.textContent += suffix || ''; }, 1250);
                        observer.unobserve(card);
                    }
                });
                observer.observe(card);
            }
        }
    });

    // Animer les cards génériques
    document.querySelectorAll('.card').forEach((card, i) => {
        card.style.animationDelay = `${i * 60}ms`;
        card.classList.add('fade-in-up');
        appearObserver.observe(card);
    });

    // Animer les lignes de tableau
    document.querySelectorAll('tbody tr').forEach((row, i) => {
        row.style.animationDelay = `${i * 40}ms`;
        row.classList.add('fade-in-left');
        appearObserver.observe(row);
    });

    // Sidebar overlay
    const overlay = document.querySelector('.mobile-overlay');
    if (overlay) overlay.addEventListener('click', toggleSidebar);

    // Ripple effect sur les boutons
    document.querySelectorAll('.btn, .btn-login, .demo-btn').forEach(btn => {
        btn.addEventListener('click', createRipple);
    });

    // Effet de survol actif sur sidebar
    initSidebarHover();

    // Particles sur la page login
    if (document.querySelector('.login-hero')) initLoginParticles();

    // Barre de progression de page
    initProgressBar();
});

// ---- Ripple Effect ----
function createRipple(e) {
    const btn = e.currentTarget;
    const existing = btn.querySelector('.ripple');
    if (existing) existing.remove();
    const circle = document.createElement('span');
    circle.classList.add('ripple');
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    circle.style.cssText = `
        width:${size}px;height:${size}px;
        left:${e.clientX - rect.left - size/2}px;
        top:${e.clientY - rect.top - size/2}px;
        position:absolute;border-radius:50%;
        background:rgba(255,255,255,0.35);
        pointer-events:none;
        animation:rippleAnim 0.6s ease-out forwards;
        transform:scale(0);`;
    btn.style.position = 'relative';
    btn.style.overflow = 'hidden';
    btn.appendChild(circle);
    setTimeout(() => circle.remove(), 700);
}

// ---- Sidebar hover indicator ----
function initSidebarHover() {
    const nav = document.querySelector('.sidebar-nav');
    if (!nav) return;
    const indicator = document.createElement('div');
    indicator.className = 'nav-indicator';
    nav.appendChild(indicator);

    const links = nav.querySelectorAll('a');
    const updateIndicator = (link) => {
        const rect = link.getBoundingClientRect();
        const navRect = nav.getBoundingClientRect();
        indicator.style.top = (rect.top - navRect.top) + 'px';
        indicator.style.height = rect.height + 'px';
        indicator.style.opacity = '1';
    };

    links.forEach(link => {
        link.addEventListener('mouseenter', () => updateIndicator(link));
    });
    nav.addEventListener('mouseleave', () => { indicator.style.opacity = '0'; });

    const active = nav.querySelector('a.active');
    if (active) updateIndicator(active);
}

// ---- Particules flottantes sur la page login ----
function initLoginParticles() {
    const hero = document.querySelector('.login-hero');
    if (!hero) return;
    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'position:absolute;inset:0;pointer-events:none;z-index:0;opacity:0.4;';
    hero.prepend(canvas);

    const ctx = canvas.getContext('2d');
    const resize = () => { canvas.width = hero.offsetWidth; canvas.height = hero.offsetHeight; };
    resize();
    window.addEventListener('resize', resize);

    const particles = Array.from({ length: 35 }, () => ({
        x: Math.random() * canvas.width,
        y: Math.random() * canvas.height,
        r: Math.random() * 3 + 1,
        dx: (Math.random() - 0.5) * 0.4,
        dy: (Math.random() - 0.5) * 0.4,
        alpha: Math.random() * 0.6 + 0.2,
    }));

    const draw = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        particles.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            ctx.fillStyle = `rgba(240,180,41,${p.alpha})`;
            ctx.fill();
            p.x += p.dx; p.y += p.dy;
            if (p.x < 0) p.x = canvas.width;
            if (p.x > canvas.width) p.x = 0;
            if (p.y < 0) p.y = canvas.height;
            if (p.y > canvas.height) p.y = 0;
        });
        requestAnimationFrame(draw);
    };
    draw();
}

// ---- Barre de progression en haut de page ----
function initProgressBar() {
    const bar = document.createElement('div');
    bar.id = 'progressBar';
    bar.style.cssText = `
        position:fixed;top:0;left:0;height:3px;width:0;z-index:9999;
        background:linear-gradient(90deg,#d4930d,#f0b429,#d4930d);
        background-size:200% 100%;
        transition:width 0.1s;
        animation:shimmer 1.5s infinite linear;`;
    document.body.prepend(bar);

    let loading = true;
    let width = 0;
    const speed = () => setInterval(() => {
        if (loading && width < 85) { width += Math.random() * 3; bar.style.width = width + '%'; }
    }, 80);
    const iv = speed();

    window.addEventListener('load', () => {
        loading = false;
        clearInterval(iv);
        width = 100;
        bar.style.width = '100%';
        bar.style.transition = 'width 0.3s';
        setTimeout(() => { bar.style.opacity = '0'; bar.style.transition = 'opacity 0.5s'; }, 400);
    });
}

// ---- Tooltip simple ----
function showTooltip(el, msg) {
    let tip = el.querySelector('._tip');
    if (!tip) {
        tip = document.createElement('span');
        tip.className = '_tip';
        tip.style.cssText = `
            position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);
            background:#1e293b;color:#fff;font-size:11px;padding:4px 8px;
            border-radius:6px;white-space:nowrap;pointer-events:none;z-index:100;
            opacity:0;transition:opacity 0.2s;`;
        el.style.position = 'relative';
        el.appendChild(tip);
    }
    tip.textContent = msg;
    setTimeout(() => { tip.style.opacity = '1'; }, 10);
    el.addEventListener('mouseleave', () => { tip.style.opacity = '0'; }, { once: true });
}

// ---- Animation de soumission de formulaire ----
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.querySelector('form[method="POST"]');
    if (loginForm) {
        const btn = loginForm.querySelector('.btn-login');
        loginForm.addEventListener('submit', () => {
            if (btn) {
                btn.textContent = 'Connexion en cours...';
                btn.style.opacity = '0.8';
                btn.disabled = true;
            }
        });
    }
});

// ---- Dark Mode Dynamique avec Injection de Style ----
document.addEventListener('DOMContentLoaded', () => {
    const headerRight = document.querySelector('.header div[style*="display:flex"]');
    if (headerRight) {
        const toggleBtn = document.createElement('button');
        toggleBtn.id = 'themeToggleBtn';
        toggleBtn.className = 'btn btn-outline';
        toggleBtn.style.cssText = 'border:none;background:transparent;font-size:18px;cursor:pointer;padding:8px;display:flex;align-items:center;justify-content:center;transition:transform 0.2s;';
        toggleBtn.innerHTML = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
        
        // Insérer avant le sélecteur de langue pour l'équilibre esthétique
        const langSelect = headerRight.querySelector('select');
        if (langSelect) {
            headerRight.insertBefore(toggleBtn, langSelect);
        } else {
            headerRight.appendChild(toggleBtn);
        }

        toggleBtn.addEventListener('click', () => {
            const isDark = document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            toggleBtn.innerHTML = isDark ? '☀️' : '🌙';
            toggleBtn.style.transform = 'scale(1.2) rotate(15deg)';
            setTimeout(() => { toggleBtn.style.transform = ''; }, 200);
            
            // Animation flash douce lors du changement de style
            const style = document.createElement('style');
            style.innerHTML = `* { transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease !important; }`;
            document.head.appendChild(style);
            setTimeout(() => { style.remove(); }, 350);
        });
    }
});

