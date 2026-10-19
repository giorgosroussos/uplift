<?php

/*
| Phase 1: GET /up returns 200 (OPS-001).
*/

it('exposes the Laravel health endpoint /up', function () {
    $this->get('/up')->assertOk();
});

it('answers only GET on the health endpoint', function () {
    $this->post('/up')->assertMethodNotAllowed();
});
