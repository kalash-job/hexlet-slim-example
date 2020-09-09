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

/*$app->get('/users', function ($request, $response) {
    return $response->write('GET /users');
});*/

/*$app->post('/users', function ($request, $response) {
    return $response->withStatus(302);
});*/

$app->get('/users', function ($request, $response) {
    $term = $request->getQueryParam('term', '');
    $users = json_decode(file_get_contents(__DIR__ . '/../data/users.json'), TRUE);
    if ($term !== '') {
        $filteredUsers = array_filter($users, function($user) use ($term) {
            return strpos($user['nickname'], $term) !== false;
        });
        $usersForPage = array_values($filteredUsers);
        $params = ['users' => $usersForPage, 'term' => $term];
    } else {
        $params = ['users' => $users, 'term' => ''];
    }
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
});

$app->get('/users/new', function ($request, $response) use (&$lastId) {
    $lastId = json_decode(file_get_contents(__DIR__ . '/../data/id.json'), TRUE);
    $lastId ++;
    $id = $lastId;
    file_put_contents(__DIR__ . '/../data/id.json', json_encode($id));
    $params = [
        'user' => ['nickname' => '', 'email' => '', 'id' => $id],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->post('/users', function ($request, $response) {
    $user = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($user);
    if (count($errors) === 0) {
        $users = json_decode(file_get_contents(__DIR__ . '/../data/users.json'), TRUE);
        $users[] = $user;
        file_put_contents(__DIR__ . '/../data/users.json', json_encode($users));
        return $response->withRedirect('/users', 302);
    }
    $params = [
        'user' => $user,
        'errors' => $errors
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
});

$app->run();
