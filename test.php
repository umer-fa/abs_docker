<?php
trait traitA {
    protected $myvar;
    public function myfunc($a) { $this->myvar = $a; }
}

trait traitB
{
    public function myfunc($a) { $this->myvar = $a * $a; }
}


class myclass
{
    protected $othervar;

    use traitA, traitB {
        traitA::myfunc insteadof traitB;
        traitA::myfunc as protected t1_myfunc;
        traitB::myfunc as protected t2_myfunc;
    }

    public function fun($a) {
        return $this->myvar = $a * 10;
//        $this->othervar = t2_myfunc($a);
    }

    public function get() {
        echo "myvar: " . $this->myvar . " - othervar: " . $this->othervar;
    }
}

$o = new myclass;
echo $o->fun(3);

