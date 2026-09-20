<?php

use App\Mail\WorkflowActionEmail;

test('the mailable renders the body as plain text without escaping', function () {
    $mailable = new WorkflowActionEmail('Sujet du run', 'Bonjour Camille, <votre> valeur : 12');

    $rendered = $mailable->render();

    expect($rendered)->toContain('Bonjour Camille, <votre> valeur : 12')
        ->and($rendered)->not->toContain('&lt;votre&gt;');
});
