<?php
use SlimPlate\SlimPlate;

class SlimPlateTest extends \PHPUnit\Framework\TestCase {

public function test_if_val () {
		$t = new SlimPlate('{if 5}OK{/if}');
		$this->assertTrue($t->check(array()));
		$this->assertEquals($t->render(array()), 'OK');
	}

	public function test_if_val_error () {
		$t = new SlimPlate('{if}OK{/if}');
		$this->expectException('InvalidArgumentException', 'Empty IF condition');
		$this->assertFalse($t->check(array()));

		$this->assertEquals($t->render(array()), '{if}OK{/if}');
	}

	public function test_if_else () {
		$t = new SlimPlate('{if 5}OK{else}wrong{/if} {if 0}wrong{else}OK{/if}');
		$this->assertEquals($t->check(array()), TRUE);
		$this->assertEquals($t->render(array()), 'OK OK');
	}

	public function test_if_var () {
		$t = new SlimPlate('{if $x}OK{/if}');
		$this->assertEquals($t->check(array('x'=>1)), TRUE);
		$this->assertEquals($t->render(array('x'=>1)), 'OK');
		$this->assertEquals($t->render(array('x'=>'')), '');
		$this->assertEquals($t->render(array('x'=>0)), '');

		$t2 = new SlimPlate('{if $x->y}OK{/if}');
		$this->assertEquals($t2->check(array('x'=>array('y'=>1))), TRUE);
		$this->assertEquals($t2->render(array('x'=>array('y'=>1))), 'OK');
	}

	public function test_if_val_val () {
		$t = new SlimPlate('{if 5 == 5}OK{/if}{if 6 == 5}Wrong{/if}');
		$this->assertEquals($t->check(array()), TRUE);
		$this->assertEquals($t->render(array()), 'OK');
	}

	public function test_if_string_val () {
		$t = new SlimPlate('{if "5" == \'5\'}OK{/if}{if &quot;6&quot; == &#039;5&#039;}Wrong{/if}');
		$this->assertEquals($t->check(array()), TRUE);
		$this->assertEquals($t->render(array()), 'OK');
	}

	public function test_if_operators () {
		$t = new SlimPlate('{if $x == $y}=={/if}{if $x === $y}==={/if}{if $x > $y}>{/if}{if $x >= $y}>={/if}{if $x < $y}<{/if}{if $x <= $y}<={/if}');
		$this->assertEquals($t->check(array('x'=>1,'y'=>1)), TRUE);
		$this->assertEquals($t->render(array('x'=>1,'y'=>1)), '=====>=<=');
		$this->assertEquals($t->render(array('x'=>'1','y'=>1)), '==>=<=');
		$this->assertEquals($t->render(array('x'=>'2','y'=>1)), '>>=');
		$this->assertEquals($t->render(array('x'=>1,'y'=>2)), '<<=');
	}

	public function test_if_esc_operators () {
		$t = new SlimPlate('{if $x &gt; $y}>{/if}{if $x &gt;= $y}>={/if}{if $x &lt; $y}<{/if}{if $x &lt;= $y}<={/if}');
		$this->assertEquals($t->check(array('x'=>1,'y'=>1)), TRUE);
		$this->assertEquals($t->render(array('x'=>1,'y'=>1)), '>=<=');
		$this->assertEquals($t->render(array('x'=>'2','y'=>1)), '>>=');
		$this->assertEquals($t->render(array('x'=>1,'y'=>2)), '<<=');
	}

	public function test_error_opreator () {
		$t = new SlimPlate("{if x ==== y}aaaa{/if}");
		$this->expectException('InvalidArgumentException', 'Undefined IF operator: ==== y');
		$t->check(array());
	}

	public function test_values () {
		$t = new SlimPlate('{$a} a {$b->a} a {$b-&gt;c}');
		$data = array('a'=>1, 'b'=>array('a'=>2, 'c'=>3));
		$t->check($data, TRUE);
		$this->assertEquals('1 a 2 a 3', $t->render($data));
	}

	public function test_bad_render () {
		$t = new SlimPlate('{if x www}{$a}{/if}');
		$this->assertEquals('&#123;if x www}1{/if&#125;', $t->render(array('a'=>1)));
	}

	public function test_error_else () {
		$t = new SlimPlate('{if 1}a{else}b{else}c{/if}');
		$this->expectException('InvalidArgumentException', 'Multiple else in: a{else}b{else}c');
		$t->check(array());
	}

	public function test_error_operator () {
		$t = new SlimPlate('{if x www}aaaa{/if}');
		$this->expectException('InvalidArgumentException', 'Undefined IF operator: www');
		$t->check(array());
	}

	public function test_error_bad_value () {
		$t = new SlimPlate("{if 6 > \"x'}OK{/if}");
		$this->expectException('InvalidArgumentException', 'Undefined value: ');
		$t->check();
	}

	public function test_not_error_render () {
		$t = new SlimPlate("{if 6 > \"x'}OK{/if}");
		$this->assertEquals('&#123;if 6 > "x\'}OK{/if&#125;', $t->render());
	}

	public function test_error_variable () {
		$t = new SlimPlate('{if $x}aaaa{/if}');
		$this->expectException('InvalidArgumentException', 'Undefined variable: $x');
		$t->check(array('w'=>1));
	}

	public function test_error_sec_variable () {
		$t = new SlimPlate('{if x ===}aaaa{/if}');
		$this->expectException('InvalidArgumentException', 'Missing second variable: x ===');
		$t->check(array());
	}

	public function test_error_variable_def () {
		$t = new SlimPlate('{if $_a}y{/if}');
		$this->expectException('InvalidArgumentException', 'Wrong variable definition');
		$t->check(array('y'=>'a'));
	}

	public function test_error_value () {
		$t = new SlimPlate('{$a}');
		$this->expectException('InvalidArgumentException', 'Unset variable: {$a}');
		$t->check(array('y'=>'a'));
	}

	public function test_error_value_strict () {
		$t = new SlimPlate('{$a}');
		$this->expectException('InvalidArgumentException', 'Unused variables: b,c');
		$t->check(array('a'=>'a','b'=>'b','c'=>'c'), TRUE);
	}

	public function test_error_value_array () {
		$t = new SlimPlate('{$a->a}');
		$this->expectException('InvalidArgumentException', 'Unset variable: {$a->a}');
		$t->check(array('a'=>array('b'=>'b')));
	}

	public function test_error_value_array_strict () {
		$t = new SlimPlate('{$a->a}');
		$this->expectException('InvalidArgumentException', 'Unused variables: a');
		$t->check(array('a'=>array('a'=>'a','b'=>'b','c'=>'c')), TRUE);
	}
}
