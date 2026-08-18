/* global OC */
/* Test PHE */
'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var urlInput  = document.getElementById('proxy-url');
    var navBtn    = document.getElementById('proxy-btn');
    var frame     = document.getElementById('proxy-frame');
    var backBtn   = document.getElementById('proxy-back');
    var fwdBtn    = document.getElementById('proxy-fwd');
    var reloadBtn = document.getElementById('proxy-reload');

    // Historique de navigation interne
    var navHistory = [];
    var historyPos = -1;

    function proxyUrl(url) {
        return OC.generateUrl('/apps/https_proxy/fetch') + '?url=' + encodeURIComponent(url);
    }

    function navigate(url) {
        if (!url) return;
        if (!/^https?:\/\//i.test(url)) {
            url = 'https://' + url;
        }
        // Tronquer l'historique si on navigue depuis un point intermédiaire
        if (historyPos < navHistory.length - 1) {
            navHistory = navHistory.slice(0, historyPos + 1);
        }
        navHistory.push(url);
        historyPos++;

        frame.src      = proxyUrl(url);
        urlInput.value = url;
        updateNav();
    }

    function updateNav() {
        backBtn.disabled   = historyPos <= 0;
        fwdBtn.disabled    = historyPos >= navHistory.length - 1;
        reloadBtn.disabled = historyPos < 0;
    }

    // ── Bouton Aller ──────────────────────────────────────────────────────
    navBtn.addEventListener('click', function () {
        navigate(urlInput.value.trim());
    });

    urlInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') navigate(urlInput.value.trim());
    });

    // ── Retour ────────────────────────────────────────────────────────────
    backBtn.addEventListener('click', function () {
        if (historyPos > 0) {
            historyPos--;
            var url    = navHistory[historyPos];
            frame.src  = proxyUrl(url);
            urlInput.value = url;
            updateNav();
        }
    });

    // ── Avant ─────────────────────────────────────────────────────────────
    fwdBtn.addEventListener('click', function () {
        if (historyPos < navHistory.length - 1) {
            historyPos++;
            var url    = navHistory[historyPos];
            frame.src  = proxyUrl(url);
            urlInput.value = url;
            updateNav();
        }
    });

    // ── Recharger ─────────────────────────────────────────────────────────
    reloadBtn.addEventListener('click', function () {
        if (historyPos >= 0) {
            frame.src = proxyUrl(navHistory[historyPos]);
        }
    });

    // ── Chargement initial ────────────────────────────────────────────────
    navigate('https://www.wikipedia.org');
});document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('proxy-btn');
    const input = document.getElementById('proxy-url');
    const frame = document.getElementById('proxy-frame');
    const backBtn = document.getElementById('proxy-back-btn');

    if (!btn || !input || !frame) return;

    const loadUrl = function() {
        let url = input.value.trim();
        if (!url) return;

        if (!url.startsWith('http://') && !url.startsWith('https://')) {
            url = 'https://' + url;
            input.value = url;
        }

        // On génère l'URL de l'API
        // OC.generateUrl s'occupe de la racine de l'instance
        const endpoint = '/apps/https_proxy/fetch';

        // On ajoute le requesttoken dans l'URL pour passer la vérification CSRF
        // OC.requestToken est disponible globalement dans l'interface Nextcloud
        const proxyEndpoint = OC.generateUrl(endpoint + '?url=' + encodeURIComponent(url) + '&requesttoken=' + encodeURIComponent(OC.requestToken));

        frame.src = proxyEndpoint;
    };

    btn.addEventListener('click', (e) => {
        e.preventDefault();
        loadUrl();
    });

    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            loadUrl();
        }
    });

    // Bouton précédent - gère l'historique de l'iframe
    if (backBtn) {
        backBtn.addEventListener('click', (e) => {
            e.preventDefault();
            try {
                frame.contentWindow.history.back();
            } catch (err) {
                console.warn('Impossible de revenir à la page précédente:', err);
            }
        });
    }
});
