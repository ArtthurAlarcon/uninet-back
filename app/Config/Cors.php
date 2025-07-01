<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cross-Origin Resource Sharing (CORS) Configuration
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
 */
class Cors extends BaseConfig
{
    /**
     * The default CORS configuration.
     *
     * @var array{
     *      allowedOrigins: list<string>,
     *      allowedOriginsPatterns: list<string>,
     *      supportsCredentials: bool,
     *      allowedHeaders: list<string>,
     *      exposedHeaders: list<string>,
     *      allowedMethods: list<string>,
     *      maxAge: int,
     *  }
     */
    public array $default = [
        'allowedOrigins' => ['http://localhost:3030'], // Reemplaza con tu URL de frontend
        'allowedOriginsPatterns' => [],
        'supportsCredentials' => false,
        'allowedHeaders' => ['Content-Type', 'Accept', 'X-Requested-With'],
        'exposedHeaders' => [],
        'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'maxAge' => 3600,
    ];

    // Configuración adicional para API si es necesario
    public array $api = [
        'allowedOrigins' => ['*'],
        'supportsCredentials' => true,
        'allowedHeaders' => ['Authorization', 'Content-Type'],
        'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    ];
}
