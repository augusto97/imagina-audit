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

  // Obtener configuración del script tag
  var script = document.currentScript;
  var API = script.getAttribute('data-api') || '';
  var COLOR = script.getAttribute('data-color') || '#0CC0DF';
  var POS = script.getAttribute('data-position') || 'bottom-right';
  var LANG = script.getAttribute('data-lang') || 'es';

  if (!API) { console.error('Imagina Audit Widget: data-api es obligatorio'); return; }

  var t = {
    es: {
      title: 'Auditoría Web Gratuita',
      subtitle: 'Descubre el estado de tu sitio en 30 segundos',
      urlLabel: 'URL del sitio',
      emailLabel: 'Tu email (opcional)',
      button: 'Auditar Gratis',
      scanning: 'Analizando...',
      viewFull: 'Ver Informe Completo',
      contact: 'Hablar con un Experto',
      close: 'Cerrar',
      error: 'Error al analizar el sitio',
      retry: 'Reintentar',
    },
    en: {
      title: 'Free Website Audit',
      subtitle: 'Discover your site health in 30 seconds',
      urlLabel: 'Website URL',
      emailLabel: 'Your email (optional)',
      button: 'Audit Free',
      scanning: 'Scanning...',
      viewFull: 'View Full Report',
      contact: 'Talk to an Expert',
      close: 'Close',
      error: 'Error scanning the site',
      retry: 'Retry',
    }
  };
  var L = t[LANG] || t.es;

  var isRight = POS === 'bottom-right';
  var isOpen = false;
  var state = 'form'; // form | scanning | result | error

  // Inyectar estilos
  var style = document.createElement('style');
  style.textContent = '\
    #ia-widget-btn{position:fixed;bottom:20px;' + (isRight ? 'right:20px' : 'left:20px') + ';width:56px;height:56px;border-radius:50%;background:' + COLOR + ';color:#fff;border:none;cursor:pointer;z-index:99999;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px rgba(0,0,0,0.25);transition:transform .2s,box-shadow .2s}\
    #ia-widget-btn:hover{transform:scale(1.08);box-shadow:0 6px 24px rgba(0,0,0,0.3)}\
    #ia-widget-btn svg{width:28px;height:28px}\
    #ia-widget-popup{position:fixed;bottom:86px;' + (isRight ? 'right:20px' : 'left:20px') + ';width:370px;max-width:calc(100vw - 40px);max-height:500px;overflow-y:auto;background:#fff;border-radius:16px;box-shadow:0 12px 48px rgba(0,0,0,0.15);z-index:99999;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;opacity:0;transform:translateY(10px);transition:opacity .25s,transform .25s;pointer-events:none}\
    #ia-widget-popup.ia-open{opacity:1;transform:translateY(0);pointer-events:auto}\
    .ia-header{display:flex;justify-content:space-between;align-items:center;padding:16px 20px 12px;border-bottom:1px solid #f1f5f9}\
    .ia-header h3{margin:0;font-size:15px;font-weight:700;color:#0f172a}\
    .ia-close{background:none;border:none;cursor:pointer;color:#94a3b8;font-size:20px;padding:4px;line-height:1}\
    .ia-close:hover{color:#64748b}\
    .ia-body{padding:16px 20px 20px}\
    .ia-body p{margin:0 0 14px;font-size:13px;color:#64748b}\
    .ia-input{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;color:#0f172a;outline:none;margin-bottom:10px;transition:border .2s}\
    .ia-input:focus{border-color:' + COLOR + '}\
    .ia-btn{width:100%;padding:11px;border:none;border-radius:10px;background:' + COLOR + ';color:#fff;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .2s}\
    .ia-btn:hover{opacity:.9}\
    .ia-btn:disabled{opacity:.6;cursor:not-allowed}\
    .ia-progress{height:4px;background:#f1f5f9;border-radius:2px;margin:14px 0;overflow:hidden}\
    .ia-progress-bar{height:100%;background:' + COLOR + ';border-radius:2px;width:0%;transition:width 1s linear}\
    .ia-score{text-align:center;padding:10px 0}\
    .ia-score-num{font-size:48px;font-weight:800;line-height:1}\
    .ia-score-label{font-size:13px;color:#64748b;margin-top:2px}\
    .ia-issues{list-style:none;padding:0;margin:14px 0}\
    .ia-issues li{font-size:12px;color:#475569;padding:5px 0;border-bottom:1px solid #f8fafc}\
    .ia-btn-outline{width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:10px;background:#fff;color:#475569;font-size:13px;font-weight:500;cursor:pointer;margin-top:8px;transition:border .2s}\
    .ia-btn-outline:hover{border-color:' + COLOR + ';color:' + COLOR + '}\
    .ia-link{display:block;text-align:center;margin-top:12px;font-size:11px;color:#94a3b8;cursor:pointer;text-decoration:none}\
    .ia-link:hover{color:#64748b}\
  ';
  document.head.appendChild(style);

  // Crear botón flotante
  var btn = document.createElement('button');
  btn.id = 'ia-widget-btn';
  btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>';
  btn.title = L.title;
  btn.onclick = function () { togglePopup(); };
  document.body.appendChild(btn);

  // Crear popup
  var popup = document.createElement('div');
  popup.id = 'ia-widget-popup';
  document.body.appendChild(popup);

  function togglePopup() {
    isOpen = !isOpen;
    if (isOpen) {
      if (state === 'form') renderForm();
      popup.classList.add('ia-open');
    } else {
      popup.classList.remove('ia-open');
    }
  }

  function renderForm() {
    state = 'form';
    popup.innerHTML = '\
      <div class="ia-header"><h3>' + L.title + '</h3><button class="ia-close" onclick="document.getElementById(\'ia-widget-popup\').classList.remove(\'ia-open\')">&times;</button></div>\
      <div class="ia-body">\
        <p>' + L.subtitle + '</p>\
        <input class="ia-input" id="ia-url" placeholder="https://ejemplo.com" value="' + window.location.origin + '">\
        <input class="ia-input" id="ia-email" placeholder="' + L.emailLabel + '">\
        <button class="ia-btn" id="ia-submit">' + L.button + '</button>\
      </div>';
    document.getElementById('ia-submit').onclick = doAudit;
  }

  function doAudit() {
    var url = document.getElementById('ia-url').value.trim();
    var email = document.getElementById('ia-email').value.trim();
    if (!url) return;
    if (!url.startsWith('http')) url = 'https://' + url;

    state = 'scanning';
    popup.innerHTML = '\
      <div class="ia-header"><h3>' + L.scanning + '</h3><button class="ia-close" onclick="document.getElementById(\'ia-widget-popup\').classList.remove(\'ia-open\')">&times;</button></div>\
      <div class="ia-body">\
        <p>' + L.scanning + '</p>\
        <div class="ia-progress"><div class="ia-progress-bar" id="ia-bar"></div></div>\
      </div>';

    // Animar barra
    setTimeout(function () { document.getElementById('ia-bar').style.width = '70%'; }, 100);

    var body = JSON.stringify({ url: url, leadEmail: email || undefined });
    fetch(API + '/audit.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: body,
    })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json.success && json.data) {
          renderResult(json.data);
        } else {
          renderError(json.error || L.error);
        }
      })
      .catch(function () { renderError(L.error); });
  }

  function renderResult(data) {
    state = 'result';
    var levelColors = { critical: '#EF4444', warning: '#F59E0B', good: '#10B981', excellent: '#059669' };
    var sc = levelColors[data.globalLevel] || '#64748B';
    var levelLabels = { critical: 'Crítico', warning: 'Regular', good: 'Bueno', excellent: 'Excelente' };
    var label = levelLabels[data.globalLevel] || data.globalLevel;

    // Top 4 problemas críticos/warning
    var issues = [];
    (data.modules || []).forEach(function (m) {
      (m.metrics || []).forEach(function (metric) {
        if ((metric.level === 'critical' || metric.level === 'warning') && issues.length < 4) {
          var dot = metric.level === 'critical' ? '🔴' : '🟡';
          issues.push('<li>' + dot + ' ' + metric.name + ': ' + metric.displayValue + '</li>');
        }
      });
    });

    var baseUrl = API.replace(/\/api\/?$/, '');

    popup.innerHTML = '\
      <div class="ia-header"><h3>Resultado</h3><button class="ia-close" onclick="document.getElementById(\'ia-widget-popup\').classList.remove(\'ia-open\')">&times;</button></div>\
      <div class="ia-body">\
        <div class="ia-score">\
          <div class="ia-score-num" style="color:' + sc + '">' + data.globalScore + '</div>\
          <div class="ia-score-label">' + label + ' · ' + data.domain + '</div>\
        </div>\
        ' + (issues.length > 0 ? '<ul class="ia-issues">' + issues.join('') + '</ul>' : '') + '\
        <a href="' + baseUrl + '/results/' + data.id + '" target="_blank" class="ia-btn" style="display:block;text-align:center;text-decoration:none">' + L.viewFull + '</a>\
        <button class="ia-btn-outline" onclick="window.open(\'https://wa.me/\',\'_blank\')">' + L.contact + '</button>\
        <a class="ia-link" onclick="document.getElementById(\'ia-widget-popup\').classList.remove(\'ia-open\')">' + L.close + '</a>\
      </div>';
  }

  function renderError(msg) {
    state = 'error';
    popup.innerHTML = '\
      <div class="ia-header"><h3>Error</h3><button class="ia-close" onclick="document.getElementById(\'ia-widget-popup\').classList.remove(\'ia-open\')">&times;</button></div>\
      <div class="ia-body">\
        <p style="color:#EF4444">' + msg + '</p>\
        <button class="ia-btn" id="ia-retry">' + L.retry + '</button>\
      </div>';
    document.getElementById('ia-retry').onclick = function () { renderForm(); };
  }
})();
