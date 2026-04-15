<?php
/**
 * Detecta el stack tecnológico del sitio a partir del HTML, headers y scripts
 * Esta información es puramente informativa — no afecta el score
 */

class TechDetector {
    private string $html;
    private array $headers;
    private HtmlParser $parser;

    public function __construct(string $html, array $headers) {
        $this->html = $html;
        $this->headers = $headers;
        $this->parser = new HtmlParser();
        $this->parser->loadHtml($html);
    }

    /**
     * Detecta todas las tecnologías y retorna un array organizado por categoría
     */
    public function detect(): array {
        return [
            'server' => $this->detectServer(),
            'cms' => $this->detectCms(),
            'pageBuilder' => $this->detectPageBuilder(),
            'ecommerce' => $this->detectEcommerce(),
            'cachePlugin' => $this->detectCachePlugin(),
            'seoPlugin' => $this->detectSeoPlugin(),
            'securityPlugin' => $this->detectSecurityPlugin(),
            'jsLibraries' => $this->detectJsLibraries(),
            'cssFramework' => $this->detectCssFramework(),
            'fonts' => $this->detectFonts(),
            'cdn' => $this->detectCdn(),
            'analytics' => $this->detectAnalytics(),
            'phpVersion' => $this->detectPhpVersion(),
            'httpProtocol' => $this->detectHttpProtocol(),
        ];
    }

    private function detectServer(): ?string {
        $server = $this->headers['server'] ?? '';
        if (stripos($server, 'nginx') !== false) return 'Nginx';
        if (stripos($server, 'apache') !== false) return 'Apache';
        if (stripos($server, 'litespeed') !== false) return 'LiteSpeed';
        if (stripos($server, 'cloudflare') !== false) return 'Cloudflare';
        if (stripos($server, 'iis') !== false) return 'Microsoft IIS';
        if (!empty($server)) return $server;
        return null;
    }

    private function detectCms(): ?string {
        $generator = $this->parser->getMeta('generator');
        if ($generator && stripos($generator, 'wordpress') !== false) {
            preg_match('/WordPress\s*([\d.]+)?/i', $generator, $m);
            return 'WordPress' . (isset($m[1]) ? " {$m[1]}" : '');
        }
        if (str_contains($this->html, '/wp-content/')) return 'WordPress';
        if ($generator && stripos($generator, 'joomla') !== false) return 'Joomla';
        if ($generator && stripos($generator, 'drupal') !== false) return 'Drupal';
        if (str_contains($this->html, 'Shopify.theme')) return 'Shopify';
        if (str_contains($this->html, 'squarespace')) return 'Squarespace';
        if (str_contains($this->html, 'wix.com')) return 'Wix';
        return null;
    }

    private function detectPageBuilder(): array {
        $builders = [];
        $checks = [
            'elementor' => 'Elementor',
            'et-boc' => 'Divi Builder',
            'divi' => 'Divi Builder',
            'fl-builder' => 'Beaver Builder',
            'wpb_' => 'WPBakery',
            'js_composer' => 'WPBakery',
            'bricks-' => 'Bricks Builder',
            'oxygen-' => 'Oxygen Builder',
            'ct-section' => 'Oxygen Builder',
            'breakdance' => 'Breakdance',
            'wp-block-' => 'Gutenberg (Bloques)',
        ];
        foreach ($checks as $pattern => $name) {
            if (str_contains($this->html, $pattern) && !in_array($name, $builders)) {
                $builders[] = $name;
            }
        }
        return $builders;
    }

    private function detectEcommerce(): array {
        $platforms = [];
        if (str_contains($this->html, 'woocommerce') || str_contains($this->html, 'wc-block')) {
            $platforms[] = 'WooCommerce';
        }
        if (str_contains($this->html, 'easy-digital-downloads') || str_contains($this->html, 'edd-')) {
            $platforms[] = 'Easy Digital Downloads';
        }
        if (str_contains($this->html, 'shopify')) $platforms[] = 'Shopify';
        if (str_contains($this->html, 'ecwid')) $platforms[] = 'Ecwid';
        if (str_contains($this->html, 'prestashop')) $platforms[] = 'PrestaShop';
        return $platforms;
    }

    private function detectCachePlugin(): array {
        $plugins = [];
        $checks = [
            'wp-rocket' => 'WP Rocket',
            'rocket-loader' => 'WP Rocket',
            'w3-total-cache' => 'W3 Total Cache',
            'wp-super-cache' => 'WP Super Cache',
            'litespeed-cache' => 'LiteSpeed Cache',
            'breeze-' => 'Breeze',
            'autoptimize' => 'Autoptimize',
            'wp-fastest-cache' => 'WP Fastest Cache',
            'swift-performance' => 'Swift Performance',
            'cache-enabler' => 'Cache Enabler',
            'nitropack' => 'NitroPack',
            'flying-press' => 'FlyingPress',
        ];
        foreach ($checks as $pattern => $name) {
            if (str_contains($this->html, $pattern) && !in_array($name, $plugins)) {
                $plugins[] = $name;
            }
        }
        // Detectar por headers
        if (isset($this->headers['x-litespeed-cache'])) $plugins[] = 'LiteSpeed Cache';
        if (isset($this->headers['x-cache-handler']) && str_contains($this->headers['x-cache-handler'], 'rocket')) $plugins[] = 'WP Rocket';
        return array_unique($plugins);
    }

    private function detectSeoPlugin(): array {
        $plugins = [];
        if (str_contains($this->html, 'yoast-seo') || str_contains($this->html, 'Yoast SEO')) $plugins[] = 'Yoast SEO';
        if (str_contains($this->html, 'rank-math') || str_contains($this->html, 'rankMath')) $plugins[] = 'Rank Math';
        if (str_contains($this->html, 'all-in-one-seo') || str_contains($this->html, 'aioseo')) $plugins[] = 'All in One SEO';
        if (str_contains($this->html, 'seopress')) $plugins[] = 'SEOPress';
        if (str_contains($this->html, 'the-seo-framework')) $plugins[] = 'The SEO Framework';
        if (str_contains($this->html, 'squirrly')) $plugins[] = 'Squirrly SEO';
        return $plugins;
    }

    private function detectSecurityPlugin(): array {
        $plugins = [];
        if (str_contains($this->html, 'wordfence')) $plugins[] = 'Wordfence';
        if (str_contains($this->html, 'sucuri')) $plugins[] = 'Sucuri';
        if (str_contains($this->html, 'ithemes-security') || str_contains($this->html, 'better-wp-security')) $plugins[] = 'Solid Security (iThemes)';
        if (str_contains($this->html, 'all-in-one-wp-security')) $plugins[] = 'All In One WP Security';
        if (str_contains($this->html, 'really-simple-ssl')) $plugins[] = 'Really Simple SSL';
        return $plugins;
    }

    private function detectJsLibraries(): array {
        $libs = [];
        if (str_contains($this->html, 'jquery') || str_contains($this->html, 'jQuery')) $libs[] = 'jQuery';
        if (str_contains($this->html, 'react') || str_contains($this->html, 'React')) $libs[] = 'React';
        if (str_contains($this->html, 'vue') || str_contains($this->html, 'Vue.js')) $libs[] = 'Vue.js';
        if (str_contains($this->html, 'angular')) $libs[] = 'Angular';
        if (str_contains($this->html, 'alpine') || str_contains($this->html, 'x-data')) $libs[] = 'Alpine.js';
        if (str_contains($this->html, 'gsap') || str_contains($this->html, 'TweenMax')) $libs[] = 'GSAP';
        if (str_contains($this->html, 'swiper')) $libs[] = 'Swiper';
        if (str_contains($this->html, 'slick')) $libs[] = 'Slick Slider';
        if (str_contains($this->html, 'owl.carousel') || str_contains($this->html, 'owlCarousel')) $libs[] = 'Owl Carousel';
        if (str_contains($this->html, 'lightbox') || str_contains($this->html, 'fancybox')) $libs[] = 'Lightbox';
        return $libs;
    }

    private function detectCssFramework(): array {
        $frameworks = [];
        if (str_contains($this->html, 'bootstrap') || str_contains($this->html, 'Bootstrap')) $frameworks[] = 'Bootstrap';
        if (str_contains($this->html, 'tailwind') || str_contains($this->html, 'Tailwind')) $frameworks[] = 'Tailwind CSS';
        if (str_contains($this->html, 'foundation')) $frameworks[] = 'Foundation';
        if (str_contains($this->html, 'bulma')) $frameworks[] = 'Bulma';
        if (str_contains($this->html, 'materialize')) $frameworks[] = 'Materialize';
        return $frameworks;
    }

    private function detectFonts(): array {
        $fonts = [];
        if (str_contains($this->html, 'fonts.googleapis.com') || str_contains($this->html, 'fonts.gstatic.com')) {
            $fonts[] = 'Google Fonts';
            // Intentar extraer los nombres de fuentes
            preg_match_all('/family=([^&"\']+)/', $this->html, $matches);
            if (!empty($matches[1])) {
                foreach (array_unique($matches[1]) as $family) {
                    $name = urldecode(explode(':', $family)[0]);
                    $name = str_replace('+', ' ', $name);
                    if ($name && !in_array($name, $fonts)) {
                        $fonts[] = $name;
                    }
                }
            }
        }
        if (str_contains($this->html, 'use.typekit.net') || str_contains($this->html, 'Adobe Fonts')) {
            $fonts[] = 'Adobe Fonts';
        }
        return array_slice($fonts, 0, 8); // Limitar a 8
    }

    private function detectCdn(): ?string {
        if (isset($this->headers['cf-ray'])) return 'Cloudflare';
        if (isset($this->headers['x-cdn']) && str_contains($this->headers['x-cdn'], 'Imperva')) return 'Imperva';
        if (isset($this->headers['x-sucuri-id'])) return 'Sucuri';
        if (isset($this->headers['via'])) {
            $via = $this->headers['via'];
            if (str_contains($via, 'cloudfront')) return 'Amazon CloudFront';
            if (str_contains($via, 'akamai')) return 'Akamai';
            if (str_contains($via, 'fastly')) return 'Fastly';
            if (str_contains($via, 'varnish')) return 'Varnish';
        }
        if (str_contains($this->html, 'cdn.jsdelivr.net')) return 'jsDelivr';
        if (str_contains($this->html, 'cdnjs.cloudflare.com')) return 'cdnjs';
        return null;
    }

    private function detectAnalytics(): array {
        $tools = [];
        if (str_contains($this->html, 'gtag') || str_contains($this->html, 'google-analytics') || str_contains($this->html, 'analytics.js')) {
            $tools[] = 'Google Analytics';
        }
        if (str_contains($this->html, 'googletagmanager.com') || str_contains($this->html, 'gtm.js')) {
            $tools[] = 'Google Tag Manager';
        }
        if (str_contains($this->html, 'fbq(') || str_contains($this->html, 'fbevents.js')) $tools[] = 'Facebook Pixel';
        if (str_contains($this->html, 'hotjar') || str_contains($this->html, 'hj(')) $tools[] = 'Hotjar';
        if (str_contains($this->html, 'clarity.ms')) $tools[] = 'Microsoft Clarity';
        if (str_contains($this->html, 'matomo') || str_contains($this->html, 'piwik')) $tools[] = 'Matomo';
        return $tools;
    }

    private function detectPhpVersion(): ?string {
        $powered = $this->headers['x-powered-by'] ?? '';
        if (preg_match('/PHP\/([\d.]+)/i', $powered, $m)) {
            return $m[1];
        }
        return null;
    }

    private function detectHttpProtocol(): ?string {
        // Este dato viene del Fetcher, no lo podemos detectar aquí desde el HTML
        return null;
    }
}
