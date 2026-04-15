<?php
/**
 * Analiza herramientas de conversión: analytics, chat, formularios, marketing
 */

class ConversionAnalyzer {
    private string $html;
    private HtmlParser $parser;

    public function __construct(string $html) {
        $this->html = $html;
        $this->parser = new HtmlParser();
        $this->parser->loadHtml($html);
    }

    /**
     * Ejecuta el análisis de herramientas de conversión
     */
    public function analyze(): array {
        $metrics = [];

        $metrics[] = $this->checkAnalytics();
        $metrics[] = $this->checkTagManager();
        $metrics[] = $this->checkChat();
        $metrics[] = $this->checkForms();
        $metrics[] = $this->checkSocialMedia();
        $metrics[] = $this->checkCookies();
        $metrics[] = $this->checkFacebookPixel();

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'conversion',
            'name' => 'Conversión y Marketing',
            'icon' => 'bar-chart-3',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_conversion'],
            'metrics' => $metrics,
            'summary' => "Tu sitio tiene una puntuación de conversión de $score/100.",
            'salesMessage' => $defaults['sales_conversion'],
        ];
    }

    /**
     * Google Analytics
     */
    private function checkAnalytics(): array {
        $patterns = ['gtag(', 'analytics.js', 'gtag/js?id=G-', 'gtag/js?id=UA-', 'GoogleAnalyticsObject', 'ga.js'];
        $found = false;
        $type = '';

        foreach ($patterns as $pattern) {
            if (str_contains($this->html, $pattern)) {
                $found = true;
                if (str_contains($pattern, 'G-')) $type = 'GA4';
                elseif (str_contains($pattern, 'UA-')) $type = 'Universal Analytics';
                else $type = 'Google Analytics';
                break;
            }
        }

        return Scoring::createMetric(
            'analytics',
            'Google Analytics',
            $found,
            $found ? "Instalado ($type)" : 'No detectado',
            $found ? 100 : 0,
            $found
                ? "Google Analytics está instalado ($type). Puedes medir el tráfico y comportamiento."
                : 'No se detectó Google Analytics. No puedes medir el rendimiento de tu sitio.',
            !$found ? 'Instalar Google Analytics 4 para medir tráfico y conversiones.' : '',
            'Instalamos y configuramos Google Analytics 4 con seguimiento de conversiones.'
        );
    }

    /**
     * Google Tag Manager
     */
    private function checkTagManager(): array {
        $found = str_contains($this->html, 'gtm.js') ||
                 str_contains($this->html, 'GTM-') ||
                 str_contains($this->html, 'googletagmanager.com');

        return Scoring::createMetric(
            'tag_manager',
            'Google Tag Manager',
            $found,
            $found ? 'Instalado' : 'No detectado',
            $found ? 100 : 40,
            $found
                ? 'Google Tag Manager está instalado. Facilita la gestión de scripts de marketing.'
                : 'No se detectó Google Tag Manager.',
            !$found ? 'Considerar implementar GTM para gestionar tags de marketing sin modificar código.' : '',
            'Configuramos GTM para gestionar todos los scripts de marketing de forma centralizada.'
        );
    }

    /**
     * Chat en vivo / WhatsApp
     */
    private function checkChat(): array {
        $chats = [
            'tawk.to' => 'Tawk.to',
            'crisp.chat' => 'Crisp',
            'intercom' => 'Intercom',
            'tidio' => 'Tidio',
            'jivo' => 'JivoChat',
            'drift.com' => 'Drift',
            'hubspot.com' => 'HubSpot',
            'wa.me/' => 'WhatsApp',
            'api.whatsapp.com' => 'WhatsApp',
            'joinchat' => 'JoinChat (WhatsApp)',
        ];

        $detected = [];
        foreach ($chats as $pattern => $name) {
            if (str_contains($this->html, $pattern)) {
                $detected[] = $name;
            }
        }
        $detected = array_unique($detected);

        $found = !empty($detected);

        return Scoring::createMetric(
            'chat',
            'Chat en vivo / WhatsApp',
            $found,
            $found ? implode(', ', $detected) : 'No detectado',
            $found ? 100 : 0,
            $found
                ? 'Chat o contacto rápido detectado: ' . implode(', ', $detected) . '.'
                : 'No se detectó chat en vivo ni botón de WhatsApp. Pierdes conversiones.',
            !$found ? 'Agregar un chat en vivo o botón de WhatsApp para captar leads.' : '',
            'Instalamos chat de WhatsApp y herramientas de atención instantánea.'
        );
    }

    /**
     * Formularios de contacto
     */
    private function checkForms(): array {
        $formPlugins = [
            'wpcf7' => 'Contact Form 7',
            'gform' => 'Gravity Forms',
            'wpforms' => 'WPForms',
            'elementor-form' => 'Elementor Forms',
            'formidable' => 'Formidable Forms',
            'ninja-forms' => 'Ninja Forms',
        ];

        $detected = [];
        foreach ($formPlugins as $pattern => $name) {
            if (str_contains($this->html, $pattern)) {
                $detected[] = $name;
            }
        }

        // Buscar formularios HTML genéricos
        $forms = $this->parser->getForms();
        $hasGenericForms = count($forms) > 0;

        $found = !empty($detected) || $hasGenericForms;

        return Scoring::createMetric(
            'forms',
            'Formularios de contacto',
            $found,
            $found ? (!empty($detected) ? implode(', ', $detected) : count($forms) . ' formularios') : 'No detectados',
            $found ? 100 : 0,
            $found
                ? 'Se detectaron formularios de contacto' . (!empty($detected) ? ': ' . implode(', ', $detected) : '') . '.'
                : 'No se detectaron formularios de contacto. Los visitantes no pueden contactarte fácilmente.',
            !$found ? 'Agregar un formulario de contacto visible y accesible.' : '',
            'Configuramos formularios optimizados para captar leads.'
        );
    }

    /**
     * Redes sociales
     */
    private function checkSocialMedia(): array {
        $networks = [
            'facebook.com' => 'Facebook',
            'instagram.com' => 'Instagram',
            'twitter.com' => 'Twitter/X',
            'x.com' => 'Twitter/X',
            'linkedin.com' => 'LinkedIn',
            'youtube.com' => 'YouTube',
            'tiktok.com' => 'TikTok',
        ];

        $detected = [];
        $links = $this->parser->getLinks();
        foreach ($links as $link) {
            foreach ($networks as $domain => $name) {
                if (str_contains($link['href'] ?? '', $domain) && !in_array($name, $detected)) {
                    $detected[] = $name;
                }
            }
        }

        $count = count($detected);
        $score = $count >= 3 ? 100 : ($count >= 1 ? 60 : 0);

        return Scoring::createMetric(
            'social_media',
            'Redes Sociales',
            $count,
            $count > 0 ? implode(', ', $detected) : 'No detectadas',
            $score,
            $count > 0
                ? "Se detectaron enlaces a $count redes sociales: " . implode(', ', $detected) . '.'
                : 'No se detectaron enlaces a redes sociales.',
            $count < 2 ? 'Agregar enlaces a las redes sociales de la empresa.' : '',
            'Integramos las redes sociales y configuramos sharing buttons.'
        );
    }

    /**
     * Cumplimiento cookies/legal
     */
    private function checkCookies(): array {
        $cookieTools = [
            'cookiebot' => 'CookieBot',
            'cookieyes' => 'CookieYes',
            'complianz' => 'Complianz',
            'gdpr' => 'GDPR Plugin',
            'cookie-law' => 'Cookie Law',
            'cookie-notice' => 'Cookie Notice',
            'cookie-consent' => 'Cookie Consent',
        ];

        $detected = null;
        foreach ($cookieTools as $pattern => $name) {
            if (stripos($this->html, $pattern) !== false) {
                $detected = $name;
                break;
            }
        }

        // Buscar enlaces legales
        $legalLinks = ['/privacy', '/politica-de-privacidad', '/aviso-legal', '/terms', '/legal'];
        $hasLegal = false;
        foreach ($legalLinks as $path) {
            if (str_contains($this->html, $path)) {
                $hasLegal = true;
                break;
            }
        }

        $found = $detected !== null || $hasLegal;

        return Scoring::createMetric(
            'cookies_legal',
            'Cookies y Cumplimiento Legal',
            $found,
            $found ? ($detected ? "$detected detectado" : 'Páginas legales encontradas') : 'No detectado',
            $found ? 100 : 20,
            $found
                ? 'Se detectó cumplimiento de cookies/legal.' . ($detected ? " Herramienta: $detected." : '')
                : 'No se detectó aviso de cookies ni páginas legales. Posible incumplimiento de GDPR.',
            !$found ? 'Implementar un aviso de cookies y crear páginas de política de privacidad.' : '',
            'Implementamos aviso de cookies y creamos las páginas legales necesarias.'
        );
    }

    /**
     * Facebook Pixel
     */
    private function checkFacebookPixel(): array {
        $found = str_contains($this->html, 'fbq(') ||
                 str_contains($this->html, 'facebook.com/tr?') ||
                 str_contains($this->html, 'fbevents.js');

        return Scoring::createMetric(
            'facebook_pixel',
            'Facebook Pixel',
            $found,
            $found ? 'Instalado' : 'No detectado',
            $found ? 100 : 50,
            $found
                ? 'Facebook Pixel está instalado. Puedes crear audiencias y medir campañas.'
                : 'No se detectó Facebook Pixel. No puedes hacer remarketing en Facebook/Instagram.',
            !$found ? 'Instalar Facebook Pixel si haces publicidad en Facebook o Instagram.' : '',
            'Configuramos Facebook Pixel con eventos de conversión personalizados.'
        );
    }
}
