<?php
require_once dirname(__DIR__) . "/bootstrap.php";
/**
 * POST /api/admin/logout
 */
Auth::logout();
Response::success();
