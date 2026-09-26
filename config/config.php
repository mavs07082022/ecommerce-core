<?php
/**
 * Global Configuration
 * Load this file BEFORE any other file that uses date() or time().
 */
date_default_timezone_set('Asia/Manila');

// Error reporting (turn off display in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);