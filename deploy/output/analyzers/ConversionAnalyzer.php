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
        $metrics[] = $this->checkPushNotifications();
        $metrics[] = $this->checkEmailMarketing();
        $metrics[] = $this->checkGoogleAds();

        $defaults = require dirname(__DIR__) . '/config/defaults.php';
        $score = Scoring::calculateModuleScore($metrics);

        return [
            'id' => 'conversion',
            'name' => Translator::t('modules.conversion.name'),
            'icon' => 'bar-chart-3',
            'score' => $score,
            'level' => Scoring::getLevel($score),
            'weight' => $defaults['weight_conversion'],
            'metrics' => $metrics,
            'summary' => Translator::t('conversion.summary', ['score' => $score]),
            'salesMessage' => $defaults['sales_conversion'] !== '' ? $defaults['sales_conversion'] : Translator::t('modules.sales.conversion'),
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
                if (str_contains($pattern, 'G-')) $type = Translator::t('conversion.analytics.type.ga4');
                elseif (str_contains($pattern, 'UA-')) $type = Translator::t('conversion.analytics.type.ua');
                else $type = Translator::t('conversion.analytics.type.generic');
                break;
            }
        }

        return Scoring::createMetric(
            'analytics',
            Translator::t('conversion.analytics.name'),
            $found,
            $found
                ? Translator::t('conversion.analytics.display.ok', ['type' => $type])
                : Translator::t('conversion.analytics.display.none'),
            $found ? 100 : 0,
            $found
                ? Translator::t('conversion.analytics.desc.ok', ['type' => $type])
                : Translator::t('conversion.analytics.desc.none'),
            !$found ? Translator::t('conversion.analytics.recommend') : '',
            Translator::t('conversion.analytics.solution')
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
            Translator::t('conversion.gtm.name'),
            $found,
            $found ? Translator::t('conversion.gtm.display.ok') : Translator::t('conversion.gtm.display.none'),
            $found ? 100 : 40,
            $found ? Translator::t('conversion.gtm.desc.ok') : Translator::t('conversion.gtm.desc.none'),
            !$found ? Translator::t('conversion.gtm.recommend') : '',
            Translator::t('conversion.gtm.solution')
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

        $list = implode(', ', $detected);
        return Scoring::createMetric(
            'chat',
            Translator::t('conversion.chat.name'),
            $found,
            $found ? Translator::t('conversion.chat.display.ok', ['list' => $list]) : Translator::t('conversion.chat.display.none'),
            $found ? 100 : 0,
            $found
                ? Translator::t('conversion.chat.desc.ok', ['list' => $list])
                : Translator::t('conversion.chat.desc.none'),
            !$found ? Translator::t('conversion.chat.recommend') : '',
            Translator::t('conversion.chat.solution')
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

        if ($found) {
            if (!empty($detected)) {
                $list = implode(', ', $detected);
                $display = Translator::t('conversion.forms.display.ok_named', ['list' => $list]);
                $desc = Translator::t('conversion.forms.desc.ok_named', ['list' => $list]);
            } else {
                $display = Translator::t('conversion.forms.display.ok_generic', ['count' => count($forms)]);
                $desc = Translator::t('conversion.forms.desc.ok_generic');
            }
        } else {
            $display = Translator::t('conversion.forms.display.none');
            $desc = Translator::t('conversion.forms.desc.none');
        }

        return Scoring::createMetric(
            'forms',
            Translator::t('conversion.forms.name'),
            $found,
            $display,
            $found ? 100 : 0,
            $desc,
            !$found ? Translator::t('conversion.forms.recommend') : '',
            Translator::t('conversion.forms.solution')
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

        $list = implode(', ', $detected);
        return Scoring::createMetric(
            'social_media',
            Translator::t('conversion.social.name'),
            $count,
            $count > 0
                ? Translator::t('conversion.social.display.ok', ['list' => $list])
                : Translator::t('conversion.social.display.none'),
            $score,
            $count > 0
                ? Translator::t('conversion.social.desc.ok', ['count' => $count, 'list' => $list])
                : Translator::t('conversion.social.desc.none'),
            $count < 2 ? Translator::t('conversion.social.recommend') : '',
            Translator::t('conversion.social.solution')
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

        if ($found) {
            $display = $detected
                ? Translator::t('conversion.cookies.display.tool', ['name' => $detected])
                : Translator::t('conversion.cookies.display.legal');
            $desc = Translator::t('conversion.cookies.desc.ok_prefix')
                . ($detected ? Translator::t('conversion.cookies.desc.ok_tool', ['name' => $detected]) : '');
        } else {
            $display = Translator::t('conversion.cookies.display.none');
            $desc = Translator::t('conversion.cookies.desc.none');
        }

        return Scoring::createMetric(
            'cookies_legal',
            Translator::t('conversion.cookies.name'),
            $found,
            $display,
            $found ? 100 : 20,
            $desc,
            !$found ? Translator::t('conversion.cookies.recommend') : '',
            Translator::t('conversion.cookies.solution')
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
            Translator::t('conversion.fb.name'),
            $found,
            $found ? Translator::t('conversion.fb.display.ok') : Translator::t('conversion.fb.display.none'),
            $found ? 100 : 50,
            $found ? Translator::t('conversion.fb.desc.ok') : Translator::t('conversion.fb.desc.none'),
            !$found ? Translator::t('conversion.fb.recommend') : '',
            Translator::t('conversion.fb.solution')
        );
    }

    /**
     * Notificaciones Push
     */
    private function checkPushNotifications(): array {
        $tools = [
            'onesignal.com' => 'OneSignal',
            'OneSignal' => 'OneSignal',
            'gravitec.net' => 'Gravitec',
            'pushengage.com' => 'PushEngage',
            'webpushr.com' => 'WebPushr',
            'pushowl.com' => 'PushOwl',
            'push.js' => 'Push.js',
            'pushwoosh.com' => 'Pushwoosh',
            'cleverpush.com' => 'CleverPush',
            'subscribers.com' => 'Subscribers',
        ];

        $detected = null;
        foreach ($tools as $pattern => $name) {
            if (str_contains($this->html, $pattern)) {
                $detected = $name;
                break;
            }
        }

        // También verificar si tiene Service Worker registrado (base para push)
        $hasServiceWorker = str_contains($this->html, 'serviceWorker.register') || str_contains($this->html, 'ServiceWorker');

        $found = $detected !== null;

        if ($found) {
            $display = Translator::t('conversion.push.display.ok', ['name' => $detected]);
            $desc = Translator::t('conversion.push.desc.ok', ['name' => $detected]);
        } else {
            $display = $hasServiceWorker
                ? Translator::t('conversion.push.display.sw_only')
                : Translator::t('conversion.push.display.none');
            $desc = $hasServiceWorker
                ? Translator::t('conversion.push.desc.none_sw')
                : Translator::t('conversion.push.desc.none');
        }

        return Scoring::createMetric(
            'push_notifications',
            Translator::t('conversion.push.name'),
            $found,
            $display,
            $found ? 100 : 60,
            $desc,
            !$found ? Translator::t('conversion.push.recommend') : '',
            Translator::t('conversion.push.solution')
        );
    }

    /**
     * Email Marketing
     */
    private function checkEmailMarketing(): array {
        $tools = [
            'mailchimp.com' => 'Mailchimp',
            'mc.js' => 'Mailchimp',
            'list-manage.com' => 'Mailchimp',
            'brevo.com' => 'Brevo',
            'sendinblue' => 'Brevo (Sendinblue)',
            'sib.js' => 'Brevo',
            'mailerlite.com' => 'MailerLite',
            'convertkit.com' => 'ConvertKit',
            'activecampaign.com' => 'ActiveCampaign',
            'aweber.com' => 'AWeber',
            'getresponse.com' => 'GetResponse',
            'hubspot.com/email' => 'HubSpot Email',
            'klaviyo.com' => 'Klaviyo',
            'constantcontact.com' => 'Constant Contact',
            'drip.com' => 'Drip',
        ];

        $detected = [];
        foreach ($tools as $pattern => $name) {
            if (str_contains($this->html, $pattern)) {
                $detected[] = $name;
            }
        }
        $detected = array_unique($detected);

        // Buscar formularios de suscripción genéricos
        $hasSubscriptionForm = str_contains($this->html, 'newsletter') ||
            str_contains($this->html, 'suscri') ||
            str_contains($this->html, 'subscri') ||
            str_contains($this->html, 'boletín');

        $found = !empty($detected) || $hasSubscriptionForm;

        $list = implode(', ', $detected);
        if (!empty($detected)) {
            $display = Translator::t('conversion.email.display.named', ['list' => $list]);
        } elseif ($hasSubscriptionForm) {
            $display = Translator::t('conversion.email.display.generic');
        } else {
            $display = Translator::t('conversion.email.display.none');
        }

        if ($found) {
            $desc = Translator::t('conversion.email.desc.ok_prefix')
                . (!empty($detected) ? Translator::t('conversion.email.desc.ok_named', ['list' => $list]) : '')
                . Translator::t('conversion.email.desc.ok_suffix');
        } else {
            $desc = Translator::t('conversion.email.desc.none');
        }

        return Scoring::createMetric(
            'email_marketing',
            Translator::t('conversion.email.name'),
            $found,
            $display,
            $found ? 100 : 40,
            $desc,
            !$found ? Translator::t('conversion.email.recommend') : '',
            Translator::t('conversion.email.solution'),
            ['detected' => $detected, 'hasSubscriptionForm' => $hasSubscriptionForm]
        );
    }

    /**
     * Google Ads
     */
    private function checkGoogleAds(): array {
        $found = str_contains($this->html, 'googleads.g.doubleclick.net') ||
                 str_contains($this->html, 'googlesyndication.com') ||
                 str_contains($this->html, 'adsbygoogle') ||
                 str_contains($this->html, 'conversion.js') ||
                 str_contains($this->html, 'google_conversion') ||
                 str_contains($this->html, 'gads') ||
                 str_contains($this->html, 'AW-');

        return Scoring::createMetric(
            'google_ads',
            Translator::t('conversion.ads.name'),
            $found,
            $found ? Translator::t('conversion.ads.display.ok') : Translator::t('conversion.ads.display.none'),
            $found ? 100 : 60,
            $found ? Translator::t('conversion.ads.desc.ok') : Translator::t('conversion.ads.desc.none'),
            !$found ? Translator::t('conversion.ads.recommend') : '',
            Translator::t('conversion.ads.solution')
        );
    }
}
