<?php

test('the root route redirects to the admin panel', function () {
    $this->get('/')->assertRedirect('/admin');
});
