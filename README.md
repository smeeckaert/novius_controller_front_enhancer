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

The pattern is defined by segments separated by "/". If a segment begins by ":" it will be considered a Parameters (see below).

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
(i.e : The /:page route will be matched before the /:char)

#### Route configuration

You can extend the simple route configuration to allow more control of the routing.
In order to do so, you must change the name of the action by an associative array like this one below.

All theses options can be used by the routeConfig method in the controller.

The cache management is already implemented in the controller.

```php
protected static $_routes = array(
        '/'       => 'home',
        '/:param' => array(
            'action' => 'home',
            'cache'  => array(
                array(
                    'type'     => 'callable',
                    'callable' => "MyCallableMethod",
                    'args'     => array()
                ),
            ),
        ),
    );
```

### Parameters

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

##### format

The key 'format' can contain a callable that will be used to the shape of a parameter during a request.

This callback will be called when matching parameters to transform the matched parameter to another object or string.

It will be called when building the url to transform back the parameter to its string form.

The callback takes two parameters, the value, and a boolean which is true when building the query.

In this example we transform a date parameter to a date object.

```php

protected static $_params = array(
    'date' => array('format' => 'routeDateFormat')
);

public static function routeDateFormat($value, $output)
{
    if ($output) {
        $class = get_class($value);
        if ($class === "Date") {
            return $value->format('%Y-%m-%d');
        }
    } else {
        try {
            return Date::create_from_string($value, '%Y-%m-%d');
        } catch (\UnexpectedValueException $e) {
            return null;
        }
    }
}

public function action_main()
{
    dd(get_class($this->routeParam('date')); // null or Date
}

```

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

### Callables

All callables are patched to allow static call within the class.
That way if you have a static method in your class, you don't have to prefix it in parameters like match or format.

```php

static $_params = array(
'date' => array('format' => 'matchDate')
);

public static function matchDate(){
//...
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

You can override this behaviour by giving the 'route' parameter which contain a valid route.

```php
echo Tools_Enhancer::url('enhancer_test', array('route' => '/:page', 'page' => $page, 'id' => 'id-test', 'char' => 'b'));
```

Will output
```
http://.../enhancer_url/page-virtual-name.html
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

## License

‘Controller_Front_Application_Enhancer’ is licensed under [GNU Affero General Public License v3](http://www.gnu.org/licenses/agpl-3.0.html) or (at your option) any later version.