document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('proxy-btn');
    const input = document.getElementById('proxy-url');
    const frame = document.getElementById('proxy-frame');

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
});
