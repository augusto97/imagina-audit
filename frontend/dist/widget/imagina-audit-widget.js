/**
 * Imagina Audit — Widget Embebible
 * JavaScript vanilla, sin dependencias externas.
 *
 * Uso:
 * <script src="https://audit.tusitio.com/widget/imagina-audit-widget.js"
 *   data-api="https://audit.tusitio.com/api"
 *   data-color="#0CC0DF"
 *   data-position="bottom-right"
 *   data-lang="es">
 * </script>
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
  var COLOR = script.getAttribute('data-color') || '#0CC0DF';
  var POS = script.getAttribute('data-position') || 'bottom-right';
  var LANG = script.getAttribute('data-lang') || 'es';
  var WHATSAPP = script.getAttribute('data-whatsapp') || '';

  if (!API) { console.error('Imagina Audit Widget: data-api es obligatorio'); return; }
  // Quitar trailing slash
  API = API.replace(/\/+$/, '');

  var t = {
    es: { title: 'Auditoría Web Gratuita', subtitle: 'Descubre el estado de tu sitio en 30 segundos', urlLabel: 'URL del sitio', emailLabel: 'Tu email (opcional)', button: 'Auditar Gratis', scanning: 'Analizando...', viewFull: 'Ver Informe Completo →', contact: 'Hablar con un Experto', close: 'Cerrar', error: 'Error al analizar el sitio', retry: 'Reintentar' },
    en: { title: 'Free Website Audit', subtitle: 'Discover your site health in 30 seconds', urlLabel: 'Website URL', emailLabel: 'Your email (optional)', button: 'Audit Free', scanning: 'Scanning...', viewFull: 'View Full Report →', contact: 'Talk to an Expert', close: 'Close', error: 'Error scanning the site', retry: 'Retry' }
  };
  var L = t[LANG] || t.es;
  var isRight = POS === 'bottom-right';

  // Inyectar estilos
  var css = document.createElement('style');
  css.textContent = [
    '#ia-w-btn{position:fixed;bottom:20px;' + (isRight?'right':'left') + ':20px;width:56px;height:56px;border-radius:50%;background:'+COLOR+';color:#fff;border:none;cursor:pointer;z-index:99999;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,0,0,.25);transition:transform .2s,box-shadow .2s;font-family:sans-serif}',
    '#ia-w-btn:hover{transform:scale(1.08);box-shadow:0 6px 24px rgba(0,0,0,.3)}',
    '#ia-w-btn svg{width:28px;height:28px;fill:none;stroke:currentColor;stroke-width:1.5}',
    '#ia-w-pop{position:fixed;bottom:86px;' + (isRight?'right':'left') + ':20px;width:370px;max-width:calc(100vw - 40px);max-height:520px;overflow-y:auto;background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,.18);z-index:99999;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:none;animation:ia-slide .25s ease}',
    '#ia-w-pop.ia-show{display:block}',
    '@keyframes ia-slide{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}',
    '.ia-hd{display:flex;justify-content:space-between;align-items:center;padding:16px 20px 12px;border-bottom:1px solid #f1f5f9}',
    '.ia-hd h3{margin:0;font-size:15px;font-weight:700;color:#0f172a}',
    '.ia-x{background:none;border:none;cursor:pointer;color:#94a3b8;font-size:22px;padding:4px;line-height:1}',
    '.ia-x:hover{color:#64748b}',
    '.ia-bd{padding:16px 20px 20px}',
    '.ia-bd p{margin:0 0 14px;font-size:13px;color:#64748b;line-height:1.5}',
    '.ia-in{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;color:#0f172a;outline:none;margin-bottom:10px;transition:border .2s;font-family:inherit}',
    '.ia-in:focus{border-color:'+COLOR+'}',
    '.ia-bt{width:100%;padding:12px;border:none;border-radius:10px;background:'+COLOR+';color:#fff;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .2s;font-family:inherit}',
    '.ia-bt:hover{opacity:.9}',
    '.ia-bt:disabled{opacity:.5;cursor:not-allowed}',
    '.ia-pg{height:4px;background:#f1f5f9;border-radius:2px;margin:16px 0;overflow:hidden}',
    '.ia-pg-b{height:100%;background:'+COLOR+';border-radius:2px;width:0%;transition:width .8s linear}',
    '.ia-sc{text-align:center;padding:10px 0}',
    '.ia-sc-n{font-size:48px;font-weight:800;line-height:1.1}',
    '.ia-sc-l{font-size:13px;color:#64748b;margin-top:4px}',
    '.ia-is{list-style:none;padding:0;margin:14px 0}',
    '.ia-is li{font-size:12px;color:#475569;padding:6px 0;border-bottom:1px solid #f8fafc}',
    '.ia-bo{width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#475569;font-size:13px;font-weight:500;cursor:pointer;margin-top:8px;transition:border .2s;font-family:inherit;text-align:center;text-decoration:none;display:block;box-sizing:border-box}',
    '.ia-bo:hover{border-color:'+COLOR+';color:'+COLOR+'}',
    '.ia-lk{display:block;text-align:center;margin-top:12px;font-size:11px;color:#94a3b8;cursor:pointer;text-decoration:none;background:none;border:none;font-family:inherit}',
    '.ia-lk:hover{color:#64748b}'
  ].join('\n');
  document.head.appendChild(css);

  // Crear botón
  var btn = document.createElement('button');
  btn.id = 'ia-w-btn';
  btn.title = L.title;
  btn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>';
  document.body.appendChild(btn);

  // Crear popup
  var pop = document.createElement('div');
  pop.id = 'ia-w-pop';
  document.body.appendChild(pop);

  btn.onclick = function () {
    if (pop.classList.contains('ia-show')) {
      pop.classList.remove('ia-show');
    } else {
      if (!pop.innerHTML || pop.querySelector('.ia-err')) renderForm();
      pop.classList.add('ia-show');
    }
  };

  function close() { pop.classList.remove('ia-show'); }

  function renderForm() {
    pop.innerHTML =
      '<div class="ia-hd"><h3>' + L.title + '</h3><button class="ia-x" id="ia-cls">&times;</button></div>' +
      '<div class="ia-bd">' +
        '<p>' + L.subtitle + '</p>' +
        '<input class="ia-in" id="ia-url" placeholder="https://ejemplo.com" value="' + location.origin + '">' +
        '<input class="ia-in" id="ia-email" placeholder="' + L.emailLabel + '">' +
        '<button class="ia-bt" id="ia-go">' + L.button + '</button>' +
      '</div>';
    pop.querySelector('#ia-cls').onclick = close;
    pop.querySelector('#ia-go').onclick = doAudit;
    // Enter key
    pop.querySelector('#ia-email').onkeydown = function(e) { if (e.key === 'Enter') doAudit(); };
  }

  function doAudit() {
    var urlEl = document.getElementById('ia-url');
    var emailEl = document.getElementById('ia-email');
    var url = urlEl ? urlEl.value.trim() : '';
    var email = emailEl ? emailEl.value.trim() : '';
    if (!url) return;
    if (url.indexOf('http') !== 0) url = 'https://' + url;

    pop.innerHTML =
      '<div class="ia-hd"><h3>' + L.scanning + '</h3><button class="ia-x" id="ia-cls">&times;</button></div>' +
      '<div class="ia-bd">' +
        '<p>' + L.scanning + '</p>' +
        '<div class="ia-pg"><div class="ia-pg-b" id="ia-bar"></div></div>' +
      '</div>';
    pop.querySelector('#ia-cls').onclick = close;

    setTimeout(function () {
      var bar = document.getElementById('ia-bar');
      if (bar) bar.style.width = '60%';
    }, 200);

    var body = JSON.stringify({ url: url, leadEmail: email || undefined });

    fetch(API + '/audit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: body
    })
    .then(function (r) { return r.json(); })
    .then(function (json) {
      if (json.success && json.data) {
        renderResult(json.data);
      } else {
        renderError(json.error || L.error);
      }
    })
    .catch(function (e) {
      console.error('Imagina Audit Widget error:', e);
      renderError(L.error);
    });
  }

  function renderResult(data) {
    var colors = { critical: '#EF4444', warning: '#F59E0B', good: '#10B981', excellent: '#059669' };
    var labels = { critical: 'Crítico', warning: 'Regular', good: 'Bueno', excellent: 'Excelente' };
    if (LANG === 'en') { labels = { critical: 'Critical', warning: 'Fair', good: 'Good', excellent: 'Excellent' }; }
    var sc = colors[data.globalLevel] || '#64748B';
    var lb = labels[data.globalLevel] || data.globalLevel;

    var issues = [];
    (data.modules || []).forEach(function (m) {
      (m.metrics || []).forEach(function (mt) {
        if ((mt.level === 'critical' || mt.level === 'warning') && issues.length < 4) {
          issues.push('<li>' + (mt.level === 'critical' ? '🔴' : '🟡') + ' ' + mt.name + '</li>');
        }
      });
    });

    var baseUrl = API.replace(/\/api\/?$/, '');
    var resultUrl = baseUrl + '/results/' + data.id;

    pop.innerHTML =
      '<div class="ia-hd"><h3>' + data.domain + '</h3><button class="ia-x" id="ia-cls">&times;</button></div>' +
      '<div class="ia-bd">' +
        '<div class="ia-sc">' +
          '<div class="ia-sc-n" style="color:' + sc + '">' + data.globalScore + '<span style="font-size:20px;color:#94a3b8">/100</span></div>' +
          '<div class="ia-sc-l">' + lb + '</div>' +
        '</div>' +
        (issues.length ? '<ul class="ia-is">' + issues.join('') + '</ul>' : '') +
        '<a href="' + resultUrl + '" target="_blank" class="ia-bt" style="display:block;text-align:center;text-decoration:none;color:#fff">' + L.viewFull + '</a>' +
        (WHATSAPP ? '<a href="https://wa.me/' + WHATSAPP.replace(/[^0-9]/g, '') + '?text=' + encodeURIComponent((LANG === 'en' ? 'Hi! I just scanned my site ' : 'Hola! Acabo de auditar mi sitio ') + data.domain + ' (' + data.globalScore + '/100). ' + (LANG === 'en' ? 'I would like more info.' : 'Me gustaría más información.')) + '" target="_blank" class="ia-bo" style="display:block;text-align:center;text-decoration:none;background:#25D366;color:#fff;border:none">' + L.contact + '</a>' : '') +
        '<button class="ia-bo" id="ia-new">' + (LANG === 'en' ? 'Scan Another Site' : 'Escanear Otro Sitio') + '</button>' +
        '<button class="ia-lk" id="ia-cls2">' + L.close + '</button>' +
      '</div>';
    pop.querySelector('#ia-cls').onclick = close;
    pop.querySelector('#ia-cls2').onclick = close;
    pop.querySelector('#ia-new').onclick = renderForm;
  }

  function renderError(msg) {
    pop.innerHTML =
      '<div class="ia-hd"><h3>Error</h3><button class="ia-x ia-err" id="ia-cls">&times;</button></div>' +
      '<div class="ia-bd">' +
        '<p style="color:#EF4444">' + msg + '</p>' +
        '<button class="ia-bt" id="ia-retry">' + L.retry + '</button>' +
      '</div>';
    pop.querySelector('#ia-cls').onclick = close;
    pop.querySelector('#ia-retry').onclick = renderForm;
  }

  // Renderizar formulario al cargar
  renderForm();
})();
