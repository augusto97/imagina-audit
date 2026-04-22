<?php
return [
    // ——— Test email (SMTP verification) ———————————————————————————
    'test.subject' => 'Imagina Audit — Test email',
    'test.body'    => "This is a test email sent from Imagina Audit.\n\n"
                    . "If you received this message, your SMTP configuration is working.\n\n"
                    . "Date: {{date}}",

    // ——— Lead notification sent to the admin when a prospect leaves
    //     contact details at the end of an audit ———————————————————————
    'lead.fallback' => 'Not provided',
    'lead.subject'  => 'New lead: {{domain}} (Score: {{score}}/100)',
    'lead.body'     => "New lead captured in Imagina Audit\n\n"
                     . "Site: {{url}}\nScore: {{score}}/100 ({{level}})\n\n"
                     . "Name: {{name}}\nEmail: {{email}}\n"
                     . "WhatsApp: {{whatsapp}}\n"
                     . "Company: {{company}}\nDate: {{date}}\n",
];
