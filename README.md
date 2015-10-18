# Service Container - an IoC Container
---

### Simple IoC container with the magic of automatically resolving constructor dependencies.

#### Dependencies:
- PHP >= 5.6

### Installation
- `composer install`


### Usage
```
namespace MyNamepace;

class MyClass {
    public function __construct(MyDependency $myDependency)
    {
        // your code goes here
    }
}

$container = new \alexlvcom\ServiceContainer\Container();

// binding existing class, constructor dependecies will be automatically resolved
$container->bind('MyClass', 'MyNamepace\MyClass');

// bind object creation logic
$container->bind('MyClass', function () {
    return new MyClass(new MyDependency());
});

// bind existing object
$container->bind('MyClass', new MyClass(new MyDependency()));

// resolve
$myObject = $container->make('MyClass');

// resolve existing object, which haven't been added into container
$myObject2 = $container->make('MyNamepace\MyClass2');

```