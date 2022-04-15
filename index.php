<?php

require './router.php';
require './slugifier.php';

$method = $_SERVER["REQUEST_METHOD"];
$parsed = parse_url($_SERVER['REQUEST_URI']);
$path = $parsed['path'];

// Útvonalak regisztrálásax
$routes = [
    // [method, útvonal, handlerFunction],
    ['GET', '/', 'homeHandler'],  //főolad
    ['GET', '/admin', 'adminHandler'], //admin főoldal
    ['POST', '/login', 'loginHandler'],
    ['POST', '/logout', 'logoutHandler'],
    ['POST', '/create-dish', 'createDishHandler'],
    ['POST', '/update-dish/{id}', 'updateDishHandler'],
    ['POST', '/delete-dish/{id}', 'deleteDishHandler'],
    ['GET', '/admin/etel-szerkesztese/{keresoBaratNev}', 'dishEditHandler'], // szerkesztés oldala
    ['GET', '/admin/uj-etel-letrehozasa', 'dishNewHandler'], // új létrehozés

];

// Útvonalválasztó inicializálása
$dispatch = registerRoutes($routes);
$matchedRoute = $dispatch($method, $path);
$handlerFunction = $matchedRoute['handler'];
$handlerFunction($matchedRoute['vars']);

// 404 error
function notFoundHandler(){
    echo 'Oldal nem található';
}
// render function
function render($path, $params = []){
    ob_start();
    require __DIR__ . '/views/' . $path;
    return ob_get_clean();
}
// mySql connection
function getConnection(){
    return new PDO('mysql:host=' . $_SERVER['DB_HOST'] . ';dbname=' . $_SERVER['DB_NAME'], $_SERVER['DB_USER'], $_SERVER['DB_PASSWORD']);
}

// bevagy- jelentkezve
function isLoggedIn(): bool {
    if(!isset($_COOKIE[session_name()])){
        return false;
    }
    session_start();
    if(!isset($_SESSION['userId'])){
        return false;
    }
    $pdo = getConnection();
    $statment = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $statment->execute([$_SESSION['userId']]);
    $user = $statment->fetch(PDO::FETCH_ASSOC);
    if(!$user){
        return false;
    }
    return true;
}
// Bejelentkezés
function loginHandler(){
    $pdo = getConnection();
    $statment = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $statment->execute([$_POST['email']]);
    $user = $statment->fetch(PDO::FETCH_ASSOC);
    if(!$user){
        header('Location: /admin?info=invalidCredentials');
        return;
    }
    $isVerified = password_verify($_POST['password'], $user['password']);
    if(!$isVerified){
        header('Location: /admin?info=invalidCredentials');
        return;
    }
    session_start();
    $_SESSION['userId'] = $user['id'];
    header('Location: /admin');
}
// logout
function logoutHandler(){
    session_start();
    $params = session_get_cookie_params();
    setcookie(session_name(), '', 0,  $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
    session_destroy();
    header('Location: /');
}

// ------------------------------------------------------Oldalak megjelenítése-------------------------------------------------------------------------------
// Publikus főoldal megjelenítése
function homeHandler(){
    $pdo = getConnection();
    $statment = $pdo->prepare('SELECT * FROM dishTypes');
    $statment->execute([]);
    $dishType = $statment->fetchAll(PDO::FETCH_ASSOC);
    for($i = 0; $i < count($dishType); $i++){
        $pdo = getConnection();
        $statment = $pdo->prepare('SELECT * FROM dishes WHERE dishTypeId = ?');
        $statment->execute([$dishType[$i]['id']]);
        $dish = $statment->fetchAll(PDO::FETCH_ASSOC);
        $dishType[$i]['dishes'] = $dish;
    }
    echo render("wrapper.phtml", [
        'content' => render("public-menu.phtml", [
            'dishTypes' => $dishType
        ])
    ]);
}

// Admin főoldal megjelenítése
function adminHandler(){
    if(!isLoggedIn()){
        echo render("wrapper.phtml", [
            'content' => render("login.phtml", [])
        ]);
        return;
    }
    $pdo = getConnection();
    $statment = $pdo->prepare('SELECT * FROM dishTypes');
    $statment->execute([]);
    $dishType = $statment->fetchAll(PDO::FETCH_ASSOC);
    for($i = 0; $i < count($dishType); $i++){
        $pdo = getConnection();
        $statment = $pdo->prepare('SELECT * FROM dishes WHERE dishTypeId = ?');
        $statment->execute([$dishType[$i]['id']]);
        $dish = $statment->fetchAll(PDO::FETCH_ASSOC);
        $dishType[$i]['dishes'] = $dish;
    }

    // if info= update:updateSuccessful, delete:, new:createSuccessful
    echo render("admin-wrapper.phtml", [
        'content' => render("dish-list.phtml", [
            'dishList' => $dishType
        ])
    ]);
}

//Szerkesztés megjelenítése
function dishEditHandler($vars){
    $pdo = getConnection();
    $statment = $pdo->prepare('SELECT * FROM dishes WHERE slug = ?');
    $statment->execute([$vars['keresoBaratNev']]);
    $dishes = $statment->fetch(PDO::FETCH_ASSOC);
    $statment2 = $pdo->prepare('SELECT * FROM dishTypes');
    $statment2->execute([]);
    $dishType = $statment2->fetchAll(PDO::FETCH_ASSOC);
    if(!$dishes){
        header('Location: /admin?info=notDesh');
        return;
    }
    echo render("admin-wrapper.phtml", [
        'content' => render("edit-dish.phtml", [
            'dishes' => $dishes,
            'dishType' => $dishType
        ])
    ]);
    return;
}
// Létrehozás megjelenítése
function dishNewHandler(){
    echo render("admin-wrapper.phtml", [
        'content' => render("create-dish.phtml", [])
    ]);
}

//------------------------------------------------------------------FUNKCIÓK---------------------------------------------------------------------------------------------------------

// Új étel létrehozása
function createDishHandler(){
    if($_POST['name'] === '' || $_POST['price'] === '' || $_POST['description'] === ''){
        echo render("admin-wrapper.phtml", [
            'content' => render("create-dish.phtml", [
                'post' => $_POST,
                'alert' => 'Hiányos az ürlap.'
            ])
        ]);
        return;
    }
    if(isset($_POST['isActive'])){
        $isActive = 1;
    }else{
        $isActive = 0;
    }
    $pdo = getConnection();
    $statment = $pdo->prepare('
    INSERT INTO `dishes` (`name`, `slug`, `description`, `price`, `isActive`, `dishTypeId`) 
    VALUES (?, ?, ?, ?, ?, ?)');
    $statment->execute([$_POST['name'], slugify($_POST['name']), 
    $_POST['description'], $_POST['price'], $isActive, $_POST['dishTypeId']]);
    header('Location: /admin?info=createSuccessful');
}

// Étel szerkesztése
function updateDishHandler($vars){
    if(isset($_POST['isActive'])){
        $isActive = 1;
    }else{
        $isActive = 0;
    }
    $pdo = getConnection();
    $statment = $pdo->prepare('UPDATE `dishes` SET `name` = ?, `slug` = ?, `description` = ?, `price` = ?, `isActive` = ?, `dishTypeId` = ? WHERE `id` = ?');
    $statment->execute([$_POST['name'], $_POST['slug'], $_POST['description'], $_POST['price'], $isActive, $_POST['dishTypeId'], $vars['id']]);
    header('Location: /admin?info=updateSuccessful');
}

// Étel törlése
function deleteDishHandler($vars){
    header('Location: /admin?info=deleteSuccessful');
}
