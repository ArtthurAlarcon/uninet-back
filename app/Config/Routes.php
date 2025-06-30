<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

$routes->group('api/contactos', function($routes) {
    $routes->get('/', 'Contactos::index');          // GET → Listar básico
    $routes->get('relaciones', 'Contactos::listarConRelaciones'); // Sin "/" al inicio
    $routes->post('/', 'Contactos::create');        // POST → Crear
    $routes->put('(:num)', 'Contactos::update/$1'); // PUT → Actualizar
    $routes->delete('(:num)', 'Contactos::delete/$1'); // DELETE → Eliminar
});