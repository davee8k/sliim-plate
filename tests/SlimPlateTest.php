<?php
use SlimPlate\SlimPlate;

class SlimPlateTest extends \PHPUnit\Framework\TestCase {

	public function test_if_val () {
		$t = new SlimPlate('{if 5}OK{/if}');
		$this->assertTrue($t->check([]));
		$this->assertEquals($t->render([]), 'OK');
	}

	public function test_if_val_error () {
		$t = new SlimPlate('{if}OK{/if}');
		$this->expectException('InvalidArgumentException', 'Empty IF condition');
		$this->assertFalse($t->check([]));

		$this->assertEquals($t->render([]), '{if}OK{/if}');
	}

	public function test_if_else () {
		$t = new SlimPlate('{if 5}OK{else}wrong{/if} {if 0}wrong{else}OK{/if}');
		$this->assertEquals($t->check([]), true);
		$this->assertEquals($t->render([]), 'OK OK');
	}

	public function test_if_var () {
		$t = new SlimPlate('{if $x}OK{/if}');
		$this->assertEquals($t->check(['x'=>1]), true);
		$this->assertEquals($t->render(['x'=>1]), 'OK');
		$this->assertEquals($t->render(['x'=>'']), '');
		$this->assertEquals($t->render(['x'=>0]), '');

		$t2 = new SlimPlate('{if $x->y}OK{/if}');
		$this->assertEquals($t2->check(['x'=>['y'=>1]]), true);
		$this->assertEquals($t2->render(['x'=>['y'=>1]]), 'OK');
	}

	public function test_if_val_val () {
		$t = new SlimPlate('{if 5 == 5}OK{/if}{if 6.1 == 5}Wrong{/if}');
		$this->assertEquals($t->check([]), true);
		$this->assertEquals($t->render([]), 'OK');
	}

	public function test_if_string_val () {
		$t = new SlimPlate('{if "5" == \'5\'}OK{/if}{if &quot;6&quot; == &#039;5&#039;}Wrong{/if}');
		$this->assertEquals($t->check([]), true);
		$this->assertEquals($t->render([]), 'OK');
	}

	public function test_if_operators () {
		$t = new SlimPlate('{if $x == $y}=={/if}{if $x === $y}==={/if}{if $x > $y}>{/if}{if $x >= $y}>={/if}{if $x < $y}<{/if}{if $x <= $y}<={/if}');
		$this->assertEquals($t->check(['x'=>1,'y'=>1]), true);
		$this->assertEquals($t->render(['x'=>1,'y'=>1]), '=====>=<=');
		$this->assertEquals($t->render(['x'=>'1','y'=>1]), '==>=<=');
		$this->assertEquals($t->render(['x'=>'2','y'=>1]), '>>=');
		$this->assertEquals($t->render(['x'=>1,'y'=>2]), '<<=');
	}

	public function test_if_esc_operators () {
		$t = new SlimPlate('{if $x &gt; $y}>{/if}{if $x &gt;= $y}>={/if}{if $x &lt; $y}<{/if}{if $x &lt;= $y}<={/if}');
		$this->assertEquals($t->check(['x'=>1,'y'=>1]), true);
		$this->assertEquals($t->render(['x'=>1,'y'=>1]), '>=<=');
		$this->assertEquals($t->render(['x'=>'2','y'=>1]), '>>=');
		$this->assertEquals($t->render(['x'=>1,'y'=>2]), '<<=');
	}

	public function test_error_opreator () {
		$t = new SlimPlate("{if x ==== y}aaaa{/if}");
		$this->expectException('InvalidArgumentException', 'Undefined IF operator: ==== y');
		$t->check([]);
	}

	public function test_values () {
		$t = new SlimPlate('{$a} a {$b->a} a {$b-&gt;c}');
		$data = ['a'=>1, 'b'=>['a'=>2, 'c'=>3]];
		$t->check($data, true);
		$this->assertEquals('1 a 2 a 3', $t->render($data));
	}

	public function test_bad_render () {
		$t = new SlimPlate('{if x www}{$a}{/if}');
		$this->assertEquals('&#123;if x www}1{/if&#125;', $t->render(['a'=>1]));
	}

	public function test_error_else () {
		$t = new SlimPlate('{if 1}a{else}b{else}c{/if}');
		$this->expectException('InvalidArgumentException', 'Multiple else in: a{else}b{else}c');
		$t->check([]);
	}

	public function test_error_operator () {
		$t = new SlimPlate('{if x www}aaaa{/if}');
		$this->expectException('InvalidArgumentException', 'Undefined IF operator: www');
		$t->check([]);
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
		$t->check(['w'=>1]);
	}

	public function test_error_sec_variable () {
		$t = new SlimPlate('{if x ===}aaaa{/if}');
		$this->expectException('InvalidArgumentException', 'Missing second variable: x ===');
		$t->check([]);
	}

	public function test_error_variable_def () {
		$t = new SlimPlate('{if $_a}y{/if}');
		$this->expectException('InvalidArgumentException', 'Wrong variable definition');
		$t->check(['y'=>'a']);
	}

	public function test_error_value () {
		$t = new SlimPlate('{$a}');
		$this->expectException('InvalidArgumentException', 'Unset variable: {$a}');
		$t->check(['y'=>'a']);
	}

	public function test_error_value_strict () {
		$t = new SlimPlate('{$a}');
		$this->expectException('InvalidArgumentException', 'Unused variables: b,c');
		$t->check(['a'=>'a','b'=>'b','c'=>'c'], true);
	}

	public function test_error_value_array () {
		$t = new SlimPlate('{$a->a}');
		$this->expectException('InvalidArgumentException', 'Unset variable: {$a->a}');
		$t->check(['a'=>['b'=>'b']]);
	}

	public function test_error_value_array_strict () {
		$t = new SlimPlate('{$a->a}');
		$this->expectException('InvalidArgumentException', 'Unused variables: a');
		$t->check(['a'=>['a'=>'a','b'=>'b','c'=>'c']], true);
	}
}