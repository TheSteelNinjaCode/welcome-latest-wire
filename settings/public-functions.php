<?php

function redirect(string $url, bool $replace = true, int $response_code = 0): void
{
    header("Location: $url", $replace, $response_code);
    exit;
}

function isAjaxRequest()
{
    $isAjax = false;

    // Check for standard AJAX header
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $isAjax = true;
    }

    // Check for common AJAX content types
    if (!empty($_SERVER['CONTENT_TYPE'])) {
        $ajaxContentTypes = [
            'application/json',
            'application/x-www-form-urlencoded',
            'multipart/form-data',
        ];

        foreach ($ajaxContentTypes as $contentType) {
            if (strpos($_SERVER['CONTENT_TYPE'], $contentType) !== false) {
                $isAjax = true;
                break;
            }
        }
    }

    // Check for common AJAX request methods
    $ajaxMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
    if (in_array(strtoupper($_SERVER['REQUEST_METHOD']), $ajaxMethods)) {
        $isAjax = true;
    }

    return $isAjax;
}

function isWireRequest(): bool
{
    $headers = getallheaders();
    return isset($headers['http_pphp_wire_request']) && strtolower($headers['http_pphp_wire_request']) === 'true';
}
