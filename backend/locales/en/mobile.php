<?php
/**
 * Mobile analyzer strings (English).
 * Keys follow: <metric_id>.<field> where field is name|displayValue|
 * description|recommendation|imaginaSolution.
 *
 * Strings with {{param}} are interpolated from the analyzer with
 * analyzer-supplied context (e.g. mobile score).
 */
return [
    // Module chrome
    'summary' => 'Your site has a mobile score of {{score}}/100.',

    // viewport metric
    'viewport.name'             => 'Meta Viewport',
    'viewport.display.missing'  => 'Not configured',
    'viewport.desc.ok'          => 'Meta viewport properly configured with width=device-width.',
    'viewport.desc.partial'     => 'Meta viewport present but without width=device-width.',
    'viewport.desc.missing'     => 'No meta viewport found. The site will not adapt to mobile screens.',
    'viewport.recommendation'   => 'Add <meta name="viewport" content="width=device-width, initial-scale=1">.',
    'viewport.solution'         => 'We set up the viewport and the complete mobile experience.',

    // mobile_speed metric
    'mobile_speed.name'           => 'Mobile Speed (PageSpeed)',
    'mobile_speed.display.none'   => 'Not available',
    'mobile_speed.desc.ok'        => 'Google PageSpeed rates mobile speed at {{score}}/100.',
    'mobile_speed.desc.missing'   => 'Could not retrieve the mobile speed score.',
    'mobile_speed.recommendation' => 'Optimize mobile speed: reduce CSS/JS, optimize images, use lazy loading.',
    'mobile_speed.solution'       => 'We specifically optimize for mobile device speed.',

    // responsive metric
    'responsive.name'              => 'Responsive Design',
    'responsive.display.none'      => 'No clear indicators detected',
    'responsive.desc.found'        => 'Responsive design indicators detected: {{list}}.',
    'responsive.desc.missing'      => 'No clear responsive design indicators (no mobile viewport, no accessible media queries and no responsive framework classes).',
    'responsive.recommendation.partial' => 'We could not verify media queries in external CSS. Make sure the main CSS has breakpoints (@media (max-width: 768px)) for tablets and mobile.',
    'responsive.recommendation.missing' => 'Implement responsive design: add <meta name="viewport" content="width=device-width, initial-scale=1"> and use media queries in CSS.',
    'responsive.solution'          => 'We ensure your site is 100% responsive across all devices.',
    'responsive.indicator.viewport'  => 'Mobile viewport',
    'responsive.indicator.srcset'    => 'Responsive images',
    'responsive.indicator.media'     => 'Media queries',
];
