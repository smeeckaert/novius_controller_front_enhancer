# Controller_Front_Application_Enhancer

The Controller_Front_Application_Enhancer is an extension of \Nos\Controller_Front_Application which handle routes for urlEnhancers.


## Setup

Extend from \Enhancer\Controller_Front_Application_Enhancer

```php
class Controller_Front_MyEnhancer extends \Enhancer\Controller_Front_Application_Enhancer
{
...
}
```

Make your enhancer point to the "route" action.

```php
    'enhancers'  => array(
        'my_enhancer_name' => array(
            'title'       => 'Enhancer With Route',
            'desc'        => '',
            'urlEnhancer' => 'application_name/front/myEnhancer/route', // URL of the enhancer
        )
    ),
```

### Declaring your routes

Your routes are defined by an associative array pattern => action.

The pattern is defined by segments separated by "/". If a segment begins by ":" it will be considered a [Parameters][].

The action is the name of a public function named "action_{action}".

```php
protected static $_routes = array(
        '/'                             => 'home',
        '/:page'                        => 'page',
        '/:char'                        => 'foo',
        '/:id'                          => 'foo',
        '/text/:page'                   => 'page',
        '/:page/:id'                    => 'bar',
        '/:page/:id/:char/have-fun'     => 'fun'
    );
```

The route priority is defined by the order of the routes in this array. The first declared route will be matched first.
(i.e : The /:page route will be matched before the /:char

### [Parameters]

Parameters are set in the protected static $_params property as an associative array ({param_name}:configuration)

For each parameter in your routes you have to define an element and his configuration in this array.


```php
protected static $_params = array(
        'id'   => array(),                                   // Empty configuration
        'char' => array('match' => 'is_string'),             // Callable configuration
        'page' => array('model' => '\Nos\Page\Model_Page'),  // Model configuration
        'image' => array('model' => 'Model_Image', 'field' => 'image_searcheable_field')  // Model configuration

    );
```

#### Parameter configuration

In order to match parameters, you have to set a configuration as value of each parameter.

If the configuration is empty, the parameter will always match like a wildcard.

##### match

The key 'match' can contain a callable that will be used to check the value of the parameter.

##### model

The key 'model' can contain a class name that will be used to find the property.

If the parameter 'field' is not provided, it will try to find a VirtualPath or VirtualName behaviour and extract the correct field to match.

You can give it classes which are not models but they must have a public static method find() with \Nos\Orm\Model prototyping.

##### field

The key 'field' contain the mysql column which will be used to retrieve the model.

It's optionnal for models with VirtualPath or VirtualName behaviour.

### Accessing parameters

To access parameters from within an action, call the method routeParam().

This method will return the matched parameter or null if it's not defined.

```php
public function action_page()
{
    $modelPage = $this->routeParam('page');
}
```

### Building URLs

To build automatically your urls, simply give at route url builder parameters as an associative array ({param_name}:value).

The class will then build the most specialized url it could make.

```php
echo Tools_Enhancer::url('enhancer_test', array('page' => $page, 'id' => 'id-test', 'char' => 'b'));
```

Will output
```
http://.../enhancer_url/page-virtual-name/id-test/b/have-fun.html
```

Caution: The builder always assume that your parameters are correct.

## Example

```php

class Controller_Front_MyEnhancer extends \Enhancer\Controller_Front_Application_Enhancer
{
    protected static $_routes = array(
        '/'                             => 'home',
        '/:page'                        => 'page',
        '/:char'                        => 'foo',
        '/:id'                          => 'foo',
        '/text/:page'                   => 'page',
        '/:page/:id'                    => 'bar',
        '/:page/:id/:char/have-fun'     => 'fun'
    );

    protected static $_params = array(
        'id'   => array(),                                   // Empty configuration
        'char' => array('match' => 'is_string'),             // Callable configuration
        'page' => array('model' => '\Nos\Page\Model_Page'),  // Model configuration
    );

    // Will be called by /
    public function action_home(){
    }

    // Will be called by /virtual-name-page and /text/virtual-name-page
    public function action_page(){
    }

    // Will be called by /[^virtual-name-page]
    public function action_foo(){
       $char = $this->routeParams('char');
       var_dump($char); // Null if the route was not a string
    }

    // Will be called by /virtual-name-page/*
    public function action_bar(){
    }

    // Will be called by /virtual-name-page/*/[A-Z]+/have-fun
    public function action_fun(){
    }
}
```