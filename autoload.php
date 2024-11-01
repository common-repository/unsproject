<?php

function uns_autoloader($class)
{
    $namespaces = [
        [
            'class' => 'Srp\\',
            'namespace' => 'Srp\\',
            'directory' => __DIR__ . '/src/vendor/Srp/'
        ],
        [
            'class' => 'BrowserDetector\\',
            'namespace' => 'BrowserDetector\\',
            'directory' => __DIR__ . '/src/vendor/BrowserDetector/'
        ],
        [
            'class' => 'UNSProjectApp\\',
            'namespace' => 'UNSProjectApp\\',
            'directory' => __DIR__ . '/src/'
        ],
    ];

    $namespace_map = null;
    foreach ($namespaces as $oneNamespace) {
        if (strpos($class, $oneNamespace['class']) !== false) {
            $namespace_map = [
                $oneNamespace['namespace'] => $oneNamespace['directory']
            ];
        }
    }

    iF(!empty($namespace_map)) {
        foreach ($namespace_map as $prefix => $dir) {
            $path = str_replace($prefix, $dir, $class);
            $path = str_replace('\\', '/', $path);
            $path = $path . '.php';
            require_once $path;
        }
    }
}

spl_autoload_register('uns_autoloader');
