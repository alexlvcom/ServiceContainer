<?php

namespace alexlvcom\ServiceContainer\Testing\Stubs;

class MyClass8 implements MyInterface
{
    /**
     * @param MyClass100500 $myClass100500 Incorrect dependency (must be not found)
     */
    public function __construct(MyClass100500 $myClass100500)
    {

    }

    public function foo()
    {

    }
}
