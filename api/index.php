<?php

/**
 * Serverless entry point for Vercel. Laravel's storage directory lives in /tmp there
 * (see bootstrap/app.php), so its folders are created on every cold start.
 */
foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
    if (! is_dir('/tmp/storage/'.$directory)) {
        mkdir('/tmp/storage/'.$directory, 0755, true);
    }
}

require __DIR__.'/../public/index.php';
