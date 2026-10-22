<?php

it('renders the home page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('GenDoc');
});
