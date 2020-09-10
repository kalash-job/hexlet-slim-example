<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator;

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/users', function ($request, $response) use ($router) {
    $term = $request->getQueryParam('term', '');
    $users = json_decode(file_get_contents(__DIR__ . '/../data/users.json'), TRUE);
    foreach ($users as $user) {
        $route = $router->urlFor('user', ['id' => $user['id']]);
        $routes[$user['id']] = $route;
    }
    if ($term !== '') {
        $filteredUsers = array_filter($users, function($user) use ($term) {
            return strpos($user['nickname'], $term) !== false;
        });
        $usersForPage = array_values($filteredUsers);
        $params = ['users' => $usersForPage, 'term' => $term, 'routes' => $routes];
    } else {
        $params = ['users' => $users, 'term' => '', 'routes' => $routes];
    }
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->get('/users/new', function ($request, $response) use ($router) {
    $lastId = json_decode(file_get_contents(__DIR__ . '/../data/id.json'), TRUE);
    $lastId ++;
    $id = $lastId;
    file_put_contents(__DIR__ . '/../data/id.json', json_encode($id));
    $params = [
        'user' => ['nickname' => '', 'email' => '', 'id' => $id],
        'errors' => [],
        'route' => $router->urlFor('users')
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('new-user-form');

$app->post('/users', function ($request, $response) use ($router) {
    $user = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($user);
    if (count($errors) === 0) {
        $users = json_decode(file_get_contents(__DIR__ . '/../data/users.json'), TRUE);
        $users[] = $user;
        file_put_contents(__DIR__ . '/../data/users.json', json_encode($users));
        $route = $router->urlFor('users');
        return $response->withRedirect($route, 302);
    }
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('save-user');

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');



$app->run();
