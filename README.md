## php-router

-----
#### Add routes in router.php
```php
// Basic usage
 Router::get('route/{id}', ['class@action', 'name'=>'route-name']);
 Router::post('route/{id}', ['class@action', 'name'=>'route-name']);
 Router::head('route/{id}', ['class@action', 'name'=>'route-name']);
 Router::put('route/{id}', ['class@action', 'name'=>'route-name']);
 Router::delete('route/{id}', ['class@action', 'name'=>'route-name']);
 Router::get('post/{id}/view', ['class@action', 'name'=>'route-name']);

 // Multiple HTTP verbs
 Router::methods(['post', 'get'],'route/{id}', ['class@action', 'name'=>'route-name']);
 Router::any('route/{id}', ['class@action', 'name'=>'route-name']);

// Callback
 Router::get('route/{slug}/{id}', [function($slug, $id){
    echo $id . ' from ' . $slug;
  }, 'name'=>'callback']);

//Optional Parameters
// Matches /route/user-post/55 or /route/user-post
 Router::get('route/{slug}/{id?}', ['class@action', 'name'=>'route-name']);

//Regular Expression - Syntax {param:[regex]}
Router::get('route/{slug}/edit/{post:[a-z]}/{id:[0-9]}', ['class@action', 'name'=>'route-name']);
Router::get('route/{lang:(en|bg)}/{post}/{id:\d+}', ['class@action', 'name'=>'route-name']);


// Matches: /route/my-post-name.pdf
Router::get('route/{slug:^\w+((?:\.pdf))$}', ['class@action', 'name'=>'route-name']);
 
// Use (format=html) instead Regular Expression | Matches: /route/my-post-name.html or /route/my-post-name
Router::get('route/{slug:format=html}, ['class@action', 'name'=>'route-name']);

```
### Dispatch routes
```php
include_once 'Router.php';
include_once 'routes.php';

$router = new Router();
$route = $router->dispatch('post/55');

/*
$route now is array like

Array
(
    [action] => controller@action
    [params] => Array
        (
            [id] => 55
        )
)
*/

```
#### Named routes 
You may specify a name for a route using the "name" array key when defining the route:
```php
//the route name is 'lang-fr'
Router::get('route/{lang}/post/{slug}', ['Class@action', 'name'=>'lang-fr']);
```
#### Make URI string To Named Route
If the named route defines parameters, you may pass array with parameters
as the second argument to the  'route' method
```php
// generate URI string like: route/fr/post/your-post-slug
$route = $router->route('lang-fr',['lang'=>'fr', 'slug'=>'your-post-slug'])->route;

//Set http verbs 
$route = $router->route('lang-fr',['lang'=>'fr', 'slug'=>'your-post-slug'], 'GET')->route;

```
#### Generate URL 
```php
// generate URL: http://your-site-name.com/route/fr/post/your-post-slug
$url = $router->route('lang-fr',['lang'=>'fr', 'slug'=>'your-post-slug'], 'GET')->url;
```