<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator;

session_start();

$container = new Container();
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});
$container->set('renderer', function () {
    // Параметром передается базовая директория в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
/*$app = AppFactory::createFromContainer($container);*/
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/users', function ($request, $response) use ($router) {
    $term = $request->getQueryParam('term', '');
    $users = json_decode(file_get_contents(__DIR__ . '/../data/users.json'), TRUE);
    foreach ($users as $user) {
        $route = $router->urlFor('user.show', ['id' => $user['id']]);
        $routes[$user['id']] = $route;
    }
    if ($term !== '') {
        $filteredUsers = array_filter($users, function($user) use ($term) {
            return strpos($user['nickname'], $term) !== false;
        });
        $usersForPage = array_values($filteredUsers);
        $params = ['users' => $usersForPage, 'term' => $term, 'routes' => $routes];
    } else {
        $flash = $this->get('flash')->getMessages();
        $params = ['users' => $users, 'term' => '', 'routes' => $routes, 'flash' => $flash];
    }
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users.index');

$app->get('/users/new', function ($request, $response) use ($router) {
    $lastId = json_decode(file_get_contents(__DIR__ . '/../data/id.json'), TRUE);
    $lastId ++;
    $id = $lastId;
    file_put_contents(__DIR__ . '/../data/id.json', json_encode($id));
    $params = [
        'user' => ['nickname' => '', 'email' => '', 'id' => $id],
        'errors' => [],
        'route' => $router->urlFor('users.store')
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('users.create');

$app->post('/users', function ($request, $response) use ($router) {
    $user = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($user);
    if (count($errors) === 0) {
        $users = json_decode(file_get_contents(__DIR__ . '/../data/users.json'), TRUE);
        $users[] = $user;
        file_put_contents(__DIR__ . '/../data/users.json', json_encode($users));
        $route = $router->urlFor('users.index');
        $this->get('flash')->addMessage('success', 'New User was added');
        return $response->withRedirect($route, 302);
    }
    $params = [
        'user' => $user,
        'errors' => $errors,
        'route' => $router->urlFor('users.store')
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('users.store');

$app->get('/users/{id}', function ($request, $response, $args) use ($router) {
    $users = json_decode(file_get_contents(__DIR__ . '/../data/users.json'), TRUE);
    $filteredUsers = array_filter($users, fn($user) => $user['id'] === $args['id']);
    if (empty($filteredUsers)) {
        $route = $router->urlFor('users.index');
        $params = ['route' => $route];
        return $this->get('renderer')->render($response->withStatus(404), "404.phtml", $params);
    }
    [$user] = array_values($filteredUsers);
    $params = ['nickname' => $user['nickname']];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user.show');

$app->run();
