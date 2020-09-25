<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator;
use Slim\Middleware\MethodOverrideMiddleware;

session_start();

$container = new Container();
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});
$container->set('renderer', function () {
    // Параметром передается базовая директория в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->add(MethodOverrideMiddleware::class);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/users', function ($request, $response) use ($router) {
    $term = $request->getQueryParam('term', '');
    $users = json_decode($request->getCookieParam('allUsers', json_encode([])), true);
    $routes = [];
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
    $lastId = json_decode(file_get_contents(__DIR__ . '/../data/id.json'), true);
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
        $users = json_decode($request->getCookieParam('allUsers', json_encode([])), true);
        $users[] = $user;
        $encodedUsers = json_encode($users);
        $route = $router->urlFor('users.index');
        $this->get('flash')->addMessage('success', 'New User was added');
        return $response->withHeader('Set-Cookie', "allUsers={$encodedUsers};Path=/")->withRedirect($route, 302);
    }
    $params = [
        'user' => $user,
        'errors' => $errors,
        'route' => $router->urlFor('users.store')
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('users.store');

$app->get('/users/{id}', function ($request, $response, $args) use ($router) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('allUsers', json_encode([])), true);
    $filteredUsers = array_filter($users, fn($user) => $user['id'] === $id);
    if (empty($filteredUsers)) {
        $route = $router->urlFor('users.index');
        $params = ['route' => $route];
        return $this->get('renderer')->render($response->withStatus(404), "404.phtml", $params);
    }
    [$user] = array_values($filteredUsers);
    $urlToEdit = $router->urlFor('users.edit', ['id' => $user['id']]);
    $urlToDelete = $router->urlFor('users.destroy', ['id' => $user['id']]);
    $flash = $this->get('flash')->getMessages();
    $params = [
        'user' => $user,
        'url' => ['edit' => $urlToEdit, 'delete' => $urlToDelete],
        'flash' => $flash
    ];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user.show');

$app->get('/users/{id}/edit', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('allUsers', json_encode([])), true);
    $filteredUsers = array_filter($users, fn($user) => $user['id'] === $id);
    [$user] = array_values($filteredUsers);
    $params = [
        'user' => $user,
        'errors' => [],
        'route' => $router->urlFor('users.update', ['id' => $user['id']])
    ];
    return $this->get('renderer')->render($response, "users/edit.phtml", $params);
})->setName('users.edit');

$app->patch('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $data = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($data);
    if (count($errors) === 0) {
        $users = json_decode($request->getCookieParam('allUsers', json_encode([])), true);
        $updatedUsers = array_map(function ($user) use ($data, $id) {
            if ($user['id'] === $id) {
                return ['nickname' => $data['nickname'], 'email' => $data['email'], 'id' => $id];
            }
            return $user;
        }, $users);
        $encodedUsers = json_encode($updatedUsers);
        $this->get('flash')->addMessage('success', 'User has been updated');
        $route = $router->urlFor('user.show', ['id' => $id]);
        return $response->withHeader('Set-Cookie', "allUsers={$encodedUsers};Path=/")->withRedirect($route, 302);
    }
    $params = [
        'user' => $data,
        'errors' => $errors,
        'route' => $router->urlFor('users.update', ['id' => $data['id']])
    ];
    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, "users/edit.phtml", $params);
})->setName('users.update');

$app->delete('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $users = json_decode($request->getCookieParam('allUsers', json_encode([])), true);
    $filteredUsers = array_filter($users, fn($user) => $user['id'] !== $id);
    $encodedUsers = json_encode(array_values($filteredUsers));
    $this->get('flash')->addMessage('success', 'User has been deleted');
    $route = $router->urlFor('users.index');
    return $response->withHeader('Set-Cookie', "allUsers={$encodedUsers};Path=/")->withRedirect($route);
})->setName('users.destroy');

$app->post('/session', function ($request, $response) use ($router) {
    $user = $request->getParsedBodyParam('user');
    $email = $user['email'];
    $url = $router->urlFor('/');
    $users = json_decode($request->getCookieParam('allUsers', json_encode([])), true);
    $filteredUsers = array_filter($users, fn($item) => $item['email'] === $email);
    if (!empty($filteredUsers)) {
        $_SESSION['user'] = $user['name'];
        return $response->withRedirect($url, 302);
    }
    $this->get('flash')->addMessage('error', 'Wrong email');
    return $response->withRedirect($url, 302);
})->setName('session.create');

$app->delete('/session', function ($request, $response, array $args) use ($router) {
    $_SESSION = [];
    session_destroy();
    $route = $router->urlFor('/');
    return $response->withRedirect($route);
})->setName('session.destroy');

$app->get('/', function ($request, $response) use ($router) {
    $flash = $this->get('flash')->getMessages();
    if (isset($_SESSION['user'])) {
        $params = [
            'user' => $_SESSION['user'] ?? null,
            'url' => $router->urlFor('session.destroy'),
            'flash' => $flash,
        ];
    } else {
        $params = [
            'user' => $_SESSION['user'] ?? null,
            'url' => $router->urlFor('session.create'),
            'flash' => $flash,
        ];
    }
    return $this->get('renderer')->render($response, 'users/login.phtml', $params);
})->setName('/');

$app->run();
