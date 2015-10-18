<?php

namespace alexlvcom\ServiceContainer;

use \Mockery as m;
use alexlvcom\ServiceContainer\Testing\Stubs\MyInterface;
use alexlvcom\ServiceContainer\Testing\Stubs\MyAbstractClass;
use alexlvcom\ServiceContainer\Testing\Stubs\MyClass;
use alexlvcom\ServiceContainer\Testing\Stubs\MyClass2;
use alexlvcom\ServiceContainer\Testing\Stubs\MyClass3;
use alexlvcom\ServiceContainer\Testing\Stubs\MyClass4;

class ServiceContainerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Mockery\MockInterface
     */
    private $containerMock;
    /**
     * @var \ReflectionClass
     */
    private $containerReflector;

    public function setUp()
    {
        $this->setupContainerMock();
        $this->containerReflector = new \ReflectionClass('alexlvcom\ServiceContainer\Container');
    }

    public function tearDown()
    {
        m::close();
    }

    private function setupContainerMock(array $methodsToMock = [])
    {
        $this->containerMock = m::mock('alexlvcom\ServiceContainer\Container['.implode(',', $methodsToMock).']')->shouldAllowMockingProtectedMethods();
    }

    /**
     * @dataProvider canBindServiceDataProvider
     * @param $name
     * @param $binding
     */
    public function testCanBindService($name, $binding)
    {

        $this->containerMock->bind($name, $binding);

        $property = $this->containerReflector->getProperty('objects');
        $property->setAccessible(true);
        $objects = $property->getValue($this->containerMock);

        $this->assertArrayHasKey($name, $objects);
        $this->assertEquals($binding, $objects[$name]);
    }


    public function testCantBindServiceAlreadyRegistered()
    {
        $this->setExpectedException('InvalidArgumentException', "foo is already registered.");
        $this->containerMock->bind('foo', 'alexlvcom\ServiceContainer\Foobar');
        $this->containerMock->bind('foo', 'alexlvcom\ServiceContainer\Foobar2');
    }

    public function canBindServiceDataProvider()
    {
        $object    = new \stdClass();
        $closure   = function () {
            return new \stdClass();
        };
        $className = 'alexlvcom\ServiceContainer\Foobar';
        return [
            [$name = 'foo', $binding = $object],
            [$name = 'bar', $binding = $closure],
            [$name = 'baz', $binding = $className],
        ];
    }

    /**
     * @dataProvider canMakeObject_FromRegistered_DataProvider
     * @param $name
     * @param $binding
     * @param $mustReturn
     */
    public function testCanMakeObject_FromRegistered($name, $binding, $mustReturn)
    {
        $this->containerMock->bind($name, $binding);
        $this->assertEquals($mustReturn, $this->containerMock->make($name));
    }

    public function canMakeObject_FromRegistered_DataProvider()
    {
        $object         = new \stdClass();
        $closureBinding = function () use ($object) {
            return $object;
        };
        $exisingClass   = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass';

        return [
            [$name = 'foo', $binding = $object, $mustReturn = $object],
            [$name = 'bar', $binding = $closureBinding, $mustReturn = $object],
            [$name = 'baz', $binding = $exisingClass, $mustReturn = new MyClass(new MyClass4())],
        ];
    }

    public function testCanMakeObject_FromExistingClass_WithConstructorDependencies()
    {
        // MyClass has dependency on MyClass4, both must be resolved.
        $exisingClass    = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass';
        $dependencyClass = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass4';
        $object          = $this->containerMock->make($exisingClass);
        $this->assertInstanceOf($exisingClass, $object);
        $this->assertInstanceOf($dependencyClass, $object->myClass4);
    }

    public function testCanMakeObject_FromExistingClass_WithoutConstructor()
    {
        $exisingClass = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass4';
        $object       = $this->containerMock->make($exisingClass);
        $this->assertInstanceOf($exisingClass, $object);
    }

    public function testCanMakeObject_FromExistingClass_WithNoRequiredParams_InConstructor()
    {
        $exisingClass = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass6';
        $object       = $this->containerMock->make($exisingClass);
        $this->assertInstanceOf($exisingClass, $object);
    }

    public function testCanMakeObject_FromExistingClass_WithRequiredParams_AndDependency()
    {
        $exisingClass = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass7';
        $object       = $this->containerMock->make($exisingClass, 'test');
        $this->assertInstanceOf($exisingClass, $object);
    }

    public function testCantMakeObject_FromRegistered_InvalidBinding()
    {
        $name             = 'NotFound';
        $nonExistentClass = 'alexlvcom\ServiceContainer\Testing\Stubs\NotFound';
        $this->setExpectedException('alexlvcom\ServiceContainer\ContainerResolveException', "Unable to resolve $name: $nonExistentClass is not a callable neither is pre-created object or path to the class.");
        $this->containerMock->bind($name, $nonExistentClass);
        $this->containerMock->make($name);
    }

    public function testCantMakeObject_NotFoundInContainer_And_IsNotExistingClass()
    {
        $class = 'alexlvcom\ServiceContainer\Testing\Stubs\NotFound';
        $this->setExpectedException('InvalidArgumentException', "$class not found in Container neither it is existing object.");
        $this->containerMock->make($class);
    }

    public function testCantMakeObject_CircularDependencyDetected()
    {
        // MyClass2 has dependency on MyClass3 and MyClass3 has dependency on MyClass2
        $class = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass2';
        $this->setExpectedException('InvalidArgumentException', "Circular dependency detected for $class.");
        $this->containerMock->make($class);
    }

    public function testCantMakeObject_ConstructorIsNotPublic()
    {
        $class = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass5';
        $this->setExpectedException('alexlvcom\ServiceContainer\ContainerResolveException', "Unable to resolve: $class's constructor is not public.");
        $this->containerMock->make($class);
    }

    public function testCantMakeObject_FromExistingClass_WithRequiredParams_AndDependency_ParamIsNotSet()
    {
        $class = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass7';
        $this->setExpectedException('alexlvcom\ServiceContainer\ContainerResolveException', "Constructor parameter #1 'param1' for $class is required");
        $object = $this->containerMock->make($class);
        $this->assertInstanceOf($class, $object);
    }

    public function testCantMakeObject_FromExistingClass_WithIncorrectDependency()
    {
        $class               = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass8';
        $incorrectDependency = 'alexlvcom\ServiceContainer\Testing\Stubs\MyClass100500';
        $this->setExpectedException('alexlvcom\ServiceContainer\ContainerResolveException', "Unable to resolve dependency #1 for $class: Class $incorrectDependency does not exist");
        $object = $this->containerMock->make($class);
        $this->assertInstanceOf($class, $object);
    }

    public function testCanResolveTheSameServiceContainerObject()
    {
        $container1 = new Container();
        $container2 = $container1->make('alexlvcom\ServiceContainer\Container');
        $container3 = $this->containerMock->make('alexlvcom\ServiceContainer\Container');
        $container4 = $this->containerMock->make('alexlvcom\ServiceContainer\Container');
        $this->assertTrue($container1 === $container2, 'Container objects are not equal (first container object created natively)');
        $this->assertTrue($container3 === $container4, 'Container objects are not equal (each container created through container)');
    }
}
