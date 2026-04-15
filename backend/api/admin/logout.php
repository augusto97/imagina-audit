<?php
require_once dirname(__DIR__) . '/bootstrap.php';

Auth::logout();
Response::success();
