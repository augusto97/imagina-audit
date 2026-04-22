<?php
return [
    'summary' => 'Your site has a conversion score of {{score}}/100.',

    // analytics
    'analytics.name'          => 'Google Analytics',
    'analytics.display.ok'    => 'Installed ({{type}})',
    'analytics.display.none'  => 'Not detected',
    'analytics.type.ga4'      => 'GA4',
    'analytics.type.ua'       => 'Universal Analytics',
    'analytics.type.generic'  => 'Google Analytics',
    'analytics.desc.ok'       => 'Google Analytics is installed ({{type}}). You can measure traffic and behavior.',
    'analytics.desc.none'     => 'Google Analytics not detected. You cannot measure your site performance.',
    'analytics.recommend'     => 'Install Google Analytics 4 to measure traffic and conversions.',
    'analytics.solution'      => 'We install and configure Google Analytics 4 with conversion tracking.',

    // tag_manager
    'gtm.name'        => 'Google Tag Manager',
    'gtm.display.ok'  => 'Installed',
    'gtm.display.none' => 'Not detected',
    'gtm.desc.ok'     => 'Google Tag Manager is installed. Eases management of marketing scripts.',
    'gtm.desc.none'   => 'Google Tag Manager not detected.',
    'gtm.recommend'   => 'Consider implementing GTM to manage marketing tags without touching code.',
    'gtm.solution'    => 'We set up GTM to manage all marketing scripts centrally.',

    // chat
    'chat.name'        => 'Live chat / WhatsApp',
    'chat.display.ok'  => '{{list}}',
    'chat.display.none' => 'Not detected',
    'chat.desc.ok'     => 'Chat or quick contact detected: {{list}}.',
    'chat.desc.none'   => 'No live chat or WhatsApp button detected. You are losing conversions.',
    'chat.recommend'   => 'Add a live chat or WhatsApp button to capture leads.',
    'chat.solution'    => 'We install WhatsApp chat and instant-contact tools.',

    // forms
    'forms.name'        => 'Contact forms',
    'forms.display.ok_named'  => '{{list}}',
    'forms.display.ok_generic' => '{{count}} forms',
    'forms.display.none' => 'Not detected',
    'forms.desc.ok_named'    => 'Contact forms detected: {{list}}.',
    'forms.desc.ok_generic'  => 'Contact forms detected.',
    'forms.desc.none'   => 'No contact forms detected. Visitors cannot easily reach you.',
    'forms.recommend'   => 'Add a visible, accessible contact form.',
    'forms.solution'    => 'We set up optimized forms to capture leads.',

    // social_media
    'social.name'        => 'Social Media',
    'social.display.ok'  => '{{list}}',
    'social.display.none' => 'Not detected',
    'social.desc.ok'     => '{{count}} social networks detected: {{list}}.',
    'social.desc.none'   => 'No social media links detected.',
    'social.recommend'   => 'Add links to the company social networks.',
    'social.solution'    => 'We integrate social networks and configure sharing buttons.',

    // cookies_legal
    'cookies.name'             => 'Cookies & legal compliance',
    'cookies.display.tool'     => '{{name}} detected',
    'cookies.display.legal'    => 'Legal pages found',
    'cookies.display.none'     => 'Not detected',
    'cookies.desc.ok_prefix'   => 'Cookie/legal compliance detected.',
    'cookies.desc.ok_tool'     => ' Tool: {{name}}.',
    'cookies.desc.none'        => 'No cookie notice or legal pages detected. Potential GDPR non-compliance.',
    'cookies.recommend'        => 'Implement a cookie banner and create privacy policy pages.',
    'cookies.solution'         => 'We implement the cookie banner and create the required legal pages.',

    // facebook_pixel
    'fb.name'        => 'Facebook Pixel',
    'fb.display.ok'  => 'Installed',
    'fb.display.none' => 'Not detected',
    'fb.desc.ok'     => 'Facebook Pixel is installed. You can create audiences and measure campaigns.',
    'fb.desc.none'   => 'Facebook Pixel not detected. You cannot remarket on Facebook/Instagram.',
    'fb.recommend'   => 'Install Facebook Pixel if you run ads on Facebook or Instagram.',
    'fb.solution'    => 'We configure Facebook Pixel with custom conversion events.',

    // push_notifications
    'push.name'              => 'Push Notifications',
    'push.display.ok'        => '{{name}} detected',
    'push.display.sw_only'   => 'Service Worker without push',
    'push.display.none'      => 'Not detected',
    'push.desc.ok'           => 'Push notifications configured with {{name}}. Lets you re-engage visitors who leave the site.',
    'push.desc.none_sw'      => 'Push notifications not detected. A Service Worker was detected that could support push.',
    'push.desc.none'         => 'Push notifications not detected. Push can recover up to 10% of lost visitors.',
    'push.recommend'         => 'Consider implementing push notifications with OneSignal or similar to re-engage visitors.',
    'push.solution'          => 'We implement push notifications to recover visitors and boost conversions.',

    // email_marketing
    'email.name'           => 'Email Marketing',
    'email.display.named'  => '{{list}}',
    'email.display.generic' => 'Subscription form',
    'email.display.none'   => 'Not detected',
    'email.desc.ok_prefix' => 'Email marketing integration detected',
    'email.desc.ok_named'  => ': {{list}}',
    'email.desc.ok_suffix' => '. Email marketing has the best ROI of all digital channels.',
    'email.desc.none'      => 'No email marketing tool or subscription form detected. Email marketing averages $42 ROI per $1 spent.',
    'email.recommend'      => 'Add a subscription form with Mailchimp, Brevo or another email marketing tool.',
    'email.solution'       => 'We integrate email marketing tools with optimized capture forms.',

    // google_ads
    'ads.name'        => 'Google Ads',
    'ads.display.ok'  => 'Detected',
    'ads.display.none' => 'Not detected',
    'ads.desc.ok'     => 'Google Ads integration detected. Lets you measure campaign conversions.',
    'ads.desc.none'   => 'Google Ads not detected. If you advertise on Google, you need the conversion tag.',
    'ads.recommend'   => 'If you advertise on Google, install the Google Ads tag to measure conversions.',
    'ads.solution'    => 'We configure Google Ads with conversion tracking and remarketing.',
];
