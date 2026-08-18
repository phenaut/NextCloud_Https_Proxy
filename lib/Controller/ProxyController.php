<?php

namespace OCA\Https_proxy\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\Util;
use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;

class ProxyController extends Controller {

        private IURLGenerator $urlGenerator;
    private IClientService $clientService;

    public function __construct(string $appName, IRequest $request) {
        parent::__construct($appName, $request);
        $this->urlGenerator  = \OC::$server->get(IURLGenerator::class);
        $this->clientService = \OC::$server->get(IClientService::class);
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
    public function index(): TemplateResponse {
        Util::addScript('https_proxy', 'script');
        return new TemplateResponse('https_proxy', 'main');
    }

    /**
     * @NoAdminRequired
     * @NoCSRFRequired
     */
        public function fetch(string $url): DataDisplayResponse {
        if (empty($url) || !str_starts_with($url, 'http')) {
            return new DataDisplayResponse('URL invalide', 400);
        }

        try {
            $client = $this->clientService->newClient();

            $proxyResponse = $client->get($url, [
                'timeout'         => 30,
                'connect_timeout' => 10,
                'allow_redirects' => true,
                'headers'         => [
                    'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'identity',
                ],
            ]);

            $contentType = $proxyResponse->getHeader('Content-Type') ?: 'application/octet-stream';
            $body        = $proxyResponse->getBody();

            if (str_contains($contentType, 'text/html')) {
                $body = $this->rewriteHtml($body, $url);
            } elseif (str_contains($contentType, 'text/css')) {
                $body = $this->rewriteCss($body, $url);
            }

            $response = new DataDisplayResponse($body, 200, ['Content-Type' => $contentType]);
            $response->addHeader('X-Frame-Options', 'ALLOWALL');
            $response->addHeader('Content-Security-Policy', "frame-ancestors *");
            $response->addHeader('Access-Control-Allow-Origin', '*');

            return $response;

        } catch (\Exception $e) {
            return new DataDisplayResponse('Erreur proxy : ' . $e->getMessage(), 500);
        }
    }

        // =========================================================================
    // Réécriture HTML complète (MITM)
    // =========================================================================

    private function rewriteHtml(string $html, string $baseUrl): string {
        if (empty($html)) return $html;

        $parsed  = parse_url($baseUrl);
        $rootUrl = $parsed['scheme'] . '://' . $parsed['host'];

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // ── Supprimer les <base> existantes ──────────────────────────────────
        foreach ($xpath->query('//base') as $base) {
            $base->parentNode->removeChild($base);
        }

        // ── href : <a>, <link> ───────────────────────────────────────────────
        foreach ($xpath->query('//*[@href]') as $node) {
            $href = $node->getAttribute('href');
            if (empty($href)
                || str_starts_with($href, '#')
                || str_starts_with($href, 'javascript:')
                || str_starts_with($href, 'mailto:')
            ) continue;
            $node->setAttribute('href', $this->proxyUrl($this->makeAbsolute($href, $baseUrl, $rootUrl)));
        }

        // ── src : img, script, iframe, video, audio, source, embed ──────────
        foreach ($xpath->query('//*[@src]') as $node) {
            $src = $node->getAttribute('src');
            if (empty($src) || str_starts_with($src, 'data:') || str_starts_with($src, 'javascript:')) continue;
            $node->setAttribute('src', $this->proxyUrl($this->makeAbsolute($src, $baseUrl, $rootUrl)));
        }

        // ── srcset ───────────────────────────────────────────────────────────
        foreach ($xpath->query('//*[@srcset]') as $node) {
            $node->setAttribute('srcset', $this->rewriteSrcset($node->getAttribute('srcset'), $baseUrl, $rootUrl));
        }

        // ── action (formulaires) ─────────────────────────────────────────────
        foreach ($xpath->query('//form[@action]') as $form) {
            $action = $form->getAttribute('action');
            if (!empty($action) && !str_starts_with($action, 'javascript:')) {
                $form->setAttribute('action', $this->proxyUrl($this->makeAbsolute($action, $baseUrl, $rootUrl)));
            }
        }

        // ── style inline ─────────────────────────────────────────────────────
        foreach ($xpath->query('//*[@style]') as $node) {
            $node->setAttribute('style', $this->rewriteCssUrls($node->getAttribute('style'), $baseUrl, $rootUrl));
        }

        // ── blocs <style> ────────────────────────────────────────────────────
        foreach ($xpath->query('//style') as $styleNode) {
            $rewritten = $this->rewriteCssUrls($styleNode->textContent, $baseUrl, $rootUrl);
            while ($styleNode->firstChild) $styleNode->removeChild($styleNode->firstChild);
            $styleNode->appendChild($dom->createTextNode($rewritten));
        }

        // ── data-src / data-href (lazy loading) ──────────────────────────────
        foreach ($xpath->query('//*[@data-src]') as $node) {
            $src = $node->getAttribute('data-src');
            if (!empty($src) && !str_starts_with($src, 'data:'))
                $node->setAttribute('data-src', $this->proxyUrl($this->makeAbsolute($src, $baseUrl, $rootUrl)));
        }
        foreach ($xpath->query('//*[@data-href]') as $node) {
            $href = $node->getAttribute('data-href');
            if (!empty($href) && !str_starts_with($href, '#'))
                $node->setAttribute('data-href', $this->proxyUrl($this->makeAbsolute($href, $baseUrl, $rootUrl)));
        }

        // ── Injection du script intercepteur JS dans <head> ──────────────────
        $interceptScript = $dom->createElement('script');
        $interceptScript->setAttribute('type', 'text/javascript');
        $interceptScript->appendChild($dom->createTextNode($this->buildInterceptorJs($rootUrl, $baseUrl)));

        $head = $dom->getElementsByTagName('head')->item(0);
        if ($head) {
            $head->insertBefore($interceptScript, $head->firstChild);
        } else {
            $body = $dom->getElementsByTagName('body')->item(0);
            if ($body) $body->insertBefore($interceptScript, $body->firstChild);
        }

        $result = $dom->saveHTML();
        return str_replace('<?xml encoding="utf-8" ?>', '', $result);
    }

    // =========================================================================
    // Réécriture CSS
    // =========================================================================

    private function rewriteCss(string $css, string $baseUrl): string {
        $parsed  = parse_url($baseUrl);
        $rootUrl = $parsed['scheme'] . '://' . $parsed['host'];
        return $this->rewriteCssUrls($css, $baseUrl, $rootUrl);
    }

    private function rewriteCssUrls(string $css, string $baseUrl, string $rootUrl): string {
        return preg_replace_callback(
            '/url\(\s*([\'"]?)(.+?)\1\s*\)/i',
            function (array $m) use ($baseUrl, $rootUrl): string {
                $quote = $m[1];
                $src   = $m[2];
                if (str_starts_with($src, 'data:') || str_starts_with($src, '#')) return $m[0];
                return 'url(' . $quote . $this->proxyUrl($this->makeAbsolute($src, $baseUrl, $rootUrl)) . $quote . ')';
            },
            $css
        ) ?? $css;
    }

    // =========================================================================
    // Réécriture srcset
    // =========================================================================

    private function rewriteSrcset(string $srcset, string $baseUrl, string $rootUrl): string {
        $out = [];
        foreach (explode(',', $srcset) as $part) {
            $part   = trim($part);
            $pieces = preg_split('/\s+/', $part, 2);
            $src    = $pieces[0];
            $desc   = isset($pieces[1]) ? ' ' . $pieces[1] : '';
            if (!empty($src) && !str_starts_with($src, 'data:'))
                $src = $this->proxyUrl($this->makeAbsolute($src, $baseUrl, $rootUrl));
            $out[] = $src . $desc;
        }
        return implode(', ', $out);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function proxyUrl(string $absUrl): string {
        return $this->urlGenerator->linkToRoute('https_proxy.proxy.fetch', ['url' => $absUrl]);
    }

    private function makeAbsolute(string $rel, string $baseUrl, string $rootUrl): string {
        $rel    = trim($rel);
        $scheme = parse_url($rel, PHP_URL_SCHEME);
        if ($scheme !== null && $scheme !== '') return $rel;
        if (str_starts_with($rel, '//')) return 'https:' . $rel;
        if (str_starts_with($rel, '/')) return $rootUrl . $rel;
        $base = preg_replace('/\/[^\/]*$/', '/', $baseUrl);
        return $base . $rel;
    }

    /**
     * Script JS injecté dans chaque page HTML proxifiée.
     * Intercepte fetch(), XHR, window.location, history et les mutations DOM
     * pour que TOUT passe par le proxy Nextcloud.
     */
    private function buildInterceptorJs(string $rootUrl, string $currentUrl): string {
        $sample    = $this->urlGenerator->linkToRoute('https_proxy.proxy.fetch', ['url' => 'PLACEHOLDER']);
        $proxyBase = substr($sample, 0, strpos($sample, 'PLACEHOLDER'));

        $jsProxyBase  = $this->jsString($proxyBase);
        $jsCurrentUrl = $this->jsString($currentUrl);
        $jsRootUrl    = $this->jsString($rootUrl);

        return <<<JS
(function () {
    'use strict';
    var PROXY_BASE  = {$jsProxyBase};
    var CURRENT_URL = {$jsCurrentUrl};
    var ROOT_URL    = {$jsRootUrl};

    function makeAbsolute(url) {
        if (!url) return url;
        if (/^(data:|blob:|javascript:|#|mailto:)/i.test(url)) return url;
        if (/^https?:\/\//i.test(url)) return url;
        if (/^\/\//.test(url)) return 'https:' + url;
        if (url.charAt(0) === '/') return ROOT_URL + url;
        return CURRENT_URL.replace(/\/[^\/]*$/, '/') + url;
    }

    function toProxy(url) {
        if (!url) return url;
        if (/^(data:|blob:|javascript:|#|mailto:)/i.test(url)) return url;
        var abs = makeAbsolute(url);
        if (abs.indexOf(PROXY_BASE) !== -1) return abs;
        return PROXY_BASE + encodeURIComponent(abs);
    }

    /* fetch() */
    var _origFetch = window.fetch;
    window.fetch = function (input, init) {
        if (typeof input === 'string') {
            input = toProxy(input);
        } else if (input && input.url) {
            input = new Request(toProxy(input.url), input);
        }
        return _origFetch.call(this, input, init);
    };

    /* XMLHttpRequest */
    var _origOpen = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method, url, async, user, pass) {
        return _origOpen.call(this, method, toProxy(String(url)),
            async !== undefined ? async : true, user, pass);
    };

    /* window.location */
    try {
        var _loc = window.location;
        Object.defineProperty(window, 'location', {
            get: function () { return _loc; },
            set: function (v) { _loc.href = toProxy(String(v)); },
            configurable: true
        });
    } catch (e) {}

    /* history.pushState / replaceState */
    ['pushState', 'replaceState'].forEach(function (m) {
        var orig = history[m];
        history[m] = function (state, title, url) {
            return orig.call(this, state, title, url ? toProxy(String(url)) : url);
        };
    });

    /* MutationObserver — nœuds injectés dynamiquement */
    function rewriteNode(node) {
        if (!node || node.nodeType !== 1) return;
        ['src', 'href', 'action', 'data-src'].forEach(function (attr) {
            if (!node.hasAttribute(attr)) return;
            var val = node.getAttribute(attr);
            if (val && !/^(data:|blob:|#|javascript:|mailto:)/i.test(val) && val.indexOf(PROXY_BASE) === -1) {
                node.setAttribute(attr, toProxy(val));
            }
        });
        Array.prototype.forEach.call(node.childNodes || [], rewriteNode);
    }

    if (typeof MutationObserver !== 'undefined') {
        new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                if (m.type === 'childList') {
                    m.addedNodes.forEach(rewriteNode);
                } else if (m.type === 'attributes') {
                    rewriteNode(m.target);
                }
            });
        }).observe(document.documentElement, {
            childList: true, subtree: true,
            attributes: true,
            attributeFilter: ['src', 'href', 'action', 'data-src']
        });
    }

    console.log('[MITM-Proxy] actif -> ' + CURRENT_URL);
})();
JS;
    }

    private function jsString(string $val): string {
        return '"' . addcslashes($val, "\\\"\n\r") . '"';
    }
}
