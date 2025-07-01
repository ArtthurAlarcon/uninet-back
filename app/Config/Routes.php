<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api', ['filter' => 'cors'], function($routes) {
    // Rutas OPTIONS para preflight
    $routes->options('contactos', function() {
        return service('response')->setStatusCode(204);
    });
    $routes->options('contactos/(:any)', function() {
        return service('response')->setStatusCode(204);
    });
    
    // rutas normales
    $routes->post('contactos', 'Contactos::create');
    $routes->get('contactos/relaciones', 'Contactos::listarConRelaciones');
    $routes->get('contactos/(:num)', 'Contactos::show/$1'); // Para obtener un contacto
    $routes->post('contactos/actualizar/(:num)', 'Contactos::updateWithPost/$1');
    $routes->delete('contactos/(:num)', 'Contactos::delete/$1');

    // Nueva ruta para servir imágenes
    $routes->get('contactos/foto/(:segment)', 'Contactos::serveImage/$1');
});