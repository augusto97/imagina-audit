<?php
require_once dirname(__DIR__) . "/bootstrap.php";
/**
 * GET /api/admin/session — Verifica sesión activa
 */
Response::success(['authenticated' => Auth::isAuthenticated()]);
