/**
 * Imagina Audit — Widget Embebible v2
 *
 * Dos modos:
 *
 * 1) FLOATING (burbuja flotante abajo-derecha/izquierda, comportamiento histórico):
 *    <script
 *      src="https://audit.tusitio.com/widget/imagina-audit-widget.js"
 *      data-api="https://audit.tusitio.com/api"
 *      data-mode="floating"
 *      data-color="#0CC0DF"
 *      data-position="bottom-right"
 *      data-lang="es"
 *      data-whatsapp="+57...">
 *    </script>
 *
 * 2) INLINE (bloque embebido dentro del contenido — ideal para landing pages
 *    de Google Ads, mini-funnel self-service):
 *    <script
 *      src="https://audit.tusitio.com/widget/imagina-audit-widget.js"
 *      data-api="https://audit.tusitio.com/api"
 *      data-mode="inline"
 *      data-target="#imagina-audit-block"
 *      data-color="#0CC0DF"
 *      data-lang="es"
 *      data-gtag-conversion="AW-XXXXXXXXX/abc123"
 *      data-gtm-event="imagina_audit_lead">
 *    </script>
 *    <div id="imagina-audit-block"></div>
 *
 *    Estados del bloque inline:
 *      1. Form: URL del sitio + botón "Auditar gratis"
 *      2. Escaneando: barra de progreso real (polling al backend)
 *      3. Adelanto: score grande + 3-5 problemas top + form "Recibir informe
 *         completo por email" — captura el lead AQUÍ.
 *      4. Desbloqueo: link al informe completo + WhatsApp + "Escanear otro"
 *
 *    Conversiones de Google Ads:
 *      - data-gtm-event="X" → dispara dataLayer.push({event: X, ...}) al capturar.
 *      - data-gtag-conversion="AW-XXX/abc" → dispara gtag('event','conversion',
 *        {send_to: 'AW-XXX/abc'}) al capturar.
 *      Ambos opcionales y compatibles. La página host debe tener cargado
 *      gtag.js o GTM (Imagina no lo carga para no duplicar dependencias).
 */
(function () {
  'use strict';

  // Buscar el script tag de forma robusta (currentScript puede ser null con async/defer)
  var scripts = document.querySelectorAll('script[data-api]');
  var script = document.currentScript;
  if (!script || !script.getAttribute('data-api')) {
    for (var i = 0; i < scripts.length; i++) {
      if (scripts[i].src && scripts[i].src.indexOf('imagina-audit-widget') !== -1) {
        script = scripts[i];
        break;
      }
    }
  }
  if (!script) { console.error('Imagina Audit Widget: no se encontró el script tag'); return; }

  var API = script.getAttribute('data-api') || '';
  var MODE = (script.getAttribute('data-mode') || 'floating').toLowerCase();
  var TARGET = script.getAttribute('data-target') || '#imagina-audit-block';
  var COLOR = script.getAttribute('data-color') || '#0CC0DF';
  var POS = script.getAttribute('data-position') || 'bottom-right';
  var LANG = script.getAttribute('data-lang') || 'es';
  var WHATSAPP = script.getAttribute('data-whatsapp') || '';
  var GTM_EVENT = script.getAttribute('data-gtm-event') || '';
  var GTAG_CONV = script.getAttribute('data-gtag-conversion') || '';

  if (!API) { console.error('Imagina Audit Widget: data-api es obligatorio'); return; }
  API = API.replace(/\/+$/, '');

  // ─── i18n mínima ────────────────────────────────────────────────────
  var t = {
    es: {
      title: 'Auditoría Web Gratuita',
      subtitle: 'Descubre el estado de tu sitio en 30 segundos',
      urlPh: 'https://ejemplo.com',
      emailPh: 'tu@email.com',
      namePh: 'Tu nombre (opcional)',
      btnScan: 'Auditar gratis',
      btnUnlock: 'Desbloquear informe completo',
      btnNew: 'Escanear otro sitio',
      btnFull: 'Ver informe completo →',
      btnContact: 'Hablar con un experto',
      close: 'Cerrar',
      error: 'Error al analizar el sitio',
      retry: 'Reintentar',
      scanning: 'Analizando…',
      queued: 'En cola — esperando turno',
      scoreLabels: { critical: 'Crítico', warning: 'Regular', good: 'Bueno', excellent: 'Excelente' },
      previewTitle: 'Tu sitio sacó',
      previewLeadIn: 'Estos son los problemas detectados:',
      gateTitle: 'Tu informe completo está listo',
      gateSubtitle: 'Te enviamos un correo con el link al informe detallado + plan de soluciones.',
      gatePrivacy: 'Solo usamos tu email para enviarte el informe.',
      sentTitle: '¡Informe enviado!',
      sentSubtitle: 'Revisa tu bandeja de entrada en {{email}}. También puedes verlo ahora:',
      moreIssues: 'y {{n}} más…'
    },
    en: {
      title: 'Free Website Audit',
      subtitle: 'Discover your site health in 30 seconds',
      urlPh: 'https://example.com',
      emailPh: 'you@email.com',
      namePh: 'Your name (optional)',
      btnScan: 'Audit free',
      btnUnlock: 'Unlock full report',
      btnNew: 'Scan another site',
      btnFull: 'View full report →',
      btnContact: 'Talk to an expert',
      close: 'Close',
      error: 'Error scanning the site',
      retry: 'Retry',
      scanning: 'Scanning…',
      queued: 'Queued — waiting your turn',
      scoreLabels: { critical: 'Critical', warning: 'Fair', good: 'Good', excellent: 'Excellent' },
      previewTitle: 'Your site scored',
      previewLeadIn: 'Top issues detected:',
      gateTitle: 'Your full report is ready',
      gateSubtitle: 'We will email you the link to the detailed report + solutions plan.',
      gatePrivacy: 'We only use your email to send you the report.',
      sentTitle: 'Report sent!',
      sentSubtitle: 'Check your inbox at {{email}}. You can also view it now:',
      moreIssues: 'and {{n}} more…'
    }
  };
  var L = t[LANG] || t.es;

  // ─── Estilos compartidos (floating + inline) ────────────────────────
  // Los inyectamos una sola vez por carga de página.
  if (!document.getElementById('ia-w-styles')) {
    var css = document.createElement('style');
    css.id = 'ia-w-styles';
    var isRight = POS === 'bottom-right';
    css.textContent = [
      // Floating
      '#ia-w-btn{position:fixed;bottom:20px;' + (isRight ? 'right' : 'left') + ':20px;width:56px;height:56px;border-radius:50%;background:' + COLOR + ';color:#fff;border:none;cursor:pointer;z-index:99999;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,0,0,.25);transition:transform .2s,box-shadow .2s;font-family:sans-serif}',
      '#ia-w-btn:hover{transform:scale(1.08);box-shadow:0 6px 24px rgba(0,0,0,.3)}',
      '#ia-w-btn svg{width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:1.5}',
      '#ia-w-pop{position:fixed;bottom:86px;' + (isRight ? 'right' : 'left') + ':20px;width:370px;max-width:calc(100vw - 40px);max-height:520px;overflow-y:auto;background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,.18);z-index:99999;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:none;animation:ia-slide .25s ease}',
      '#ia-w-pop.ia-show{display:block}',
      '@keyframes ia-slide{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}',
      // Inline contenedor responsivo
      '.ia-inline-root{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#0f172a;max-width:560px;margin:0 auto;background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.06);overflow:hidden}',
      '.ia-inline-root *{box-sizing:border-box}',
      // Compartidos
      '.ia-hd{display:flex;justify-content:space-between;align-items:center;padding:16px 20px 12px;border-bottom:1px solid #f1f5f9}',
      '.ia-hd h3{margin:0;font-size:15px;font-weight:700;color:#0f172a}',
      '.ia-x{background:none;border:none;cursor:pointer;color:#94a3b8;font-size:22px;padding:4px;line-height:1}',
      '.ia-x:hover{color:#64748b}',
      '.ia-bd{padding:18px 20px 20px}',
      '.ia-bd p{margin:0 0 14px;font-size:13.5px;color:#475569;line-height:1.55}',
      '.ia-in{width:100%;padding:11px 13px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;color:#0f172a;outline:none;margin-bottom:10px;transition:border .2s;font-family:inherit;background:#fff}',
      '.ia-in:focus{border-color:' + COLOR + '}',
      '.ia-bt{width:100%;padding:13px;border:none;border-radius:10px;background:' + COLOR + ';color:#fff;font-size:14.5px;font-weight:600;cursor:pointer;transition:opacity .2s,transform .1s;font-family:inherit}',
      '.ia-bt:hover{opacity:.92}',
      '.ia-bt:active{transform:translateY(1px)}',
      '.ia-bt:disabled{opacity:.5;cursor:not-allowed}',
      '.ia-pg{height:4px;background:#f1f5f9;border-radius:2px;margin:16px 0;overflow:hidden}',
      '.ia-pg-b{height:100%;background:' + COLOR + ';border-radius:2px;width:0%;transition:width .8s linear}',
      '.ia-sc{text-align:center;padding:10px 0}',
      '.ia-sc-n{font-size:56px;font-weight:800;line-height:1.05;letter-spacing:-1px}',
      '.ia-sc-l{font-size:13px;color:#64748b;margin-top:6px;font-weight:500;text-transform:uppercase;letter-spacing:.5px}',
      '.ia-is{list-style:none;padding:0;margin:18px 0}',
      '.ia-is li{font-size:13px;color:#475569;padding:8px 0;border-bottom:1px solid #f8fafc;display:flex;gap:8px;align-items:center}',
      '.ia-bo{width:100%;padding:11px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#475569;font-size:13.5px;font-weight:500;cursor:pointer;margin-top:8px;transition:all .2s;font-family:inherit;text-align:center;text-decoration:none;display:block}',
      '.ia-bo:hover{border-color:' + COLOR + ';color:' + COLOR + '}',
      '.ia-lk{display:block;text-align:center;margin-top:14px;font-size:11.5px;color:#94a3b8;cursor:pointer;text-decoration:none;background:none;border:none;font-family:inherit}',
      '.ia-lk:hover{color:#64748b}',
      '.ia-priv{display:block;text-align:center;margin-top:10px;font-size:11px;color:#94a3b8}',
      '.ia-gate-hd{text-align:center;padding:8px 0 16px}',
      '.ia-gate-hd h4{margin:0 0 6px;font-size:18px;font-weight:700;color:#0f172a}',
      '.ia-gate-hd p{margin:0;font-size:13.5px;color:#64748b}',
      '.ia-sent{text-align:center;padding:8px 0}',
      '.ia-sent-ico{width:56px;height:56px;margin:0 auto 12px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;color:#16a34a;font-size:28px}',
      '.ia-err{color:#ef4444;font-size:13px;margin:8px 0 0;text-align:center}'
    ].join('\n');
    document.head.appendChild(css);
  }

  // ─── Utilidades ─────────────────────────────────────────────────────
  function tpl(s, vars) {
    return s.replace(/\{\{(\w+)\}\}/g, function (_, k) { return vars[k] != null ? vars[k] : ''; });
  }

  /**
   * Dispara los eventos de conversión configurados. Llamado UNA vez al
   * capturar el lead (el momento de la conversión real, no al iniciar
   * el scan). Compatible con GTM (dataLayer) y gtag.js directo.
   */
  function fireConversion(domain, score) {
    var payload = { domain: domain, score: score };
    try {
      if (GTM_EVENT && window.dataLayer) {
        window.dataLayer.push({
          event: GTM_EVENT,
          imagina_audit_domain: domain,
          imagina_audit_score: score
        });
      }
    } catch (e) { /* GTM no disponible */ }
    try {
      if (GTAG_CONV && typeof window.gtag === 'function') {
        window.gtag('event', 'conversion', {
          send_to: GTAG_CONV,
          value: 1.0,
          currency: 'USD'
        });
      }
    } catch (e) { /* gtag no disponible */ }
    return payload;
  }

  function pollProgress(auditId, onUpdate, onDone, onFail) {
    var deadline = Date.now() + 15 * 60 * 1000; // 15min
    var INTERVAL = 1500;
    var stopped = false;
    function tick() {
      if (stopped || Date.now() > deadline) {
        if (!stopped) onFail(L.error);
        return;
      }
      fetch(API + '/scan-progress.php?id=' + encodeURIComponent(auditId))
        .then(function (r) {
          if (r.status === 404) return null; // aún no se escribió, reintentar
          return r.json();
        })
        .then(function (json) {
          if (!json) { setTimeout(tick, INTERVAL); return; }
          if (!json.success || !json.data) { setTimeout(tick, INTERVAL); return; }
          var d = json.data;
          if (d.status === 'completed') {
            // Pedir el resultado final
            fetch(API + '/audit-status.php?id=' + encodeURIComponent(auditId))
              .then(function (r) { return r.json(); })
              .then(function (j2) {
                if (j2.success && j2.data) onDone(j2.data);
                else onFail(L.error);
              })
              .catch(function () { onFail(L.error); });
            return;
          }
          if (d.status === 'failed') {
            onFail(d.error || L.error);
            return;
          }
          onUpdate(d);
          setTimeout(tick, INTERVAL);
        })
        .catch(function () { setTimeout(tick, INTERVAL); });
    }
    tick();
    return { stop: function () { stopped = true; } };
  }

  // ─── Renderer compartido ────────────────────────────────────────────
  // Cada modo le pasa un contenedor donde inyectar el HTML del estado actual.
  function makeApp(container, opts) {
    opts = opts || {};
    var auditId = null;
    var auditResult = null;
    var polling = null;

    function header(title, closable) {
      var x = closable && opts.onClose
        ? '<button class="ia-x" data-act="close">&times;</button>'
        : '';
      return '<div class="ia-hd"><h3>' + title + '</h3>' + x + '</div>';
    }

    function renderForm() {
      var defaultUrl = '';
      if (MODE === 'floating') {
        // En floating asumimos que el usuario quiere auditar el sitio en
        // el que está navegando — pre-llenamos location.origin.
        defaultUrl = location.origin;
      }
      container.innerHTML =
        header(L.title, true) +
        '<div class="ia-bd">' +
          '<p>' + L.subtitle + '</p>' +
          '<input class="ia-in" data-act="url" placeholder="' + L.urlPh + '" value="' + defaultUrl + '">' +
          '<button class="ia-bt" data-act="go">' + L.btnScan + '</button>' +
        '</div>';
    }

    function renderScanning(progress) {
      var pct = progress && progress.progress ? Math.max(5, progress.progress) : 5;
      var label = progress && progress.status === 'queued'
        ? L.queued + (progress.position ? ' (' + progress.position + ')' : '')
        : (progress && progress.currentLabel) || L.scanning;
      container.innerHTML =
        header(L.scanning, false) +
        '<div class="ia-bd">' +
          '<p>' + label + '</p>' +
          '<div class="ia-pg"><div class="ia-pg-b" style="width:' + pct + '%"></div></div>' +
        '</div>';
    }

    function renderPreview(data) {
      auditResult = data;
      var sc = { critical: '#EF4444', warning: '#F59E0B', good: '#10B981', excellent: '#059669' }[data.globalLevel] || '#64748B';
      var lb = L.scoreLabels[data.globalLevel] || data.globalLevel;
      // Recolectar top problemas críticos/warning, limitados a 5 visibles
      var bad = [];
      (data.modules || []).forEach(function (m) {
        (m.metrics || []).forEach(function (mt) {
          if (mt.level === 'critical' || mt.level === 'warning') {
            bad.push({ level: mt.level, name: mt.name });
          }
        });
      });
      bad.sort(function (a, b) {
        return a.level === 'critical' && b.level !== 'critical' ? -1 : 1;
      });
      var shown = bad.slice(0, 5);
      var rest = bad.length - shown.length;
      var issuesHtml = '';
      if (shown.length) {
        issuesHtml = '<p style="margin-top:14px;font-weight:600;color:#334155">' + L.previewLeadIn + '</p>' +
          '<ul class="ia-is">' +
          shown.map(function (i) {
            return '<li>' + (i.level === 'critical' ? '🔴' : '🟡') + ' ' + i.name + '</li>';
          }).join('') +
          (rest > 0 ? '<li style="color:#94a3b8;font-style:italic">' + tpl(L.moreIssues, { n: rest }) + '</li>' : '') +
          '</ul>';
      }
      container.innerHTML =
        header(data.domain, true) +
        '<div class="ia-bd">' +
          '<div class="ia-sc">' +
            '<div class="ia-sc-n" style="color:' + sc + '">' + data.globalScore + '<span style="font-size:24px;color:#94a3b8;font-weight:600">/100</span></div>' +
            '<div class="ia-sc-l">' + lb + '</div>' +
          '</div>' +
          issuesHtml +
          // Gate: pedir email para informe completo
          '<div style="margin-top:20px;padding-top:18px;border-top:1px solid #f1f5f9">' +
            '<div class="ia-gate-hd"><h4>' + L.gateTitle + '</h4><p>' + L.gateSubtitle + '</p></div>' +
            '<input class="ia-in" data-act="email" type="email" placeholder="' + L.emailPh + '" required>' +
            '<input class="ia-in" data-act="name" placeholder="' + L.namePh + '">' +
            '<button class="ia-bt" data-act="capture">' + L.btnUnlock + '</button>' +
            '<p class="ia-priv">' + L.gatePrivacy + '</p>' +
          '</div>' +
        '</div>';
    }

    function renderSent(email) {
      var baseUrl = API.replace(/\/api\/?$/, '');
      var fullUrl = baseUrl + '/results/' + auditId;
      var waHtml = '';
      if (WHATSAPP && auditResult) {
        var msg = (LANG === 'en'
          ? "Hi! I just audited " + auditResult.domain + " and scored " + auditResult.globalScore + "/100. I'd like to know how to improve it."
          : "¡Hola! Acabo de auditar " + auditResult.domain + " y saqué " + auditResult.globalScore + "/100. Quiero saber cómo mejorarlo.");
        waHtml = '<a href="https://wa.me/' + WHATSAPP.replace(/[^0-9]/g, '') + '?text=' + encodeURIComponent(msg) + '" target="_blank" rel="noopener" class="ia-bo" style="background:#25D366;color:#fff;border:none">' + L.btnContact + '</a>';
      }
      container.innerHTML =
        header(L.sentTitle, true) +
        '<div class="ia-bd">' +
          '<div class="ia-sent">' +
            '<div class="ia-sent-ico">✓</div>' +
            '<p>' + tpl(L.sentSubtitle, { email: email }) + '</p>' +
          '</div>' +
          '<a href="' + fullUrl + '" target="_blank" rel="noopener" class="ia-bt" style="display:block;text-align:center;text-decoration:none">' + L.btnFull + '</a>' +
          waHtml +
          '<button class="ia-bo" data-act="new">' + L.btnNew + '</button>' +
        '</div>';
    }

    function renderError(msg) {
      container.innerHTML =
        header('Error', true) +
        '<div class="ia-bd">' +
          '<p class="ia-err">' + msg + '</p>' +
          '<button class="ia-bt" data-act="retry">' + L.retry + '</button>' +
        '</div>';
    }

    // Delegación de eventos (un solo listener por contenedor).
    container.addEventListener('click', function (ev) {
      var btn = ev.target.closest('[data-act]');
      if (!btn) return;
      var act = btn.getAttribute('data-act');
      if (act === 'close') return opts.onClose && opts.onClose();
      if (act === 'go') return doScan();
      if (act === 'capture') return doCapture();
      if (act === 'new') return renderForm();
      if (act === 'retry') return renderForm();
    });
    container.addEventListener('keydown', function (ev) {
      if (ev.key !== 'Enter') return;
      var act = ev.target && ev.target.getAttribute('data-act');
      if (act === 'url') doScan();
      else if (act === 'email' || act === 'name') doCapture();
    });

    function doScan() {
      var urlEl = container.querySelector('[data-act="url"]');
      var url = urlEl ? urlEl.value.trim() : '';
      if (!url) { urlEl && urlEl.focus(); return; }
      if (url.indexOf('http') !== 0) url = 'https://' + url;
      renderScanning(null);

      fetch(API + '/audit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ url: url, lang: LANG, _source: 'embed' })
      })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json.success || !json.data) return renderError(json.error || L.error);
          var d = json.data;
          if (d.cached && d.result) { renderPreview(d.result); return; }
          auditId = d.auditId;
          polling = pollProgress(auditId,
            function (st) { renderScanning(st); },
            function (full) { renderPreview(full); },
            function (msg) { renderError(msg); }
          );
        })
        .catch(function () { renderError(L.error); });
    }

    function doCapture() {
      if (!auditId) return;
      var emailEl = container.querySelector('[data-act="email"]');
      var nameEl = container.querySelector('[data-act="name"]');
      var email = emailEl ? emailEl.value.trim() : '';
      var name = nameEl ? nameEl.value.trim() : '';
      if (!email) { emailEl && emailEl.focus(); return; }
      // Validación email mínima
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { emailEl && emailEl.focus(); return; }

      var btn = container.querySelector('[data-act="capture"]');
      if (btn) { btn.disabled = true; btn.textContent = '…'; }

      fetch(API + '/capture-lead.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          auditId: auditId,
          leadEmail: email,
          leadName: name || undefined
        })
      })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json.success) {
            // Conversión: lead capturado correctamente.
            fireConversion(auditResult ? auditResult.domain : '', auditResult ? auditResult.globalScore : 0);
            renderSent(email);
          } else {
            if (btn) { btn.disabled = false; btn.textContent = L.btnUnlock; }
            var errEl = container.querySelector('.ia-err');
            if (errEl) errEl.remove();
            var err = document.createElement('p');
            err.className = 'ia-err';
            err.textContent = json.error || L.error;
            btn && btn.parentNode.insertBefore(err, btn.nextSibling);
          }
        })
        .catch(function () {
          if (btn) { btn.disabled = false; btn.textContent = L.btnUnlock; }
        });
    }

    return { renderForm: renderForm };
  }

  // ─── Modo: floating ─────────────────────────────────────────────────
  function initFloating() {
    var btn = document.createElement('button');
    btn.id = 'ia-w-btn';
    btn.title = L.title;
    btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>';
    document.body.appendChild(btn);

    var pop = document.createElement('div');
    pop.id = 'ia-w-pop';
    document.body.appendChild(pop);

    var app = makeApp(pop, {
      onClose: function () { pop.classList.remove('ia-show'); }
    });

    btn.onclick = function () {
      if (pop.classList.contains('ia-show')) pop.classList.remove('ia-show');
      else { app.renderForm(); pop.classList.add('ia-show'); }
    };
  }

  // ─── Modo: inline ───────────────────────────────────────────────────
  function initInline() {
    var target = document.querySelector(TARGET);
    if (!target) {
      console.error('Imagina Audit Widget: no se encontró el contenedor', TARGET);
      return;
    }
    target.classList.add('ia-inline-root');
    var app = makeApp(target, { /* sin onClose: el bloque no se cierra */ });
    app.renderForm();
  }

  // ─── Boot ────────────────────────────────────────────────────────────
  function boot() {
    if (MODE === 'inline') initInline();
    else initFloating();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
