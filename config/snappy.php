<?php

$ret = array(
    'pdf' => array(
        'enabled' => true,
        'timeout' => false,
        'options' => array(),
        'env'     => array(),
    ),
);

$binary_path = env('PDF_BINARY_PATH', 'vendor' . DIRECTORY_SEPARATOR .'h4cc' . DIRECTORY_SEPARATOR .'wkhtmltopdf-amd64' . DIRECTORY_SEPARATOR .'bin' . DIRECTORY_SEPARATOR .'wkhtmltopdf-amd64');
$binary_path = base_path($binary_path);
$ret['pdf']['binary'] = $binary_path;

return $ret;
