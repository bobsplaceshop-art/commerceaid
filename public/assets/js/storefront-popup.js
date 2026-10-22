(function () {
    'use strict';
    var modal = document.getElementById('sf-popup-modal');
    if (!modal) return;

    var cookieKey  = modal.getAttribute('data-sf-popup-cookie') || 'sf_popup';
    var cookieDays = parseInt(modal.getAttribute('data-sf-popup-cookie-days'), 10) || 365;

    function hasCookie(name) {
        return document.cookie.split('; ').some(function (row) {
            return row.indexOf(name + '=') === 0;
        });
    }

    if (hasCookie(cookieKey)) return;

    setTimeout(function () {
        modal.style.display = '';
        modal.classList.add('is-visible');
        modal.removeAttribute('aria-hidden');
        document.body.style.overflow = 'hidden';
        var closeBtn = document.getElementById('sf-popup-close');
        if (closeBtn) closeBtn.focus();
    }, 500);

    function setCookie(name, days) {
        var d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=1; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
    }

    function dismiss() {
        modal.style.display = 'none';
        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        setCookie(cookieKey, cookieDays);
    }

    var closeBtn = document.getElementById('sf-popup-close');
    if (closeBtn) closeBtn.addEventListener('click', dismiss);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) dismiss();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.style.display !== 'none') dismiss();
    });
})();
