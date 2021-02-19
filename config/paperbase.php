<?php

$defaultJobTimeout = env('JOB_TIMEOUT', 600);

return [
    'job_timeout' => $defaultJobTimeout,
    'ocr_timeout' => env('OCR_TIMEOUT', $defaultJobTimeout)
];
