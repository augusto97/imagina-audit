<?php
return [
    // ——— Email de prueba (verificación SMTP) ————————————————————
    'test.subject' => 'Imagina Audit — Email de prueba',
    'test.body'    => "Este es un email de prueba enviado desde Imagina Audit.\n\n"
                    . "Si recibes este mensaje, la configuración SMTP está correcta.\n\n"
                    . "Fecha: {{date}}",

    // ——— Notificación de lead enviada al admin cuando un prospecto
    //     deja datos de contacto al final de una auditoría ——————————
    'lead.fallback' => 'No proporcionado',
    'lead.subject'  => 'Nuevo lead: {{domain}} (Score: {{score}}/100)',
    'lead.body'     => "Nuevo lead capturado en Imagina Audit\n\n"
                     . "Sitio: {{url}}\nScore: {{score}}/100 ({{level}})\n\n"
                     . "Nombre: {{name}}\nEmail: {{email}}\n"
                     . "WhatsApp: {{whatsapp}}\n"
                     . "Empresa: {{company}}\nFecha: {{date}}\n",
];
