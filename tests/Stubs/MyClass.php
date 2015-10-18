<?php

namespace alexlvcom\ServiceContainer\Testing\Stubs;

class MyClass extends MyAbstractClass implements MyInterface
{
    public $myClass4;

    public function __construct(MyClass4 $myClass4)
    {

        $this->myClass4 = $myClass4;

    }

    public function foo()
    {

    }

    public function bar()
    {

    }
}
