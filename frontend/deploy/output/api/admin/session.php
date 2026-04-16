<?php
require_once dirname(__DIR__) . '/bootstrap.php';

// Este endpoint NO requiere auth — retorna true/false
Response::success(['authenticated' => Auth::checkAuth()]);
