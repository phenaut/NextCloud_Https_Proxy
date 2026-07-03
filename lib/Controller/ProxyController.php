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

class ProxyController extends Controller {

    public function __construct(string $appName, IRequest $request) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function index(): TemplateResponse {
        Util::addScript('https_proxy', 'script');
        return new TemplateResponse('https_proxy', 'main');
    }

    /**
     * NoCSRFRequired est nécessaire pour la navigation fluide dans l'iframe
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function fetch(string $url): DataDisplayResponse {
        if (empty($url) || !str_starts_with($url, 'http')) {
            return new DataDisplayResponse('URL invalide', 400);
        }

        try {
            /** @var IClientService $clientService */
            $clientService = \OC::$server->get(IClientService::class);
            $client = $clientService->newClient();

            $proxyResponse = $client->get($url, [
                'timeout' => 15,
                'connect_timeout' => 5,
                'allow_redirects' => true
            ]);

            $contentType = $proxyResponse->getHeader('Content-Type');
            $body = $proxyResponse->getBody();

            // Réécriture des URLs pour le contenu HTML
            if (str_contains($contentType, 'text/html')) {
                $body = $this->rewriteUrls($body, $url);
            }

            $response = new DataDisplayResponse(
                $body,
                200,
                ['Content-Type' => $contentType]
            );

            $response->addHeader('X-Frame-Options', 'ALLOWALL');
            $response->addHeader('Content-Security-Policy', "frame-ancestors 'self'");

            return $response;
        } catch (\Exception $e) {
            // Utilisation de DataDisplayResponse ici aussi pour éviter l'erreur de classe manquante
            return new DataDisplayResponse('Erreur proxy : ' . $e->getMessage(), 500);
        }
    }

    private function rewriteUrls(string $html, string $baseUrl): string {
        if (empty($html)) return $html;

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        // Support UTF-8
        $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $parse = parse_url($baseUrl);
        $rootUrl = $parse['scheme'] . '://' . $parse['host'];

        // Liens <a>
        $links = $dom->getElementsByTagName('a');
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if ($href && !str_starts_with($href, '#') && !str_starts_with($href, 'javascript:')) {
                $newHref = $this->makeAbsolute($href, $baseUrl, $rootUrl);
                $proxyUrl = \OC::$server->getURLGenerator()->linkToRoute('https_proxy.proxy.fetch', ['url' => $newHref]);
                $link->setAttribute('href', $proxyUrl);
            }
        }

        // Images <img>
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if ($src) {
                $newSrc = $this->makeAbsolute($src, $baseUrl, $rootUrl);
                $proxySrc = \OC::$server->getURLGenerator()->linkToRoute('https_proxy.proxy.fetch', ['url' => $newSrc]);
                $img->setAttribute('src', $proxySrc);
            }
        }

        return $dom->saveHTML();
    }

    private function makeAbsolute(string $rel, string $baseUrl, string $rootUrl): string {
        if (parse_url($rel, PHP_URL_SCHEME) != '') return $rel;
        if (isset($rel[0]) && $rel[0] === '/' && isset($rel[1]) && $rel[1] === '/') return "https:" . $rel;
        if (isset($rel[0]) && $rel[0] === '/') return $rootUrl . $rel;

        return rtrim($baseUrl, '/') . '/' . $rel;
    }
}
