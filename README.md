# SlimPlate

## Description

Bare bone templating system for text strings, based on Latte syntax

## Requirements

- PHP 5.4+

## Usage

SlimPlate support basic if/else statesments, integers, $variables and $object->variable syntax.

	$t = new SlimPlate('{if $x > 5}YES{/if} {if $a == $b}no{else}yes{/if}');
	$t->render(['x'=>6, 'a'=>'yes', 'b'=>'no']);
	returns: "YES yes"