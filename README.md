# fadoc

fadoc automatically converts Form-Arrays and Domain-Objects to each other according to type declarations of methods.


## Feature

- Conversion is done automatically according to method declarations of domain objects. No Form classes.
- You can define validation logics on domain objects.
- Supports factories, abstract classes/interfaces.


## Basic Usage

```php
class Point {
    private $x, y;
    public function __construct(int $x, int $y) {...}
}
class Circle {
    private $c, $r;
    public function __construct(Point $c, int $r) {...}
    public function intersects(Circle $another): bool {...}
}

$form = ['another' => [
           'c' => ['x' => '0', 'y' => '20'],
           'r' => '10'
        ]];
$c = new tyam\fadoc\Converter();
$condition = $c->objectize(['my\domain\Circle', 'intersects'], $form);
if ($condition->isFine()) {
    list($another) = $condition->get();
    $result = $myCircle->intersects($another);
} else {
    // validation error...
}
```


## Installation

```
$ composer require tyam/fadoc
```

fadoc depends on tyam/condition, which also is my library.


## Documentation

* [wiki (in Japanese)](https://github.com/tyam/fadoc/wiki/%E3%83%9B%E3%83%BC%E3%83%A0)
* [wiki (in English)](https://github.com/tyam/fadoc/wiki)


## Lisence

MIT
