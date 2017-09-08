# fadoc

fadocはメソッドの型宣言を利用してフォーム配列とドメインオブジェクトを相互に自動変換します。


## 特徴

* ドメインオブジェクトのメソッド定義から自動的に変換が行われます。Formクラスは不要です。
* バリデーションロジックをドメインオブジェクトに定義可能。
* ファクトリ、抽象クラス／インターフェイスに対応。


## 基本的な使い方

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


## インストール

```
$ composer require tyam/fadoc
```

fadocはtyam/conditionに依存しています。


## ドキュメンテーション

* wiki（日本語）
* wiki（英語）


## ライセンス

MIT

