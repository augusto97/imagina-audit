/**
 * Imagina Audit — Widget Embebible v2.1
 *
 * Dos modos (data-mode):
 *
 *   floating — burbuja flotante en una esquina; abre un popup con el flujo.
 *   inline   — bloque embebido dentro del contenido de la página host
 *              (ideal para landings de Google Ads / mini-funnel).
 *
 * Estilo (solo afecta lo visual, no el flujo):
 *   data-theme : 'light' (default) | 'dark'
 *   data-style : 'card' (default) | 'gradient' | 'minimal'
 *   data-color : color de acento (botones, gauge, foco de inputs)
 *
 * Atributos comunes:
 *   data-api            (obligatorio)  base del backend, ej. https://x/api
 *   data-lang           es | en
 *   data-whatsapp       +57...  (añade botón WhatsApp al final)
 *   data-position       bottom-right | bottom-left   (solo floating)
 *   data-target         #selector  (solo inline; default #imagina-audit-block)
 *   data-gtm-event      nombre del evento dataLayer (conversión Google Ads/GTM)
 *   data-gtag-conversion AW-XXXXXXXXXX/abc  (conversión gtag.js directa)
 *
 * IMPORTANTE — campos de contacto:
 *   El widget consulta /api/config.php y muestra EXACTAMENTE los campos que
 *   el admin marcó como obligatorios en Configuración → Captura de leads
 *   (email / nombre / WhatsApp). Así nunca pide un campo que el backend
 *   rechazará por faltante (ni al revés).
 *
 * Conversiones: ambos eventos se disparan UNA vez, al enviar el formulario
 * de contacto (el momento real de la conversión).
 */
(function () {
  'use strict';

  // ─── Localizar el <script> propio de forma robusta ──────────────────
  var script = document.currentScript;
  if (!script || !script.getAttribute('data-api')) {
    var all = document.querySelectorAll('script[data-api]');
    for (var i = 0; i < all.length; i++) {
      if (all[i].src && all[i].src.indexOf('imagina-audit-widget') !== -1) { script = all[i]; break; }
    }
  }

  // Fallback de configuración por objeto global. Cuando el widget se
  // inyecta INLINE (p.ej. el preview de /admin/embed, que descarga el
  // código fresco y lo ejecuta sin un <script src>), no hay atributos
  // data-* que leer del tag. En ese caso la config viene de
  // window.IMAGINA_AUDIT_CONFIG = { 'data-api': ..., 'data-mode': ... }.
  var GCFG = (typeof window !== 'undefined' && window.IMAGINA_AUDIT_CONFIG) ? window.IMAGINA_AUDIT_CONFIG : null;

  if (!script && !GCFG) { console.error('[ImaginaAudit] no se encontró el script tag'); return; }

  // Lectores unificados: primero el atributo del tag, luego el objeto global.
  function ga(name) {
    if (script && script.getAttribute && script.getAttribute(name) != null) return script.getAttribute(name);
    if (GCFG && GCFG[name] != null) return GCFG[name];
    return null;
  }
  function ha(name) {
    if (script && script.hasAttribute && script.hasAttribute(name)) return true;
    if (GCFG && Object.prototype.hasOwnProperty.call(GCFG, name)) return true;
    return false;
  }

  var API = (ga('data-api') || '').replace(/\/+$/, '');
  var MODE = (ga('data-mode') || 'floating').toLowerCase();
  var TARGET = ga('data-target') || '#imagina-audit-block';
  var COLOR = ga('data-color') || '#0CC0DF';
  var THEME = (ga('data-theme') || 'light').toLowerCase();
  var STYLE = (ga('data-style') || 'card').toLowerCase();
  var POS = ga('data-position') || 'bottom-right';
  var LANG = (ga('data-lang') || 'es').slice(0, 2);
  var WHATSAPP = ga('data-whatsapp') || '';
  var GTM_EVENT = ga('data-gtm-event') || '';
  var GTAG_CONV = ga('data-gtag-conversion') || '';

  // Modo demo: el widget arranca directamente en el estado "preview" con
  // datos ficticios, sin escanear nada y sin tocar la red. Usado por
  // /admin/embed para que el admin vea el bloque completo sin pelearse
  // con un escaneo real.
  var DEMO = String(ga('data-demo') || '').toLowerCase();
  var IS_DEMO = DEMO === '1' || DEMO === 'true' || DEMO === 'yes';
  // Estado demo inicial: 'preview' (default) o 'form' (para previsualizar
  // el formulario de entrada con los estilos).
  var DEMO_STATE = (ga('data-demo-state') || 'preview').toLowerCase();

  // Overrides explícitos de los campos requeridos del gate. Útil para el
  // preview del admin (donde queremos un resultado determinista que NO
  // dependa de la latencia de /api/config.php) y para escenarios donde
  // el sitio host quiera forzar campos sin tocar la config global.
  function hasAttr(n) { return ha(n); }
  function attrBool(n) {
    var v = String(ga(n) || '').toLowerCase();
    return v === '1' || v === 'true' || v === 'yes';
  }
  var FORCE_REQ = {
    email: hasAttr('data-require-email') ? attrBool('data-require-email') : null,
    name: hasAttr('data-require-name') ? attrBool('data-require-name') : null,
    whatsapp: hasAttr('data-require-whatsapp') ? attrBool('data-require-whatsapp') : null,
  };
  var HAS_FORCE = FORCE_REQ.email !== null || FORCE_REQ.name !== null || FORCE_REQ.whatsapp !== null;

  if (!API && !IS_DEMO) { console.error('[ImaginaAudit] data-api es obligatorio'); return; }

  // ─── Config del servidor (campos obligatorios del gate) ─────────────
  // La pedimos al arrancar; para cuando el visitante termina un escaneo
  // (segundos) ya está resuelta. Si llega tarde (gate ya renderizado),
  // disparamos un callback registrado para re-renderizar el gate con los
  // campos correctos — antes había una race condition que dejaba el
  // WhatsApp escondido aunque estuviera marcado obligatorio.
  var serverCfg = null;
  var onConfigReady = null;
  if (API && !HAS_FORCE) {
    fetch(API + '/config.php')
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.success && j.data) {
          serverCfg = j.data;
          if (typeof onConfigReady === 'function') onConfigReady();
        }
      })
      .catch(function () { /* usaremos defaults */ });
  }

  function gateReqs() {
    // 1) Overrides explícitos vía data-require-* tienen prioridad absoluta.
    //    Lo usa el preview del admin para reflejar la config sin esperar
    //    al fetch async.
    if (HAS_FORCE) {
      return {
        email:    FORCE_REQ.email    !== null ? FORCE_REQ.email    : true,
        name:     FORCE_REQ.name     !== null ? FORCE_REQ.name     : false,
        whatsapp: FORCE_REQ.whatsapp !== null ? FORCE_REQ.whatsapp : false,
      };
    }
    // 2) Config del servidor.
    var lc = serverCfg && serverCfg.leadCapture;
    if (lc) return { email: !!lc.requireEmail, name: !!lc.requireName, whatsapp: !!lc.requireWhatsapp };
    // 3) Fallback prudente.
    return { email: true, name: false, whatsapp: false };
  }
  function cfgPlaceholder(key, fallback) {
    var f = serverCfg && serverCfg.form;
    return (f && f[key]) ? f[key] : fallback;
  }

  // ─── i18n ────────────────────────────────────────────────────────────
  var t = {
    es: {
      title: 'Auditoría Web Gratuita', subtitle: 'Descubre el estado de tu sitio en 30 segundos',
      urlPh: 'https://ejemplo.com', emailPh: 'tu@email.com', namePh: 'Tu nombre', whatsappPh: '+57 300 000 0000',
      btnScan: 'Auditar gratis', btnUnlock: 'Desbloquear informe completo', btnNew: 'Escanear otro sitio',
      btnFull: 'Ver informe completo', btnContact: 'Hablar con un experto', retry: 'Reintentar',
      scanning: 'Analizando tu sitio…', queued: 'En cola — esperando turno',
      error: 'No pudimos analizar el sitio. Verifica la URL e inténtalo de nuevo.',
      scoreLabels: { critical: 'Crítico', warning: 'Regular', good: 'Bueno', excellent: 'Excelente', info: 'Info', unknown: '—' },
      previewLeadIn: 'Problemas detectados', moreIssues: 'y {{n}} problemas más',
      gateTitle: 'Tu informe completo está listo', gateSubtitle: 'Te enviamos por correo el link al informe detallado con el plan de soluciones.',
      gatePrivacy: 'Solo usamos tus datos para enviarte el informe. Sin spam.',
      optional: '(opcional)', required: 'obligatorio',
      sentTitle: '¡Informe enviado!', sentSubtitle: 'Revisa tu bandeja en {{email}}. También puedes abrirlo ahora:',
      errEmail: 'Ingresa un email válido.', errName: 'Ingresa tu nombre.', errWhatsapp: 'Ingresa tu WhatsApp.',
      issuesGood: 'No encontramos problemas graves. ¡Buen trabajo!'
    },
    en: {
      title: 'Free Website Audit', subtitle: 'Discover your site health in 30 seconds',
      urlPh: 'https://example.com', emailPh: 'you@email.com', namePh: 'Your name', whatsappPh: '+1 555 000 0000',
      btnScan: 'Audit free', btnUnlock: 'Unlock full report', btnNew: 'Scan another site',
      btnFull: 'View full report', btnContact: 'Talk to an expert', retry: 'Retry',
      scanning: 'Scanning your site…', queued: 'Queued — waiting your turn',
      error: "We couldn't scan the site. Check the URL and try again.",
      scoreLabels: { critical: 'Critical', warning: 'Fair', good: 'Good', excellent: 'Excellent', info: 'Info', unknown: '—' },
      previewLeadIn: 'Issues detected', moreIssues: 'and {{n}} more issues',
      gateTitle: 'Your full report is ready', gateSubtitle: 'We will email you the link to the detailed report with the solutions plan.',
      gatePrivacy: 'We only use your data to send you the report. No spam.',
      optional: '(optional)', required: 'required',
      sentTitle: 'Report sent!', sentSubtitle: 'Check your inbox at {{email}}. You can also open it now:',
      errEmail: 'Enter a valid email.', errName: 'Enter your name.', errWhatsapp: 'Enter your WhatsApp.',
      issuesGood: 'No serious issues found. Nice work!'
    }
  };
  var L = t[LANG] || t.es;

  var SEV = { critical: '#EF4444', warning: '#F59E0B', good: '#10B981', excellent: '#059669', info: '#6B7280', unknown: '#94A3B8' };

  // ─── Estilos (una vez por página) ───────────────────────────────────
  // Diseño deliberadamente diferenciado por (theme × style) para que las
  // 6 combinaciones se vean DISTINTAS desde el formulario inicial, no
  // solo desde el preview post-scan.
  //
  // Theme (light/dark) controla texto, bordes y fondo del card.
  // Style (card/gradient/minimal) controla el "marco" del widget:
  //   card     — card sólido con borde + sombra suave.
  //   gradient — card sólido + header pintado con degradado del acento.
  //   minimal — sin marco, inputs con borde inferior, fondo transparente
  //             (la página host provee el fondo).
  //
  // El widget NO impone fondo de página: solo se aplica al card. En
  // minimal el card también es transparente y todo respira sobre el bg
  // que defina la página donde se embeba.
  if (!document.getElementById('ia-w-styles')) {
    var st = document.createElement('style');
    st.id = 'ia-w-styles';
    var isRight = POS !== 'bottom-left';
    st.textContent = [
      // Variables base
      '.ia-root{--ia-accent:' + COLOR + ';--ia-accent-2:color-mix(in srgb,' + COLOR + ' 65%,#000);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;-webkit-font-smoothing:antialiased}',
      '.ia-root *{box-sizing:border-box}',
      // Theme: solo afecta colores propios del widget (texto, bordes,
      // fondo del card). NO toca el fondo de la página.
      '.ia-theme-light{--ia-card:#ffffff;--ia-fg:#0f172a;--ia-muted:#5a6776;--ia-faint:#94a3b8;--ia-border:#e5e9ee;--ia-soft:#f3f5f8;--ia-input-bg:#ffffff;--ia-input-bd:#dde2e8;--ia-track:#eef1f5;--ia-shadow:0 12px 36px -10px rgba(15,23,42,.18),0 4px 12px rgba(15,23,42,.06)}',
      '.ia-theme-dark{--ia-card:#0f172a;--ia-fg:#f1f5f9;--ia-muted:#9aa6b8;--ia-faint:#5e6b80;--ia-border:#1e293b;--ia-soft:#162033;--ia-input-bg:#0b1322;--ia-input-bd:#27334a;--ia-track:#1e293b;--ia-shadow:0 18px 50px -10px rgba(0,0,0,.6),0 4px 16px rgba(0,0,0,.35)}',

      // Floating
      '#ia-w-btn{position:fixed;bottom:22px;' + (isRight ? 'right' : 'left') + ':22px;width:58px;height:58px;border-radius:50%;background:' + COLOR + ';color:#fff;border:none;cursor:pointer;z-index:2147483000;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 22px rgba(0,0,0,.28);transition:transform .2s,box-shadow .2s}',
      '#ia-w-btn:hover{transform:translateY(-2px) scale(1.05);box-shadow:0 10px 30px rgba(0,0,0,.34)}',
      '#ia-w-btn svg{width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:1.6}',
      '#ia-w-pop{position:fixed;bottom:92px;' + (isRight ? 'right' : 'left') + ':22px;width:384px;max-width:calc(100vw - 32px);max-height:min(620px,calc(100vh - 120px));overflow-y:auto;z-index:2147483000;display:none;animation:ia-in .28s cubic-bezier(.2,.8,.2,1)}',
      '#ia-w-pop.ia-show{display:block}',
      '@keyframes ia-in{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}',

      '.ia-inline-root{max-width:540px;margin:0 auto}',

      // ─── Estilo CARD (default): card sólido con borde + sombra ───
      '.ia-style-card .ia-card{background:var(--ia-card);color:var(--ia-fg);border-radius:18px;overflow:hidden;border:1px solid var(--ia-border);box-shadow:var(--ia-shadow)}',
      '.ia-style-card .ia-hd{display:flex;justify-content:space-between;align-items:flex-start;padding:18px 22px 14px;border-bottom:1px solid var(--ia-border);background:var(--ia-card)}',

      // ─── Estilo GRADIENT: card + header pintado con degradado ────
      // El header con gradiente está SIEMPRE visible (forma y resultados),
      // así el admin lo ve desde el formulario inicial.
      '.ia-style-gradient .ia-card{background:var(--ia-card);color:var(--ia-fg);border-radius:18px;overflow:hidden;border:1px solid var(--ia-border);box-shadow:var(--ia-shadow)}',
      '.ia-style-gradient .ia-hd{display:flex;justify-content:space-between;align-items:flex-start;padding:22px 22px 20px;background:linear-gradient(135deg,var(--ia-accent) 0%,var(--ia-accent-2) 100%);color:#fff;border-bottom:none}',
      '.ia-style-gradient .ia-hd h3{color:#fff}',
      '.ia-style-gradient .ia-hd .ia-dom{color:rgba(255,255,255,.85)}',
      '.ia-style-gradient .ia-hd .ia-x{color:rgba(255,255,255,.78)}',
      '.ia-style-gradient .ia-hd .ia-x:hover{color:#fff;background:rgba(255,255,255,.16)}',
      '.ia-style-gradient .ia-bt{background:linear-gradient(135deg,var(--ia-accent) 0%,var(--ia-accent-2) 100%);box-shadow:0 6px 18px -6px color-mix(in srgb,var(--ia-accent) 70%,transparent)}',
      '.ia-style-gradient .ia-sc-wrap{margin:-22px -22px 18px;padding:26px 22px 20px;background:linear-gradient(135deg,var(--ia-accent) 0%,var(--ia-accent-2) 100%);color:#fff}',
      '.ia-style-gradient .ia-sc-wrap .ia-gauge-d{color:rgba(255,255,255,.85)}',
      '.ia-style-gradient .ia-sc-wrap .ia-sev{background:rgba(255,255,255,.22)!important;color:#fff!important}',

      // ─── Estilo MINIMAL: sin marco, transparente, inputs underlined ─
      '.ia-style-minimal .ia-card{background:transparent;color:var(--ia-fg);border:none;box-shadow:none;border-radius:0;overflow:visible}',
      '.ia-style-minimal .ia-hd{display:flex;justify-content:space-between;align-items:flex-start;padding:6px 2px 14px;border-bottom:1px solid var(--ia-border);background:transparent}',
      '.ia-style-minimal .ia-bd{padding:18px 2px 6px}',
      '.ia-style-minimal .ia-in{border:none;border-bottom:1.5px solid var(--ia-input-bd);border-radius:0;padding:11px 0;background:transparent}',
      '.ia-style-minimal .ia-in:focus{border-bottom-color:var(--ia-accent);box-shadow:none}',
      '.ia-style-minimal .ia-bt{border-radius:10px}',
      '.ia-style-minimal .ia-bo{border-radius:10px;border-width:1px}',

      // ─── Cuerpo común ────────────────────────────────────────────
      '.ia-card{color:var(--ia-fg)}',
      '.ia-hd h3{margin:0;font-size:14.5px;font-weight:700;letter-spacing:.2px;line-height:1.2}',
      '.ia-hd .ia-dom{font-size:12.5px;color:var(--ia-muted);font-weight:500;margin-top:3px}',
      '.ia-x{background:none;border:none;cursor:pointer;color:var(--ia-faint);font-size:24px;line-height:1;padding:2px 8px;border-radius:8px}',
      '.ia-x:hover{color:var(--ia-fg);background:var(--ia-soft)}',
      '.ia-bd{padding:22px 22px 24px}',
      '.ia-lead{margin:0 0 16px;font-size:13.5px;color:var(--ia-muted);line-height:1.55}',

      // Inputs (default card/gradient style)
      '.ia-fld{margin-bottom:13px}',
      '.ia-lbl{display:block;font-size:11.5px;font-weight:600;color:var(--ia-muted);margin:0 0 6px;letter-spacing:.3px;text-transform:uppercase}',
      '.ia-lbl .ia-opt{color:var(--ia-faint);font-weight:500;text-transform:none;margin-left:4px}',
      '.ia-req-star{color:#EF4444;margin-left:3px}',
      '.ia-in{width:100%;padding:12px 14px;border:1.5px solid var(--ia-input-bd);border-radius:11px;font-size:14.5px;color:var(--ia-fg);background:var(--ia-input-bg);outline:none;transition:border-color .15s,box-shadow .15s;font-family:inherit}',
      '.ia-in::placeholder{color:var(--ia-faint)}',
      '.ia-in:focus{border-color:var(--ia-accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--ia-accent) 18%,transparent)}',
      '.ia-in.ia-bad{border-color:#EF4444}',

      // Botones
      '.ia-bt{width:100%;padding:14px;border:none;border-radius:12px;background:var(--ia-accent);color:#fff;font-size:15px;font-weight:700;cursor:pointer;transition:filter .15s,transform .08s;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:8px}',
      '.ia-bt:hover{filter:brightness(1.06)}',
      '.ia-bt:active{transform:translateY(1px)}',
      '.ia-bt:disabled{opacity:.55;cursor:not-allowed}',
      '.ia-bt svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2}',
      '.ia-bo{width:100%;padding:12px;border:1.5px solid var(--ia-border);border-radius:12px;background:transparent;color:var(--ia-muted);font-size:13.5px;font-weight:600;cursor:pointer;margin-top:9px;transition:all .15s;font-family:inherit;text-align:center;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:7px}',
      '.ia-bo:hover{border-color:var(--ia-accent);color:var(--ia-accent)}',
      '.ia-bo svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:2}',
      '.ia-wa{background:#25D366;border-color:#25D366;color:#fff}',
      '.ia-wa:hover{filter:brightness(1.05);color:#fff;border-color:#25D366}',

      // Progreso
      '.ia-pg{height:6px;background:var(--ia-track);border-radius:99px;margin:18px 0 12px;overflow:hidden}',
      '.ia-pg-b{height:100%;background:var(--ia-accent);border-radius:99px;width:6%;transition:width .7s cubic-bezier(.4,0,.2,1)}',
      '.ia-pg-lbl{font-size:12.5px;color:var(--ia-muted);text-align:center;display:flex;align-items:center;justify-content:center;gap:8px}',
      '.ia-spin{width:14px;height:14px;border:2px solid var(--ia-track);border-top-color:var(--ia-accent);border-radius:50%;animation:ia-rot .7s linear infinite}',
      '@keyframes ia-rot{to{transform:rotate(360deg)}}',

      // Gauge
      '.ia-gauge{position:relative;width:140px;height:140px;margin:6px auto 6px}',
      '.ia-gauge svg{transform:rotate(-90deg)}',
      '.ia-gauge-ring{transition:stroke-dashoffset 1s cubic-bezier(.4,0,.2,1)}',
      '.ia-gauge-ctr{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}',
      '.ia-gauge-n{font-size:40px;font-weight:800;line-height:1;letter-spacing:-1.2px}',
      '.ia-gauge-d{font-size:12px;color:var(--ia-faint);font-weight:600;margin-top:2px}',
      '.ia-sev{display:inline-block;margin:10px auto 0;padding:5px 13px;border-radius:99px;font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.6px}',
      '.ia-sc-wrap{text-align:center}',

      // Lista de problemas
      '.ia-is-t{font-size:12px;font-weight:700;color:var(--ia-muted);text-transform:uppercase;letter-spacing:.6px;margin:20px 0 8px}',
      '.ia-is{list-style:none;padding:0;margin:0 0 4px}',
      '.ia-is li{font-size:13.5px;color:var(--ia-fg);padding:10px 0;border-bottom:1px solid var(--ia-border);display:flex;gap:10px;align-items:center}',
      '.ia-is li:last-child{border-bottom:none}',
      '.ia-dot{width:9px;height:9px;border-radius:50%;flex:none}',
      '.ia-is-more{color:var(--ia-faint)!important;font-style:italic}',

      // Gate
      '.ia-gate{margin-top:22px;padding-top:20px;border-top:1px solid var(--ia-border)}',
      '.ia-gate h4{margin:0 0 6px;font-size:17px;font-weight:700;text-align:center;color:var(--ia-fg)}',
      '.ia-gate-sub{margin:0 0 16px;font-size:13px;color:var(--ia-muted);text-align:center;line-height:1.5}',
      '.ia-priv{display:block;text-align:center;margin-top:11px;font-size:11px;color:var(--ia-faint)}',
      '.ia-err{color:#EF4444;font-size:12.5px;margin:8px 0 0;text-align:center}',

      // Enviado
      '.ia-sent{text-align:center;padding:6px 0 2px}',
      '.ia-sent-ico{width:60px;height:60px;margin:0 auto 14px;border-radius:50%;background:color-mix(in srgb,#10B981 16%,transparent);display:flex;align-items:center;justify-content:center;color:#10B981}',
      '.ia-sent-ico svg{width:30px;height:30px;fill:none;stroke:currentColor;stroke-width:2.5}',
      '.ia-sent h4{margin:0 0 6px;font-size:18px;font-weight:700}',
      '.ia-sent p{margin:0 0 18px;font-size:13.5px;color:var(--ia-muted);line-height:1.5}'
    ].join('\n');
    document.head.appendChild(st);
  }

  // ─── Utils ───────────────────────────────────────────────────────────
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function tpl(s, v) { return s.replace(/\{\{(\w+)\}\}/g, function (_, k) { return v[k] != null ? v[k] : ''; }); }
  function rootClass() { return 'ia-root ia-theme-' + (THEME === 'dark' ? 'dark' : 'light') + ' ia-style-' + (['card', 'gradient', 'minimal'].indexOf(STYLE) >= 0 ? STYLE : 'card'); }

  var ICON = {
    shield: '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
    lock: '<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
    arrow: '<svg viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>',
    refresh: '<svg viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/></svg>',
    chat: '<svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
    check: '<svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>'
  };

  function fireConversion(domain, score) {
    try { if (GTM_EVENT && window.dataLayer) window.dataLayer.push({ event: GTM_EVENT, imagina_audit_domain: domain, imagina_audit_score: score }); } catch (e) {}
    try { if (GTAG_CONV && typeof window.gtag === 'function') window.gtag('event', 'conversion', { send_to: GTAG_CONV, value: 1.0, currency: 'USD' }); } catch (e) {}
  }

  function pollProgress(auditId, onUpdate, onDone, onFail) {
    var deadline = Date.now() + 15 * 60 * 1000, INTERVAL = 1500, stopped = false;
    function tick() {
      if (stopped) return;
      if (Date.now() > deadline) return onFail(L.error);
      fetch(API + '/scan-progress.php?id=' + encodeURIComponent(auditId))
        .then(function (r) { return r.status === 404 ? null : r.json(); })
        .then(function (json) {
          if (!json || !json.success || !json.data) { setTimeout(tick, INTERVAL); return; }
          var d = json.data;
          if (d.status === 'completed') {
            fetch(API + '/audit-status.php?id=' + encodeURIComponent(auditId))
              .then(function (r) { return r.json(); })
              .then(function (j2) { (j2.success && j2.data) ? onDone(j2.data) : onFail(L.error); })
              .catch(function () { onFail(L.error); });
            return;
          }
          if (d.status === 'failed') return onFail(d.error || L.error);
          onUpdate(d); setTimeout(tick, INTERVAL);
        })
        .catch(function () { setTimeout(tick, INTERVAL); });
    }
    tick();
    return { stop: function () { stopped = true; } };
  }

  // ─── App (renderer compartido floating/inline) ──────────────────────
  function makeApp(host, opts) {
    opts = opts || {};
    var auditId = null, auditResult = null;

    // host es el contenedor externo; dentro montamos una .ia-card.
    // Usamos classList.add (no host.className=) para NO pisar clases que
    // el caller ya puso en el host — p.ej. el popup flotante lleva
    // 'ia-show' y sobrescribir la className lo dejaba en display:none.
    var hostClasses = (rootClass() + (MODE === 'inline' ? ' ia-inline-root' : '')).split(' ');
    hostClasses.forEach(function (c) { if (c) host.classList.add(c); });
    var card = document.createElement('div');
    card.className = 'ia-card';
    host.appendChild(card);

    function head(title, dom, closable) {
      var x = (closable && opts.onClose) ? '<button class="ia-x" data-act="close" aria-label="cerrar">&times;</button>' : '';
      var sub = dom ? '<div class="ia-dom">' + esc(dom) + '</div>' : '';
      return '<div class="ia-hd"><div><h3>' + esc(title) + '</h3>' + sub + '</div>' + x + '</div>';
    }

    function renderForm() {
      var def = MODE === 'floating' ? location.origin : '';
      card.innerHTML =
        head(L.title, '', true) +
        '<div class="ia-bd">' +
          '<p class="ia-lead">' + esc(L.subtitle) + '</p>' +
          '<div class="ia-fld"><input class="ia-in" data-act="url" inputmode="url" placeholder="' + esc(cfgPlaceholder('placeholderUrl', L.urlPh)) + '" value="' + esc(def) + '"></div>' +
          '<button class="ia-bt" data-act="go">' + ICON.shield + esc(L.btnScan) + '</button>' +
        '</div>';
      var inp = card.querySelector('[data-act="url"]'); if (inp && MODE === 'inline') inp.focus();
    }

    function renderScanning(p) {
      var pct = p && p.progress ? Math.max(6, Math.min(99, p.progress)) : 6;
      var lbl = p && p.status === 'queued'
        ? L.queued + (p.position ? ' (' + p.position + ')' : '')
        : ((p && p.currentLabel) || L.scanning);
      card.innerHTML =
        head(L.scanning, '', false) +
        '<div class="ia-bd">' +
          '<div class="ia-pg"><div class="ia-pg-b" style="width:' + pct + '%"></div></div>' +
          '<div class="ia-pg-lbl"><span class="ia-spin"></span>' + esc(lbl) + '</div>' +
        '</div>';
    }

    function gauge(score, sev) {
      var C = 327, off = C * (1 - Math.max(0, Math.min(100, score)) / 100);
      return '<div class="ia-gauge">' +
        '<svg width="132" height="132" viewBox="0 0 120 120">' +
          '<circle cx="60" cy="60" r="52" fill="none" stroke="var(--ia-track)" stroke-width="11"/>' +
          '<circle class="ia-gauge-ring" cx="60" cy="60" r="52" fill="none" stroke="' + sev + '" stroke-width="11" stroke-linecap="round" stroke-dasharray="' + C + '" stroke-dashoffset="' + C + '"/>' +
        '</svg>' +
        '<div class="ia-gauge-ctr"><div class="ia-gauge-n" style="color:' + sev + '">' + score + '</div><div class="ia-gauge-d">/ 100</div></div>' +
      '</div>';
    }

    function renderPreview(data) {
      auditResult = data;
      var sev = SEV[data.globalLevel] || SEV.unknown;
      var lbl = L.scoreLabels[data.globalLevel] || data.globalLevel;

      var bad = [];
      (data.modules || []).forEach(function (m) {
        (m.metrics || []).forEach(function (mt) {
          if (mt.level === 'critical' || mt.level === 'warning') bad.push({ level: mt.level, name: mt.name });
        });
      });
      bad.sort(function (a, b) { return (a.level === 'critical' ? 0 : 1) - (b.level === 'critical' ? 0 : 1); });
      var shown = bad.slice(0, 5), rest = bad.length - shown.length;

      var issues = '';
      if (shown.length) {
        issues = '<div class="ia-is-t">' + esc(L.previewLeadIn) + '</div><ul class="ia-is">' +
          shown.map(function (i) {
            return '<li><span class="ia-dot" style="background:' + SEV[i.level] + '"></span>' + esc(i.name) + '</li>';
          }).join('') +
          (rest > 0 ? '<li class="ia-is-more"><span class="ia-dot" style="background:var(--ia-faint)"></span>' + tpl(L.moreIssues, { n: rest }) + '</li>' : '') +
          '</ul>';
      } else {
        issues = '<p class="ia-lead" style="margin-top:14px">' + esc(L.issuesGood) + '</p>';
      }

      var scWrap = '<div class="ia-sc-wrap"' + (STYLE === 'gradient' ? ' style="background:linear-gradient(135deg,' + sev + ',color-mix(in srgb,' + sev + ' 60%,#000))"' : '') + '>' +
        gauge(data.globalScore, STYLE === 'gradient' ? '#fff' : sev) +
        '<span class="ia-sev" style="background:color-mix(in srgb,' + sev + ' 15%,transparent);color:' + sev + '">' + esc(lbl) + '</span>' +
      '</div>';

      var r = gateReqs();
      var fields = '';
      // Email: lo mostramos siempre (es el canal del informe).
      fields += field('email', 'email', L.emailPh, r.email);
      // Nombre: lo mostramos siempre (baja fricción); requerido según config.
      fields += field('name', 'text', L.namePh, r.name);
      // WhatsApp: solo si el admin lo pide (evita un campo de más).
      if (r.whatsapp) fields += field('whatsapp', 'tel', L.whatsappPh, true);

      card.innerHTML =
        head(L.title, data.domain, true) +
        '<div class="ia-bd">' +
          scWrap +
          issues +
          '<div class="ia-gate">' +
            '<h4>' + esc(L.gateTitle) + '</h4>' +
            '<p class="ia-gate-sub">' + esc(L.gateSubtitle) + '</p>' +
            fields +
            '<button class="ia-bt" data-act="capture">' + ICON.lock + esc(L.btnUnlock) + '</button>' +
            '<span class="ia-priv">' + esc(L.gatePrivacy) + '</span>' +
          '</div>' +
        '</div>';

      // animar gauge
      requestAnimationFrame(function () {
        var ring = card.querySelector('.ia-gauge-ring');
        if (ring) { var C = 327; ring.setAttribute('stroke-dashoffset', String(C * (1 - Math.max(0, Math.min(100, data.globalScore)) / 100))); }
      });
    }

    function field(key, type, ph, required) {
      var labelMap = { email: 'Email', name: L.namePh, whatsapp: 'WhatsApp' };
      var lbl = labelMap[key] || key;
      var mark = required
        ? '<span class="ia-req-star">*</span>'
        : ' <span class="ia-opt">' + esc(L.optional) + '</span>';
      var phr = key === 'name' ? cfgPlaceholder('placeholderName', ph)
        : key === 'email' ? cfgPlaceholder('placeholderEmail', ph)
        : key === 'whatsapp' ? cfgPlaceholder('placeholderWhatsapp', ph) : ph;
      return '<div class="ia-fld">' +
        '<label class="ia-lbl">' + esc(lbl) + mark + '</label>' +
        '<input class="ia-in" data-fld="' + key + '" type="' + type + '" placeholder="' + esc(phr) + '"' + (key === 'whatsapp' ? ' inputmode="tel"' : '') + '>' +
      '</div>';
    }

    function renderSent(email) {
      var baseUrl = API.replace(/\/api\/?$/, '');
      var fullUrl = baseUrl + '/results/' + auditId;
      var wa = '';
      if (WHATSAPP && auditResult) {
        var msg = (LANG === 'en'
          ? 'Hi! I just audited ' + auditResult.domain + ' and scored ' + auditResult.globalScore + '/100. I want to know how to improve it.'
          : '¡Hola! Acabo de auditar ' + auditResult.domain + ' y saqué ' + auditResult.globalScore + '/100. Quiero saber cómo mejorarlo.');
        wa = '<a href="https://wa.me/' + WHATSAPP.replace(/[^0-9]/g, '') + '?text=' + encodeURIComponent(msg) + '" target="_blank" rel="noopener" class="ia-bo ia-wa">' + ICON.chat + esc(L.btnContact) + '</a>';
      }
      card.innerHTML =
        head(L.sentTitle, '', true) +
        '<div class="ia-bd"><div class="ia-sent">' +
          '<div class="ia-sent-ico">' + ICON.check + '</div>' +
          '<h4>' + esc(L.sentTitle) + '</h4>' +
          '<p>' + tpl(L.sentSubtitle, { email: '<b>' + esc(email) + '</b>' }) + '</p>' +
          '<a href="' + fullUrl + '" target="_blank" rel="noopener" class="ia-bt">' + esc(L.btnFull) + ICON.arrow + '</a>' +
          wa +
          '<button class="ia-bo" data-act="new">' + ICON.refresh + esc(L.btnNew) + '</button>' +
        '</div></div>';
    }

    function renderError(msg) {
      card.innerHTML =
        head('Error', '', true) +
        '<div class="ia-bd"><p class="ia-err" style="margin-bottom:14px">' + esc(msg) + '</p>' +
        '<button class="ia-bt" data-act="retry">' + ICON.refresh + esc(L.retry) + '</button></div>';
    }

    // Eventos
    card.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-act]'); if (!b) return;
      var a = b.getAttribute('data-act');
      if (a === 'close') return opts.onClose && opts.onClose();
      if (a === 'go') return doScan();
      if (a === 'capture') return doCapture();
      if (a === 'new' || a === 'retry') return renderForm();
    });
    card.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Enter') return;
      var a = ev.target && ev.target.getAttribute('data-act');
      var f = ev.target && ev.target.getAttribute('data-fld');
      if (a === 'url') doScan();
      else if (f) doCapture();
    });

    function doScan() {
      var el = card.querySelector('[data-act="url"]');
      var url = el ? el.value.trim() : '';
      if (!url) { el && el.focus(); return; }
      if (!/^https?:\/\//i.test(url)) url = 'https://' + url;
      renderScanning(null);
      fetch(API + '/audit.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url: url, lang: LANG, _source: 'embed' })
      })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json.success || !json.data) return renderError(json.error || L.error);
          var d = json.data;
          if (d.cached && d.result) return renderPreview(d.result);
          auditId = d.auditId;
          pollProgress(auditId, renderScanning, renderPreview, renderError);
        })
        .catch(function () { renderError(L.error); });
    }

    function fieldEl(k) { return card.querySelector('[data-fld="' + k + '"]'); }
    function showErr(msg, focusEl) {
      var old = card.querySelector('.ia-gate .ia-err'); if (old) old.remove();
      card.querySelectorAll('.ia-in.ia-bad').forEach(function (n) { n.classList.remove('ia-bad'); });
      var p = document.createElement('p'); p.className = 'ia-err'; p.textContent = msg;
      var gate = card.querySelector('.ia-gate'); if (gate) gate.appendChild(p);
      if (focusEl) { focusEl.classList.add('ia-bad'); focusEl.focus(); }
    }

    function doCapture() {
      if (!auditId) return;
      var r = gateReqs();
      var emailEl = fieldEl('email'), nameEl = fieldEl('name'), waEl = fieldEl('whatsapp');
      var email = emailEl ? emailEl.value.trim() : '';
      var name = nameEl ? nameEl.value.trim() : '';
      var wa = waEl ? waEl.value.trim() : '';

      // Validación cliente espejo del backend.
      if ((r.email || email) && email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return showErr(L.errEmail, emailEl);
      if (r.email && !email) return showErr(L.errEmail, emailEl);
      if (r.name && !name) return showErr(L.errName, nameEl);
      if (r.whatsapp && !wa) return showErr(L.errWhatsapp, waEl);

      var btn = card.querySelector('[data-act="capture"]');
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="ia-spin" style="border-top-color:#fff;border-color:rgba(255,255,255,.4);border-top-color:#fff"></span>'; }

      fetch(API + '/capture-lead.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ auditId: auditId, leadEmail: email || undefined, leadName: name || undefined, leadWhatsapp: wa || undefined })
      })
        .then(function (r2) { return r2.json(); })
        .then(function (json) {
          if (json.success) {
            fireConversion(auditResult ? auditResult.domain : '', auditResult ? auditResult.globalScore : 0);
            renderSent(email || wa);
          } else {
            if (btn) { btn.disabled = false; btn.innerHTML = ICON.lock + esc(L.btnUnlock); }
            showErr(json.error || L.error, null);
          }
        })
        .catch(function () {
          if (btn) { btn.disabled = false; btn.innerHTML = ICON.lock + esc(L.btnUnlock); }
          showErr(L.error, null);
        });
    }

    // Si el gate ya estaba renderizado cuando llegó la config tardía,
    // re-renderizamos el preview con los campos correctos (fix race
    // condition que escondía WhatsApp).
    function maybeRerenderGate() {
      if (auditResult && card.querySelector('.ia-gate')) {
        renderPreview(auditResult);
      }
    }

    return { renderForm: renderForm, renderPreview: renderPreview, maybeRerenderGate: maybeRerenderGate };
  }

  // ─── Floating ────────────────────────────────────────────────────────
  function initFloating() {
    var btn = document.createElement('button');
    btn.id = 'ia-w-btn'; btn.type = 'button'; btn.title = L.title;
    btn.innerHTML = ICON.shield;
    document.body.appendChild(btn);

    var pop = document.createElement('div');
    pop.id = 'ia-w-pop';
    document.body.appendChild(pop);

    var app = makeApp(pop, { onClose: function () { pop.classList.remove('ia-show'); } });
    onConfigReady = app.maybeRerenderGate;
    btn.onclick = function () {
      if (pop.classList.contains('ia-show')) { pop.classList.remove('ia-show'); return; }
      app.renderForm(); pop.classList.add('ia-show');
    };
  }

  // ─── Inline ──────────────────────────────────────────────────────────
  function initInline() {
    var target = document.querySelector(TARGET);
    if (!target) { console.error('[ImaginaAudit] no se encontró el contenedor', TARGET); return; }
    var app = makeApp(target, {});
    onConfigReady = app.maybeRerenderGate;
    app.renderForm();
  }

  // ─── Datos de DEMO para el preview de /admin/embed ───────────────────
  // Resultado ficticio realista (mismas formas que un AuditResult real)
  // para que el admin vea el bloque completo sin tener que escanear.
  function demoResult() {
    var issues_es = [
      'Headers de seguridad ausentes',
      'Versión de WordPress desactualizada',
      'Plugin Elementor desactualizado',
      'XML-RPC habilitado',
      'Imágenes sin texto alternativo',
      'Falta meta description',
      'Sin compresión Gzip/Brotli',
      'SSL próximo a vencer',
      'Login expuesto sin protección',
      'Falta sitemap.xml'
    ];
    var issues_en = [
      'Missing security headers',
      'WordPress version outdated',
      'Elementor plugin outdated',
      'XML-RPC enabled',
      'Images without alt text',
      'Missing meta description',
      'No Gzip/Brotli compression',
      'SSL expiring soon',
      'Exposed login',
      'Missing sitemap.xml'
    ];
    var names = LANG === 'en' ? issues_en : issues_es;
    var levels = ['critical', 'critical', 'critical', 'warning', 'warning', 'warning', 'critical', 'warning', 'critical', 'warning'];
    var metrics = names.map(function (n, i) { return { level: levels[i], name: n }; });
    return {
      id: 'demo',
      domain: LANG === 'en' ? 'your-site.com' : 'tu-sitio.com',
      url: 'https://' + (LANG === 'en' ? 'your-site.com' : 'tu-sitio.com'),
      globalScore: 51,
      globalLevel: 'warning',
      modules: [{ metrics: metrics }]
    };
  }

  // Para floating + demo: crea el botón flotante Y el popup abierto, para
  // que el admin vea la experiencia completa de la burbuja.
  function initFloatingDemo(initialState) {
    var btn = document.createElement('button');
    btn.id = 'ia-w-btn'; btn.type = 'button'; btn.title = L.title; btn.innerHTML = ICON.shield;
    document.body.appendChild(btn);
    var pop = document.createElement('div');
    pop.id = 'ia-w-pop'; pop.classList.add('ia-show');
    document.body.appendChild(pop);
    var app = makeApp(pop, { onClose: function () { pop.classList.remove('ia-show'); } });
    btn.onclick = function () { pop.classList.toggle('ia-show'); };
    if (initialState === 'form') app.renderForm(); else app.renderPreview(demoResult());
    onConfigReady = app.maybeRerenderGate;
  }
  function initInlineDemo(initialState) {
    var target = document.querySelector(TARGET);
    if (!target) { console.error('[ImaginaAudit] no se encontró el contenedor', TARGET); return; }
    var app = makeApp(target, {});
    if (initialState === 'form') app.renderForm(); else app.renderPreview(demoResult());
    onConfigReady = app.maybeRerenderGate;
  }

  function boot() {
    if (IS_DEMO) {
      return MODE === 'inline' ? initInlineDemo(DEMO_STATE) : initFloatingDemo(DEMO_STATE);
    }
    MODE === 'inline' ? initInline() : initFloating();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
