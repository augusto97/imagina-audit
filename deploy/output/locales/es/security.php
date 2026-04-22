<?php
return [
    'summary' => 'Tu sitio tiene una puntuación de seguridad de {{score}}/100.',

    // ssl_valid
    'ssl.name'             => 'Certificado SSL',
    'ssl.display.invalid'  => 'No válido o no presente',
    'ssl.display.valid'    => 'Válido hasta {{validTo}} ({{days}} días)',
    'ssl.desc.invalid'     => 'El sitio no tiene un certificado SSL válido. Los visitantes verán advertencias de seguridad.',
    'ssl.desc.valid'       => 'Certificado SSL válido emitido por {{issuer}}. Expira el {{validTo}}.',
    'ssl.desc.expiring'    => 'Certificado SSL próximo a expirar ({{days}} días). Emitido por {{issuer}}.',
    'ssl.recommend.install' => 'Instalar un certificado SSL (Let\'s Encrypt es gratuito).',
    'ssl.recommend.renew'   => 'Renovar el certificado SSL antes de que expire.',
    'ssl.solution'         => 'Instalamos y configuramos SSL gratuito con Let\'s Encrypt en tu hosting. Monitoreamos la expiración y lo renovamos automáticamente.',

    // https_redirect
    'redirect.name'            => 'Redirección HTTP → HTTPS',
    'redirect.display.ok'      => 'Configurada correctamente',
    'redirect.display.missing' => 'No configurada',
    'redirect.desc.ok'         => 'HTTP redirige correctamente a HTTPS.',
    'redirect.desc.missing'    => 'HTTP no redirige a HTTPS. Los visitantes podrían acceder a la versión no segura.',
    'redirect.recommend'       => 'Configurar redirección 301 de HTTP a HTTPS.',
    'redirect.solution'        => 'Configuramos la redirección HTTPS y forzamos conexiones seguras.',

    // hsts_preload
    'hsts.name'             => 'HSTS Preload',
    'hsts.display.ready'    => 'Listo para preload',
    'hsts.display.partial'  => 'HSTS sin preload',
    'hsts.display.none'     => 'Sin HSTS',
    'hsts.desc.ready'       => 'HSTS completamente configurado con preload, includeSubDomains y max-age >= 1 año. Listo para enviar a hstspreload.org.',
    'hsts.desc.partial'     => 'HSTS presente pero falta preload / includeSubDomains / max-age suficiente para calificar al preload list de Chrome.',
    'hsts.desc.none'        => 'Sin HSTS. Configurar para forzar HTTPS y poder solicitar inclusión en el preload list.',
    'hsts.recommend'        => 'Configurar: Strict-Transport-Security: max-age=31536000; includeSubDomains; preload. Luego registrar en hstspreload.org',
    'hsts.solution'         => 'Configuramos HSTS con preload para máxima protección HTTPS.',

    // weak_tls
    'tls.name'          => 'Versiones TLS débiles',
    'tls.display.ok'    => 'Solo TLS 1.2+',
    'tls.display.weak'  => '{{list}} habilitado',
    'tls.desc.ok'       => 'El servidor solo acepta TLS 1.2 y superior. Correcto.',
    'tls.desc.weak'     => 'El servidor acepta versiones TLS débiles: {{list}}. Son vulnerables a ataques como POODLE y BEAST.',
    'tls.recommend'     => 'Desactivar TLS 1.0 y 1.1 en la configuración del servidor. Solo habilitar TLS 1.2 y TLS 1.3.',
    'tls.solution'      => 'Configuramos el servidor para aceptar solo versiones modernas de TLS.',

    // dnssec
    'dnssec.name'               => 'DNSSEC',
    'dnssec.display.enabled'    => 'Habilitado',
    'dnssec.display.disabled'   => 'No habilitado',
    'dnssec.display.invalid'    => 'N/A',
    'dnssec.desc.enabled'       => 'DNSSEC habilitado. Protege contra envenenamiento de caché DNS y redirecciones maliciosas.',
    'dnssec.desc.disabled'      => 'DNSSEC no habilitado. Sin firma DNS, es posible suplantar los registros DNS del dominio.',
    'dnssec.desc.invalid'       => 'Dominio inválido.',
    'dnssec.recommend'          => 'Habilitar DNSSEC en tu registrador o proveedor DNS (Cloudflare lo hace con 1 click).',
    'dnssec.solution'           => 'Configuramos DNSSEC para proteger contra ataques de DNS.',

    // source_code_exposure
    'source.name'            => 'Exposición de código fuente',
    'source.display.safe'    => 'Protegido',
    'source.display.exposed' => '{{count}} archivos expuestos',
    'source.desc.safe'       => 'No se detectaron archivos de control de versiones expuestos (.git, .svn). Correcto.',
    'source.desc.exposed'    => 'CRÍTICO: Archivos de control de versiones accesibles: {{list}}. Un atacante puede descargar todo el código fuente incluyendo credenciales.',
    'source.recommend'       => 'Bloquear acceso a /.git/, /.svn/, etc. en .htaccess o eliminar estos directorios del servidor web.',
    'source.solution'        => 'Protegemos contra fugas de código fuente y archivos de sistema.',

    // security_headers (bundle)
    'headers.name'           => 'Headers de seguridad',
    'headers.display'        => '{{present}}/7 headers configurados',
    'headers.desc.none'      => 'Crítico: no hay headers de seguridad presentes. El sitio está altamente expuesto a ataques web comunes.',
    'headers.desc.partial'   => 'Faltan algunos headers de seguridad: {{missing}}.',
    'headers.desc.ok'        => 'Todos los headers modernos de seguridad están correctamente configurados.',
    'headers.recommend'      => 'Configurar los headers de seguridad faltantes en el servidor o desde WordPress. Los más críticos: {{list}}.',
    'headers.solution'       => 'Configuramos todos los headers modernos de seguridad en tu hosting.',

    // exposed_headers
    'exposed.name'            => 'Headers expuestos',
    'exposed.display.ok'      => 'Ocultos',
    'exposed.display.exposed' => '{{list}} visibles',
    'exposed.desc.ok'         => 'No se exponen headers sensibles.',
    'exposed.desc.exposed'    => 'Estos headers revelan información del servidor: {{list}}. Facilitan que atacantes identifiquen vulnerabilidades.',
    'exposed.recommend'       => 'Eliminar u ofuscar los headers Server y X-Powered-By en la configuración del servidor.',
    'exposed.solution'        => 'Ocultamos información de versión del servidor para reducir la superficie de ataque.',

    // sri
    'sri.name'            => 'Subresource Integrity (SRI)',
    'sri.display'         => '{{withSri}}/{{total}} scripts externos con SRI',
    'sri.desc.ok'         => 'Scripts externos sin hash de integridad: {{count}}. SRI protege contra scripts comprometidos en CDNs.',
    'sri.desc.none'       => 'No se detectaron scripts externos o todos tienen SRI. Correcto.',
    'sri.recommend'       => 'Agregar integrity="sha384-..." a los tags <script> que cargan desde CDNs externos.',
    'sri.solution'        => 'Implementamos SRI en scripts externos para proteger contra ataques de supply-chain en CDNs.',

    // exposed_email
    'email.name'            => 'Email expuesto en sitio',
    'email.display.ok'      => 'Protegido',
    'email.display.exposed' => '{{count}} emails visibles',
    'email.desc.ok'         => 'No se detectaron emails expuestos en el HTML.',
    'email.desc.exposed'    => 'Se encontraron {{count}} emails en el HTML público: {{list}}. Los bots de spam los recolectan.',
    'email.recommend'       => 'Reemplazar emails visibles con formularios de contacto o protegerlos con JavaScript obfuscation.',
    'email.solution'        => 'Protegemos los emails visibles con obfuscación anti-spam.',

    // dmarc
    'dmarc.name'         => 'DMARC',
    'dmarc.display.ok'   => 'Configurado ({{policy}})',
    'dmarc.display.none' => 'No configurado',
    'dmarc.desc.ok'      => 'Política DMARC {{policy}}. Protege el dominio contra suplantación de email.',
    'dmarc.desc.none'    => 'Sin registro DMARC. Atacantes pueden enviar emails suplantando tu dominio.',
    'dmarc.recommend'    => 'Agregar un registro DNS TXT para _dmarc con v=DMARC1; p=reject; rua=mailto:...',
    'dmarc.solution'     => 'Configuramos DMARC para proteger contra suplantación del dominio.',

    // spf
    'spf.name'         => 'SPF',
    'spf.display.ok'   => 'Configurado',
    'spf.display.none' => 'No configurado',
    'spf.desc.ok'      => 'Registro SPF configurado. Autoriza los remitentes en nombre del dominio.',
    'spf.desc.none'    => 'Sin registro SPF. Los emails del dominio pueden marcarse como spam.',
    'spf.recommend'    => 'Agregar un registro DNS TXT con v=spf1 include:tu-proveedor-email.com ~all',
    'spf.solution'     => 'Configuramos SPF y DKIM para óptima entregabilidad de email.',

    // safe_browsing
    'sb.name'         => 'Google Safe Browsing',
    'sb.display.ok'   => 'Limpio',
    'sb.display.bad'  => 'REPORTADO como inseguro',
    'sb.display.na'   => 'No verificable',
    'sb.desc.ok'      => 'Google Safe Browsing no reporta amenazas para este dominio.',
    'sb.desc.bad'     => 'Google Safe Browsing ha reportado este sitio como inseguro (malware / phishing / contenido engañoso). Los navegadores lo bloquean.',
    'sb.desc.na'      => 'No se pudo verificar Safe Browsing (API no configurada).',
    'sb.recommend'    => 'Limpiar contenido de malware / phishing y solicitar revisión en Google Search Console.',
    'sb.solution'     => 'Monitoreamos y limpiamos malware, luego solicitamos el des-listado en Google.',

    // directory_listing
    'dir.name'            => 'Directory listing',
    'dir.display.ok'      => 'Protegido',
    'dir.display.exposed' => '{{count}} directorios expuestos',
    'dir.desc.ok'         => 'No se encontró listado de directorios abierto.',
    'dir.desc.exposed'    => 'Directory listing habilitado en: {{list}}. Atacantes pueden navegar y descargar archivos directamente.',
    'dir.recommend'       => 'Deshabilitar directory indexing con Options -Indexes en .htaccess.',
    'dir.solution'        => 'Deshabilitamos directory indexing para evitar information disclosure.',

    // wp_info_files
    'wpinfo.name'            => 'Archivos info de WordPress expuestos',
    'wpinfo.display.ok'      => 'Protegidos',
    'wpinfo.display.exposed' => '{{count}} archivos expuestos',
    'wpinfo.desc.ok'         => 'Los archivos info de WordPress están protegidos o no accesibles.',
    'wpinfo.desc.exposed'    => 'Estos archivos revelan información de la estructura de WordPress: {{list}}.',
    'wpinfo.recommend'       => 'Eliminar o proteger readme.html, license.txt y archivos similares.',
    'wpinfo.solution'        => 'Eliminamos los archivos info innecesarios y protegemos la instalación WP.',

    // wp_install_files
    'wpinstall.name'            => 'Archivos install/debug de WordPress expuestos',
    'wpinstall.display.ok'      => 'Protegidos',
    'wpinstall.display.exposed' => '{{count}} archivos expuestos',
    'wpinstall.desc.ok'         => 'No hay archivos de instalación o debug accesibles.',
    'wpinstall.desc.exposed'    => 'CRÍTICO: archivos expuestos: {{list}}. Podrían permitir hijack de instalación o fuga de información.',
    'wpinstall.recommend'       => 'Eliminar install.php, upgrade.php y debug.log del web root. Proteger wp-admin/install.php en .htaccess.',
    'wpinstall.solution'        => 'Aseguramos estos puntos de entrada y eliminamos archivos debug.',

    // php_in_uploads
    'phpup.name'            => 'PHP en /wp-content/uploads/',
    'phpup.display.ok'      => 'Bloqueado',
    'phpup.display.exposed' => 'PHP ejecutable',
    'phpup.desc.ok'         => 'La ejecución de PHP en uploads/ está bloqueada. Correcto.',
    'phpup.desc.exposed'    => 'CRÍTICO: PHP se ejecuta en wp-content/uploads/. Un atacante que suba un archivo malicioso puede ejecutar código.',
    'phpup.recommend'       => 'Agregar un .htaccess en wp-content/uploads/ con <FilesMatch "\\.php$"> Deny from all </FilesMatch>.',
    'phpup.solution'        => 'Bloqueamos la ejecución de PHP en uploads/ con hardening a nivel de hosting.',

    // rest_api_enum_extra
    'restextra.name'            => 'Enumeración REST API extra',
    'restextra.display.ok'      => 'Protegida',
    'restextra.display.exposed' => '{{count}} endpoints expuestos',
    'restextra.desc.ok'         => 'Los endpoints sensibles de la REST API están protegidos.',
    'restextra.desc.exposed'    => 'Endpoints sensibles accesibles: {{list}}. Permiten enumeración de usuarios / datos sensibles.',
    'restextra.recommend'       => 'Restringir el acceso a los endpoints REST adicionales sensibles.',
    'restextra.solution'        => 'Restringimos los endpoints REST API solo para usuarios autenticados.',

    // default_admin_user
    'admin.name'         => 'Usuario "admin" por defecto',
    'admin.display.ok'   => 'No detectado',
    'admin.display.bad'  => 'Detectado / probable',
    'admin.desc.ok'      => 'No se detectó usuario por defecto \'admin\'.',
    'admin.desc.bad'     => 'Se detectó un usuario \'admin\' (o similar). Muy blanco de ataques de fuerza bruta.',
    'admin.recommend'    => 'Renombrar el usuario a uno no obvio y usar contraseñas fuertes.',
    'admin.solution'     => 'Renombramos usuarios por defecto y configuramos protección contra fuerza bruta.',

    // security_plugin
    'splugin.name'         => 'Plugin de seguridad',
    'splugin.display.ok'   => 'Detectado: {{name}}',
    'splugin.display.none' => 'No detectado',
    'splugin.desc.ok'      => 'Plugin de seguridad instalado: {{name}}.',
    'splugin.desc.none'    => 'No se detectó plugin de seguridad. El sitio está expuesto a ataques comunes.',
    'splugin.recommend'    => 'Instalar y configurar un plugin de seguridad reconocido (Wordfence, Sucuri, iThemes Security).',
    'splugin.solution'     => 'Instalamos y configuramos un plugin de seguridad profesional con monitoreo activo.',

    // core_vulnerabilities
    'cve_core.name'            => 'CVEs del core de WordPress',
    'cve_core.display.ok'      => 'Sin CVEs conocidos',
    'cve_core.display.exposed' => '{{count}} CVEs ({{worst}})',
    'cve_core.desc.ok'         => 'No hay CVEs conocidos que afecten esta versión de WordPress.',
    'cve_core.desc.exposed'    => 'WordPress {{version}} está afectado por {{count}} CVEs conocidos. Severidad peor: {{worst}}.',
    'cve_core.recommend'       => 'Actualizar WordPress inmediatamente a la última versión estable.',
    'cve_core.solution'        => 'Mantenemos el core de WP actualizado con testing y parches automáticos.',

    // plugin_vulnerabilities
    'cve_plugins.name'            => 'Plugins vulnerables',
    'cve_plugins.display.ok'      => 'Sin CVEs detectados',
    'cve_plugins.display.exposed' => '{{count}} plugins con CVE',
    'cve_plugins.desc.ok'         => 'No hay plugins con CVEs activos conocidos.',
    'cve_plugins.desc.exposed'    => '{{count}} plugins afectados por CVEs conocidos: {{list}}.',
    'cve_plugins.recommend'       => 'Actualizar los plugins afectados inmediatamente. Si no hay fix disponible, reemplazarlos por una alternativa segura.',
    'cve_plugins.solution'        => 'Actualizamos los plugins vulnerables semanalmente y reemplazamos los que ya no reciben actualizaciones.',

    // theme_vulnerabilities
    'cve_theme.name'            => 'Vulnerabilidades del tema',
    'cve_theme.display.ok'      => 'Sin CVEs detectados',
    'cve_theme.display.exposed' => '{{count}} CVEs en tema',
    'cve_theme.desc.ok'         => 'No se detectaron CVEs conocidos en el tema activo.',
    'cve_theme.desc.exposed'    => 'El tema {{theme}} está afectado por {{count}} CVEs conocidos.',
    'cve_theme.recommend'       => 'Actualizar el tema o reemplazarlo por uno mantenido activamente.',
    'cve_theme.solution'        => 'Mantenemos tu tema seguro y migramos a una alternativa segura si está abandonado.',
];
