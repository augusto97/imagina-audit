<?php
/**
 * GET /api/admin/session — Verifica sesión activa
 */
Response::success(['authenticated' => Auth::isAuthenticated()]);
