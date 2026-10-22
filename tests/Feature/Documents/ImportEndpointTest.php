<?php

it('returns a valid document cleaned up, with its logo', function () {
    $payload = documentPayload(['reference' => '  ', 'from.logo' => 'data:image/png;base64,'.base64_encode("\x89PNG\r\n\x1a\nrest")]);

    $this->postJson(route('documents.import'), $payload)
        ->assertOk()
        ->assertJsonPath('document.number', 'INV-0001')
        ->assertJsonPath('document.reference', null)
        ->assertJsonPath('document.from.logo', $payload['from']['logo']);
});

it('rejects an invalid document with readable errors', function () {
    $this->postJson(route('documents.import'), documentPayload(['currency' => 'XYZ', 'style.font' => 'comic-sans']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency', 'style.font' => 'The selected font is invalid.']);
});

it('rejects something that is not a document at all', function () {
    $this->postJson(route('documents.import'), ['hello' => 'world'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'number', 'items']);
});
