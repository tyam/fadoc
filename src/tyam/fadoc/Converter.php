<?php

namespace tyam\fadoc;

use tyam\condition\Condition;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use LogicException;


class Converter {
    /**
     * [fully-qualified-class-name => constructor-specifier, ...]
     * constructor-specifier: 
     *   [class:string, method:string]
     *   [class:object, method:string]
     *   class:string  -- calls class::invoke()
     *   class:object  -- calls $object->invoke()
     */
    private $ctrmap;

    public function __construct(array $ctrmap = []) {
        $this->ctrmap = $ctrmap;
    }

    public function setConstructor($cls, $method) {
        $this->ctrmap[$cls] = $method;
    }

    public function getConstructor($cls) {
        return $this->ctrmap[$cls];
    }

    protected function mapError($what) {
        return $what;
    }

    protected static function debug($fmt, ...$args) {
        //vprintf($fmt."\n", $args);
    }

    protected static function getMethodSpec($action): ReflectionFunctionAbstract {
        if (is_string($action)) {
            return self::getMethodSpec([$action, '__invoke']);
        } else if (is_object($action)) {
            return self::getMethodSpec([$action, '__invoke']);
        } else if (is_array($action)) {
            list($c, $m) = $action;
            $crefl = new ReflectionClass($c);
            if ($m == '__construct') {
                $mrefl = $crefl->getConstructor();
            } else {
                $mrefl = $crefl->getMethod($m);
            }
            if (! $mrefl) {
                throw new LogicException('method not found');
            }
            return $mrefl;
        } else {
            throw new LogicException('action invalid');
        }
    }

    public function objectize($action, $input): Condition {
        $mrefl = self::getMethodSpec($action);
        $cd = $this->objectizeMethod($mrefl, $input);
        self::debug('o: err:%d', count($cd->describe()));
        return $cd;
    }

    public function validate($action, $input): Condition {
        $mrefl = self::getMethodSpec($action);
        $cd = $this->validateMethod($mrefl, $input);
        self::debug('v: err:%d', count($cd->describe()));
        return $cd;
    }

    protected function objectizeMethod(ReflectionFunctionAbstract $method, $input) {
        $where = $method->getDeclaringClass()->getName() . '->' . $method->getName();
        self::debug('omethod[%s]: ', $where);
        $ps = $method->getParameters();
        $plen = count($ps);
        $errors = [];
        $objs = [];
        for ($pi = 0, $ai = 0; $pi < $plen; $pi++, $ai++) {
            $p = $ps[$pi];
            if (isset($input[$ai])) {
                $n = $ai;
                $v = $input[$ai];
            } else if (isset($input[$p->getName()])) {
                $n = $p->getName();
                $v = $input[$p->getName()];
            } else {
                $n = $ai;
                $v = null;
            }
            if ($v === null && $p->isVariadic()) {
                break;
            }
            self::debug('omethod[%s]:p %d %d %s', $where, $pi, $ai, $n);
            $cd = $this->objectizeParameter($p, $v);
            if (!$cd()) {
                $errors[$ai] = $errors[$p->getName()] = $cd->describe();
            } else {
                $objs[$ai] = $cd->get();
            }
            if ($p->isVariadic()) {
                $pi--;
            }
        }
        if ($errors) {
            return Condition::poor($errors);
        } else {
            return Condition::fine($objs);
        }
    }

    protected function validateMethod(ReflectionFunctionAbstract $method, $input) {
        $where = $method->getDeclaringClass()->getName() . '->' . $method->getName();
        self::debug('vmethod[%s]: ', $where);
        $ps = $method->getParameters();
        $plen = count($ps);

        $key = key($input);
        if ($key === null || $key === false) {
            throw new \LogicException('no input specified');
        }
        if (is_numeric($key)) {
            $pi = (int)$key;
            if ($plen <= $pi) {
                if ($ps[$plen - 1]->isVariadic()) {
                    $p = $ps[$plen - 1];
                } else {
                    throw new \LogicException('index out of bound: '.$pi);
                }
            } else {
                $p = $ps[$pi];
            }
        } else {
            for ($pi = 0; $pi < $plen; $pi++) {
                $p = $ps[$pi];
                if ($key == $p->getName()) {
                    break;
                }
            }
        }
        self::debug('vmethod[%s]: p %pi', $where, $pi);
        return $this->validateParameter($p, $input[$key]);
    }

    protected function filterValue(ReflectionParameter $p, $cd) {
        if (!$cd()) {
            return $cd;
        }
        $cls = $p->getDeclaringClass();
        $methodName = $this->getValidatorName($p);
        if ($cls->hasMethod($methodName)) {
            return $cls->getMethod($methodName)->invoke(null, $cd->get());
        } else {
            return $cd;
        }
    }

    protected function getValidatorName(ReflectionParameter $p) :string {
        $m = $p->getDeclaringFunction();
        if ($m->isConstructor()) {
            return "validate".ucfirst($p->getName());
        } else {
            return "validate".ucfirst($p->getName())."For".ucfirst($m->getName());
        }
    }

    protected function objectizeParameter(ReflectionParameter $p, $v) {
        if ($p->getClass()) {
            // class specified
            $c = $p->getClass();
            if (is_null($v)) {
                if ($p->isDefaultValueAvailable()) {
                    self::debug('oparameter: class defaultValue');
                    return Condition::fine($p->getDefaultValue());
                } else if ($this->detectMinArity($c) == 0) {
                    // rescue. Classes with 0 parameters can be omitted.
                    $v = [];
                } else {
                    self::debug('oparameter: class arrayRequired!');
                    return Condition::poor($this->mapError('arrayRequired'));
                }
            } else if (is_string($v)) {
                self::debug('oparameter: class arrayRequired!');
                return Condition::poor($this->mapError('arrayRequired'));
            }
            if ($c->isAbstract() || $c->isInterface()) {
                return $this->objectizeAbstractClass($c, $v);
            } else {
                return $this->objectizeClass($c, $v);
            }
        } else if ($p->getType() == 'array') {
            // array specified
            if (is_null($v)) {
                if ($p->isDefaultValueAvailable()) {
                    self::debug('oparameter: array defaultValue');
                    return Condition::fine($p->getDefaultValue());
                } else {
                    // rescue. Arrays with 0 elements can be omitted.
                    $v = [];
                }
            }
            if (is_array($v)) {
                self::debug('oparameter: array asis');
                return $this->filterValue($p, Condition::fine($v));
            } else {
                self::debug('oparameter: array arrayRequired!');
                return Condition::poor($this->mapError('arrayRequired'));
            }
        } else if ($p->hasType()) {
            // scalar type specified
            if (is_array($v)) {
                self::debug('oparameter: scalar scalarRequired!');
                return Condition::poor($this->mapError('scalarRequired'));
            } else if (is_null($v)) {
                if ($p->isDefaultValueAvailable()) {
                    self::debug('oparameter: scalar defaultValue');
                    return Condition::fine($p->getDefaultValue());
                } else if ($p->getType() == 'bool') {
                    // rescue. Empty value for bool is interpreted as false; for checkbox.
                    self::debug('oparameter: scalar bool-rescued');
                    return Condition::fine(false);
                } else {
                    self::debug('oparameter: scalar required!');
                    return Condition::poor($this->mapError('required'));
                }
            } else {
                switch ($p->getType().'') {
                    case 'string': return $this->filterValue($p, $this->objectizeString($v));
                    case 'int':    return $this->filterValue($p, $this->objectizeInt($v));
                    case 'float':  return $this->filterValue($p, $this->objectizeFloat($v));
                    case 'bool':   return $this->filterValue($p, $this->objectizeBool($v));
                }
            }
        } else {
            // not specified
            self::debug('oparameter: any asis');
            return $this->filterValue($p, Condition::fine($v));
        }
    }

    protected function validateParameter(ReflectionParameter $p, $v) {
        if ($p->getClass()) {
            // class specified
            $c = $p->getClass();
            if (is_string($v)) {
                self::debug('vparameter: class arrayRequired!');
                return Condition::poor($this->mapError('arrayRequired'));
            }
            if ($c->isAbstract() || $c->isInterface()) {
                return $this->validateAbstractClass($c, $v);
            } else {
                return $this->validateClass($c, $v);
            }
        } else if ($p->getType() == 'array') {
            // array specified
            if (is_array($v)) {
                self::debug('vparameter: array asis');
                return $this->filterValue($p, Condition::fine($v));
            } else {
                self::debug('vparameter: array arrayRequired!');
                return Condition::poor($this->mapError('arrayRequired'));
            }
        } else if ($p->hasType()) {
            // scalar type specified
            if (is_array($v)) {
                self::debug('vparameter: scalar scalrRequired!');
                return Condition::poor($this->mapError('scalarRequired'));
            } else {
                switch ($p->getType().'') {
                    case 'string': return $this->filterValue($p, $this->objectizeString($v));
                    case 'int':    return $this->filterValue($p, $this->objectizeInt($v));
                    case 'float':  return $this->filterValue($p, $this->objectizeFloat($v));
                    case 'bool':   return $this->filterValue($p, $this->objectizeBool($v));
                }
            }
        } else {
            // not specified
            self::debug('vparameter: any asis');
            return $this->filterValue($p, Condition::fine($v));
        }
    }

    protected function objectizeInt(string $v) {
        if (trim($v) === '') {
            self::debug('oint: required! %s', $v);
            return Condition::poor($this->mapError('required'));
        }
        $v = trim($v);
        $i = intval($v);
        if (''.$i === $v) {
            self::debug('oint: %d', $i);
            return Condition::fine($i);
        } else {
            self::debug('oint: invalid! %s', $v);
            return Condition::poor($this->mapError('invalid'));
        }
    }

    protected function objectizeBool(string $v) {
        $v = trim($v);
        if ($v === '') {
            self::debug('obool: required!');
            return Condition::poor($this->mapError('required'));
        }
        if (strcasecmp($v, 'true') === 0 || strcasecmp($v, 't') === 0 || strcmp($v, '1') === 0 || strcasecmp($v, 'yes') === 0 || strcasecmp($v, 'y') === 0) {
            self::debug('obool: true');
            return Condition::fine(true);
        } else if (strcasecmp($v, 'false') === 0 || strcasecmp($v, 'f') === 0 || strcmp($v, '0') === 0 || strcasecmp($v, 'no') === 0 || strcasecmp($v, 'n') === 0) {
            self::debug('obool: false');
            return Condition::fine(false);
        } else {
            self::debug('obool: invalid! %s', $v);
            return Condition::poor($this->mapError('invalid'));
        }
    }

    protected function objectizeString(string $v) {
        self::debug('ostring: %s', $v);
        return Condition::fine($v);
    }

    protected function objectizeFloat(string $v) {
        $v = trim($v);
        if ($v === '') {
            self::debug('ofloat: required!');
            return Condition::poor($this->mapError('required'));
        }
        if (is_numeric($v)) {
            $f = floatval($v);
            self::debug('ofloat: %f', $f);
            return Condition::fine($f);
        } else {
            self::debug('ofloat: invalid! %s', $v);
            return Condition::poor($this->mapError('invalid'));
        }
    }

    protected function detectMinArity(ReflectionClass $c) {
        if (isset($this->ctrmap[$c->getName()])) {
            $ctr = self::getMethodSpec($this->ctrmap[$c->getName()]);
            return $ctr->getNumberOfRequiredParameters();
        } else {
            $ctr = $c->getConstructor();
            if ($ctr) {
                return $ctr->getNumberOfRequiredParameters();
            } else {
                return 0;
            }
        }
    }

    protected function objectizeClass(ReflectionClass $c, array $v) {
        self::debug('oclass: %s', $c->getName());
        if (isset($this->ctrmap[$c->getName()])) {
            $ctr = self::getMethodSpec($this->ctrmap[$c->getName()]);
            $cd = $this->objectizeMethod($ctr, $v);
            if (!$cd()) {
                return $cd;
            } else {
                $obj = $ctr->invokeArgs(null, $cd->get());
                self::debug('oclass: map %s', get_class($obj));
                return Condition::fine($obj);
            }
        } else {
            $ctr = $c->getConstructor();
            if ($ctr) {
                $cd = $this->objectizeMethod($ctr, $v);
                if (!$cd()) {
                    return $cd;
                } else {
                    $obj = $c->newInstanceArgs($cd->get());
                    self::debug('oclass: ctr %s', get_class($obj));
                    return Condition::fine($obj);
                }
            } else {
                $obj = $c->newInstance();
                self::debug('oclass: default-ctr %s', get_class($obj));
                return Condition::fine($obj);
            }
        }
    }

    protected function validateClass(ReflectionClass $c, array $v) {
        self::debug('vclass: %s', $c->getName());
        if (isset($this->ctrmap[$c->getName()])) {
            $ctr = self::getMethodSpec($this->ctrmap[$c->getName()]);
            $cd = $this->validateMethod($ctr, $v);
            if (!$cd()) {
                return $cd;
            } else {
                self::debug('vclass: map');
                return $cd;
            }
        } else {
            $ctr = $c->getConstructor();
            if ($ctr) {
                $cd = $this->validateMethod($ctr, $v);
                if (!$cd()) {
                    return $cd;
                } else {
                    self::debug('vclass: ctr');
                    return $cd;
                }
            } else {
                self::debug('vclass: default-ctr');
                return Condition::fine(true);
            }
        }
    }

    protected function objectizeAbstractClass($c, $v) {
        if (! isset($v['__selection'])) {
            self::debug('oabstract: selection required!');
            return Condition::poor(['__selection' => $this->mapError('required')]);
        }

        $className = $c->getNamespaceName().'\\'.$v['__selection'];
        if (class_exists($className)) {
            $c = new ReflectionClass($className);
            $cd = $this->objectizeClass($c, $v[$v['__selection']]);
            if (!$cd()) {
                return Condition::poor(['__selection' => $cd->describe()]);
            } else {
                return $cd;
            }
        } else {
            self::debug('oabstract: selection invalid!');
            return Condition::poor(['__selection' => $this->mapError('invalid')]);
        }
    }

    protected function validateAbstractClass($c, $v) {
        $cls = array_keys($v)[0];
        $className = $c->getNamespaceName().'\\'.$cls;
        if (class_exists($className)) {
            $c = new ReflectionClass($className);
            $cd = $this->validateClass($c, $v[$cls]);
            return $cd;
        } else {
            self::debug('vabstract: selection invalid!');
            return Condition::poor($this->mapError('invalid'));
        }
    }

    public function formulize($action, array $args): array {
        $mrefl = self::getMethodSpec($action);
        $form = $this->formulizeMethod($mrefl, $args);
        self::debug('f: count:%d', count($form));
        return $form;
    }

    protected function getGetterName(ReflectionParameter $p): string {
        $c = $p->getDeclaringClass();
        $baseName = ucfirst($p->getName());
        if ($c->hasMethod('get'.$baseName)) {
            return 'get'.$baseName;
        } else if ($c->hasMethod('is'.$baseName)) {
            return 'is'.$baseName;
        } else {
            return null;
        }
    }

    protected function getExtractorName(ReflectionFunctionAbstract $ctr): string {
        if ($ctr->getName() == '__invoke') {
            return 'extract';
        } else {
            return 'extractFor'.ucfirst($ctr->getName());
        }
    }

    protected function formulizeMethod(ReflectionFunctionAbstract $method, $args) {
        $where = $method->getDeclaringClass()->getName() . '->' . $method->getName();
        self::debug('fmethod[%s]: ', $where);
        
        $as = [];
        $ps = $method->getParameters();
        $plen = count($ps);
        for ($pi = 0, $ai = 0; $pi < $plen; $pi++, $ai++) {
            $p = $ps[$pi];
            $name = $p->getName();
            if ($p->isVariadic()) {
                $alen = count($args);
                for (; $ai < $alen; $ai++) {
                    $o = $args[$ai];
                    $a = $this->formulizeParameter($p, $o);
                    if (! is_null($a)) {
                        $as[$ai] = $a;
                    }
                }
            } else {
                $o = $args[$ai];
                $a = $this->formulizeParameter($p, $o);
                if (! is_null($a)) {
                    $as[$pi] = $as[$name] = $a;
                }
            }
        }
        return $as;
    }

    protected function formulizeParameter(ReflectionParameter $p, $o) {
        $pc = $p->getClass();
        $oc = (is_object($o)) ? new ReflectionClass($o) : null;
        if ($pc && $oc && $pc != $oc) {
            $as = [];
            $as['__selection'] = $oc->getShortName();
            $as[$oc->getShortName()] = $this->formulizeClass($oc, $o);
            self::debug('fparameter: abstract %s', $oc->getShortName());
            return $as;
        } else if (is_array($o)) {
            return $this->formulizeArray($o);
        } else if (is_int($o)) {
            self::debug('fparameter: int %d', $o);
            return ''.$o;
        } else if (is_bool($o)) {
            self::debug('fparameter: bool %s', ($o) ? 'true' : 'false');
            return ($o) ? 'true' : 'false';
        } else if (is_string($o)) {
            self::debug('fparameter: string %s', $o);
            return $o;
        } else if (is_float($o)) {
            self::debug('fparameter: float %f', $o);
            return ''.$o;
        } else if (is_null($o)) {
            self::debug('fparameter: null');
            return null;
        } else if ($oc) {
            return $this->formulizeClass($oc, $o);
        } else {
            self::debug('fparameter: unhandled object');
            throw new \LogicException('unhandled object');
        }
    }

    protected function formulizeClass(ReflectionClass $c, $o) {
        if (isset($this->ctrmap[$c->getName()])) {
            $ctr = self::getMethodSpec($this->ctrmap[$c->getName()]);
            $extractor = $this->getExtractorName($ctr);
            $subj = $this->ctrmap[$c->getName()][0];
            $vs = call_user_func([$subj, $extractor], $o);
            $ps = $ctr->getParameters();
            $plen = count($ps);
            $args = [];
            for ($pi = 0; $pi < $plen; $pi++) {
                $p = $ps[$pi];
                $arg = $this->formulizeParameter($p, $vs[$pi]);
                if (! is_null($arg)) {
                    if ($p->isVariadic()) {
                        $args[$pi] = $arg;
                        $pi--;
                    } else {
                        $args[$pi] = $args[$p->getName()] = $arg; 
                    }
                }
            }
            return $args;
        } else {
            $ctr = $c->getConstructor();
            $ps = $ctr->getParameters();
            $plen = count($ps);
            $args = [];
            for ($pi = 0; $pi < $plen; $pi++) {
                $p = $ps[$pi];
                $name = $p->getName();
                $getter = $this->getGetterName($p);
                if (! $getter) {
                    continue;
                }
                if ($ctr->getDeclaringClass() != $c) {
                    $subj = $this->ctrmap[$c->getName()][0];
                    $v = call_user_func([$subj, $getter], $o);
                } else {
                    $v = call_user_func([$o, $getter]);
                }
                $a = $this->formulizeParameter($p, $v);
                if ($p->isVariadic()) {
                    foreach ($a as $e) {
                        $args[$pi++] = $e;
                    }
                } else {
                    if (! is_null($a)) {
                        $args[$pi] = $args[$name] = $a;
                    }
                }
            }
            return $args;
        }
    }

    protected function formulizeArray($o) {
        $as = [];
        foreach ($o as $k => $e) {
            if (is_array($e)) {
                $as[$k] = $this->formulizeArray($e);
            } else if (is_int($e)) {
                $as[$k] = ''.$e;
            } else if (is_bool($e)) {
                $as[$k] = ($e) ? 'true' : 'false';
            } else if (is_string($e)) {
                $as[$k] = $e;
            } else if (is_float($e)) {
                $as[$k] = ''.$e;
            } else if (is_null($e)) {
                /* DO NOTHING */
            } else if (is_object($e)) {
                $as[$k] = $this->formulizeClass(new ReflectionClass($e), $e);
            }  else {
                self::debug('farray: unhandled object');
                throw new \LogicException('unhandled object');
            }
        }
        self::debug('farray: %d values', count($as));
        return $as;
    }
}