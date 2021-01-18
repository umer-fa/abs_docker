<?php
class A implements JsonSerializable
{
    protected $a = array();

    public function __construct()
    {
        $this->a = array( new B, new B );
    }

    public function jsonSerialize()
    {
        return $this->a;
    }
}

class B implements JsonSerializable
{
    protected $b = array( 'foo' => 'bar' );

    public function jsonSerialize()
    {
        return $this->b;
    }
}


$foo = new A();

var_dump($foo);
echo "<br>";
$json = json_encode($foo);

var_dump($json);
