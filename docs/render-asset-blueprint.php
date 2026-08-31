<?php

require dirname(__DIR__).'/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options;
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml(file_get_contents(__DIR__.'/Blueprint-Asset-Management-HRIS.html'), 'UTF-8');
$dompdf->render();

file_put_contents(__DIR__.'/Blueprint-Asset-Management-HRIS.pdf', $dompdf->output());
