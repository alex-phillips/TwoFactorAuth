<?php
/**
 * TwoFactorAuth Auth file for Nginx auth_request module.
 * This script performs the actual check of the authentication token
 * that was generated after a successful login.
 *
 * @author Arno0x0x - https://twitter.com/Arno0x0x
 * @license GPLv3 - licence available here: http://www.gnu.org/copyleft/gpl.html
 * @link https://github.com/Arno0x/
 */

//------------------------------------------------------
// Include config file
if (file_exists('../config.php')) {
	// don't authenticate whenever there is a fatal error in the config file
	require_once("../lib/TFAErrorHandler.php");

	register_shutdown_function(array('TFAErrorHandler', 'handle_fatal_error'));
	set_exception_handler(array('TFAErrorHandler', 'handle_exception'));
	set_error_handler(array('TFAErrorHandler', 'handle_php_error'));

	try {
		require_once("../config.php");
	}
	catch (Exception $e) {
		// don't authenticate whenever there are notices or warnings in the config file
        redirect();
	}
} else {
	// don't authenticate if the config file is missing!!
    redirect();
}

// * ========================= DEBUG BLOCK ==========================

if (defined('TFA_NGINX_DEBUG') AND TFA_NGINX_DEBUG)
{
	$dir = dirname(__FILE__);
	$debugFileName = $dir.DIRECTORY_SEPARATOR."debug.log";
	$canLog = false;

	if (!file_exists($logName)) {
		$canLog = is_writable($dir);
	} else if (is_writable($logName)) {
		$canLog = true;
	}

	if ($canLog) {
		$debugHandle = fopen ($debugFileName ,"a");

		foreach ($_SERVER as $key => $value) {
			if (is_array($value)) {
				$vs = array();

				foreach ($value AS $k => $v) {
					if (is_array($v)) {
						continue;
					}

					$vs[] = '[' . $k . "] => '" . $v . "'";
				}

				$value = implode(",\n", $vs);
			}

			fwrite ($debugHandle,$key.": ".$value."\n");
		}

		fwrite ($debugHandle,"END");
		fclose($debugHandle);
	}
}

if (!defined('SESSION_NAME') OR !SESSION_NAME) {
	// don't authenticate if config.php is broken!!
    redirect();
}

//====================================================
// Restore an existing session
session_set_cookie_params (0, "/", COOKIE_DOMAIN, true, true);
session_name(SESSION_NAME);
session_start();

//====================================================
// Check if the authentication has been completed
if (isset($_SESSION["authenticated"]) && $_SESSION["authenticated"] === true) {
	http_response_code(200);
}
else {
	// Else return an HTTP 401 status code
	session_destroy();
    redirect();
}

function redirect()
{
    if (AUTH_FAILED_REDIRECT_URL) {
		$url = AUTH_FAILED_REDIRECT_URL;

		if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
			isset($_SERVER['HTTP_X_FORWARDED_HOST']) &&
			isset($_SERVER['HTTP_X_FORWARDED_URI'])) {
			$from = $_GET['from'] ?? "{$_SERVER['HTTP_X_FORWARDED_PROTO']}://{$_SERVER['HTTP_X_FORWARDED_HOST']}{$_SERVER['HTTP_X_FORWARDED_URI']}";
			$query = parse_url($url, PHP_URL_QUERY);
			if ($query) {
				$url .= "&from=$from";
			} else {
				$url .= "?from=$from";
			}
		}

        header("Location: $url");
        exit;
    }

    http_response_code(401);
}
