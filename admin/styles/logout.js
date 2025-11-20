document.addEventListener('DOMContentLoaded', function () {
  const logoutBtn = document.getElementById('logoutBtn');

  // Logout functionality
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function (e) {
      e.preventDefault();

      Swal.fire({
        html: `
          <div style="display: flex; flex-direction: column; align-items: center; gap: 15px;">
            <img src="../images/mainlogo.png" alt="Blessence Logo" style="width: 80px; height: auto; border-radius: 12px;">
            <h2 style="color: #e497aa; margin: 0;">Logout Confirmation</h2>
            <p style="color: #6c757d; text-align: center; max-width: 250px;">
              Are you sure you want to logout from the admin dashboard?
            </p>
          </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Yes, Logout',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e497aa',
        cancelButtonColor: '#6c757d',
        reverseButtons: true,
        background: '#ffffff',
        backdrop: true,
        customClass: {
          popup: 'soft-popup'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = '../../index.php';
        }
      });
    });
  }

  // Initialize cashier mode functionality
  initializeCashierMode();
});

// Cashier Mode Functionality
function initializeCashierMode() {
  // Track typed keys for Shift + "cashier"
  let typedKeys = '';
  const triggerWord = 'CASHIER';
  const cashierModeEntry = document.getElementById('cashierModeEntry');

  // Keyboard shortcut (Shift + CASHIER)
  document.addEventListener('keydown', (e) => {
    if (e.shiftKey && e.key.length === 1) {
      typedKeys += e.key.toUpperCase();

      if (typedKeys.length > triggerWord.length) {
        typedKeys = typedKeys.slice(-triggerWord.length);
      }

      if (typedKeys === triggerWord) {
        showCashierModeEntry();
        setTimeout(() => {
          window.location.href = 'cashier-mode.html';
        }, 2000);
        typedKeys = '';
      }
    } else if (!e.shiftKey && e.key === 'Shift') {
      // Do nothing if just Shift is pressed
    } else {
      typedKeys = '';
    }
  });

  // Initialize activation methods
  new CashierModeMultiTap();
  new MobileLongPressCashierMode();
}

// Function to show cashier mode entry screen
function showCashierModeEntry() {
  const cashierModeEntry = document.getElementById('cashierModeEntry');
  if (cashierModeEntry) {
    cashierModeEntry.classList.add('active');
  }
}

// --- Cashier Mode Multiple Tap Activation (Desktop) ---
class CashierModeMultiTap {
  constructor() {
    this.cashierLink = document.getElementById('cashierModeLink');
    this.tapCounter = document.getElementById('cashierTapCounter');
    this.cashierModeEntry = document.getElementById('cashierModeEntry');
    this.tapCount = 0;
    this.requiredTaps = 5;
    this.tapTimeout = null;
    this.tapTimeoutDuration = 2000;
    
    this.init();
  }
  
  init() {
    this.bindEvents();
  }
  
  bindEvents() {
    if (this.cashierLink && window.innerWidth >= 992) {
      this.cashierLink.addEventListener('click', (e) => {
        e.preventDefault();
        this.handleTap();
      });
    }
  }
  
  handleTap() {
    if (this.tapTimeout) {
      clearTimeout(this.tapTimeout);
    }
    
    this.tapCount++;
    this.updateTapCounter();
    
    if (this.tapCount >= this.requiredTaps) {
      this.activateCashierMode();
      return;
    }
    
    this.tapTimeout = setTimeout(() => {
      this.resetTapCounter();
    }, this.tapTimeoutDuration);
  }
  
  updateTapCounter() {
    if (!this.tapCounter) return;
    
    const remainingTaps = this.requiredTaps - this.tapCount;
    
    if (remainingTaps > 0) {
      this.tapCounter.textContent = `${remainingTaps} click${remainingTaps > 1 ? 's' : ''} away to activate cashier mode`;
      this.tapCounter.classList.add('show');
      this.tapCounter.classList.remove('hide');
    }
  }
  
  resetTapCounter() {
    this.tapCount = 0;
    if (this.tapCounter) {
      this.tapCounter.classList.remove('show');
      this.tapCounter.classList.add('hide');
      
      setTimeout(() => {
        this.tapCounter.classList.remove('hide');
      }, 300);
    }
  }
  
  activateCashierMode() {
    this.tapCount = 0;
    if (this.tapCounter) {
      this.tapCounter.classList.remove('show');
    }
    
    showCashierModeEntry();
    
    setTimeout(() => {
      window.location.href = 'cashier-mode.html';
    }, 2000);
  }
}

// --- Mobile Long Press Cashier Mode ---
class MobileLongPressCashierMode {
  constructor() {
    this.cashierLink = document.getElementById('cashierModeLink');
    this.revealElement = document.getElementById('fullscreenReveal');
    this.overlayElement = document.getElementById('revealOverlay');
    this.cashierModeEntry = document.getElementById('cashierModeEntry');
    this.pressTimer = null;
    this.pressDuration = 3000;
    this.startTime = 0;
    this.animationFrame = null;
    this.isActivating = false;
    this.hasTriggered = false;
    this.currentScale = 0;

    this.init();
  }

  init() {
    if (this.cashierLink && window.innerWidth < 992) {
      this.bindEvents();
    }
  }

  bindEvents() {
    this.cashierLink.addEventListener('touchstart', (e) => this.handlePressStart(e), { passive: false });
    this.cashierLink.addEventListener('touchend', (e) => this.handlePressEnd(e), { passive: false });
    this.cashierLink.addEventListener('touchmove', (e) => this.handlePressMove(e), { passive: false });
    this.cashierLink.addEventListener('touchcancel', (e) => this.handlePressEnd(e), { passive: false });
    this.cashierLink.addEventListener('contextmenu', (e) => e.preventDefault());
  }

  handlePressStart(e) {
    e.preventDefault();
    this.startTime = Date.now();
    this.isActivating = true;
    this.hasTriggered = false;
    this.currentScale = 0;

    this.cashierLink.classList.add('pressing');
    if (navigator.vibrate) {
      navigator.vibrate(30);
    }

    this.showReveal();
    this.animateGradualExpansion();
  }

  handlePressEnd(e) {
    if (!this.isActivating || this.hasTriggered) return;

    const pressDuration = Date.now() - this.startTime;
    this.cleanup();

    if (pressDuration > 200) {
      this.cancelReveal();
    } else {
      this.hideReveal();
    }
  }

  handlePressMove(e) {
    if (!this.isActivating || this.hasTriggered) return;

    const touch = e.touches ? e.touches[0] : e;
    const rect = this.cashierLink.getBoundingClientRect();
    const x = touch.clientX;
    const y = touch.clientY;

    const tolerance = 50;
    if (x < rect.left - tolerance || x > rect.right + tolerance ||
      y < rect.top - tolerance || y > rect.bottom + tolerance) {
      this.handlePressEnd(e);
    }
  }

  showReveal() {
    if (!this.revealElement || !this.overlayElement) return;

    const rect = this.cashierLink.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    this.revealElement.style.left = centerX + 'px';
    this.revealElement.style.top = centerY + 'px';
    this.revealElement.style.transform = 'translate(-50%, -50%) scale(0)';
    this.revealElement.style.transition = 'none';

    this.revealElement.classList.add('active');
    this.overlayElement.classList.add('active');
  }

  animateGradualExpansion() {
    const rect = this.cashierLink.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;

    const distTL = Math.hypot(centerX - 0, centerY - 0);
    const distTR = Math.hypot(centerX - window.innerWidth, centerY - 0);
    const distBL = Math.hypot(centerX - 0, centerY - window.innerHeight);
    const distBR = Math.hypot(centerX - window.innerWidth, centerY - window.innerHeight);

    const maxRadius = Math.max(distTL, distTR, distBL, distBR);
    const maxScale = (maxRadius * 2) / 100;

    const updateExpansion = () => {
      if (!this.isActivating) return;

      const elapsed = Date.now() - this.startTime;
      const progress = Math.min(elapsed / this.pressDuration, 1);

      const eased = progress < 0.5
        ? 4 * progress * progress * progress
        : 1 - Math.pow(-2 * progress + 2, 3) / 2;

      this.currentScale = eased * maxScale;

      if (this.revealElement) {
        this.revealElement.style.transform = `translate(-50%, -50%) scale(${this.currentScale})`;

        const shadowIntensity = Math.min(eased * 50, 50);
        this.revealElement.style.boxShadow = `0 0 ${shadowIntensity}px ${shadowIntensity / 2}px rgba(228, 151, 170, ${0.3 + eased * 0.2})`;
      }

      if (progress >= 1) {
        this.triggerComplete();
        return;
      }

      this.animationFrame = requestAnimationFrame(updateExpansion);
    };

    this.animationFrame = requestAnimationFrame(updateExpansion);
  }

  triggerComplete() {
    if (this.hasTriggered) return;
    this.hasTriggered = true;

    if (navigator.vibrate) {
      navigator.vibrate([50, 30, 50]);
    }

    this.hideReveal();
    showCashierModeEntry();

    setTimeout(() => {
      window.location.href = 'cashier-mode.html';
    }, 2000);
  }

  cancelReveal() {
    if (!this.revealElement || !this.overlayElement) return;

    this.revealElement.style.transition = 'transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.4s ease, box-shadow 0.4s ease';
    this.revealElement.style.transform = 'translate(-50%, -50%) scale(0)';
    this.revealElement.style.opacity = '0';
    this.revealElement.style.boxShadow = '0 0 0 0 rgba(228, 151, 170, 0)';

    this.overlayElement.style.transition = 'opacity 0.4s ease';
    this.overlayElement.style.opacity = '0';

    setTimeout(() => {
      this.hideReveal();
    }, 500);
  }

  hideReveal() {
    if (this.revealElement) {
      this.revealElement.classList.remove('active');
      this.revealElement.style.transition = '';
      this.revealElement.style.transform = '';
      this.revealElement.style.left = '';
      this.revealElement.style.top = '';
      this.revealElement.style.opacity = '';
      this.revealElement.style.boxShadow = '';
    }

    if (this.overlayElement) {
      this.overlayElement.classList.remove('active');
      this.overlayElement.style.transition = '';
      this.overlayElement.style.opacity = '';
    }
  }

  cleanup() {
    if (this.pressTimer) {
      clearTimeout(this.pressTimer);
      this.pressTimer = null;
    }

    if (this.animationFrame) {
      cancelAnimationFrame(this.animationFrame);
      this.animationFrame = null;
    }

    this.isActivating = false;
    this.cashierLink.classList.remove('pressing');
  }
}