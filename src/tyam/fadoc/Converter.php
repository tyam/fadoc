<?php

namespace tyam\fadoc;

use tyam\condition\Condition;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;


class Converter implements LoggerAwareInterface 
{
    
    public const REPAIR = 1;
    public const LENIENT = 2;

    private $ctrmap;

    private $logger;

    /**
     * [fully-qualified-class-name => constructor-specifier, ...]
     * constructor-specifier := [class:string, method:string]
     *                        | [class:object, method:string]
     *                        | class:string  -- same as [class:string, "__invoke"]
     *                        | class:object  -- same as [class:object, "__invoke"]
     */
    public function __construct(array $ctrmap = [], LoggerInterface $logger = null) 
    {
        $this->ctrmap = $ctrmap;
        if (is_null($logger)) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    public function setConstructor($cls, $method) 
    {
        $this->ctrmap[$cls] = $method;
    }

    public function getConstructor($cls) 
    {
        return $this->ctrmap[$cls];
    }

    protected function mapError($what) 
    {
        return $what;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    protected static function testFlag($flags, $flag): bool 
    {
        return (($flags & $flag) != 0);
    }

    protected static function actionToString($action): string 
    {
        if (is_array($action)) {
            if (! is_string($action[0])) {
                $action[0] = get_class($action[0]);
            }
            return $action[0] . '::' . $action[1];
        } else if (! is_string($action)) {
            return $action . '';
        } else {
            return $action;
        }
    }

    protected static function reflToString($refl): string
    {
        if ($refl instanceof \ReflectionClass) {
            return $refl->getShortName();
        } else if ($refl instanceof \ReflectionMethod) {
            return self::reflToString($refl->getDeclaringClass()) . '::' . $refl->getName();
        } else if ($refl instanceof \ReflectionParameter) {
            return $refl->getName();
        } else {
            return $refl->toString();
        }
    }

    protected static function getMethodSpec($action): ReflectionFunctionAbstract 
    {
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

    public function objectize($action, $input, $flags = 0): Condition 
    {
        $this->logger->debug('objectize: starts for {action}.', ['action' => self::actionToString($action)]);
        $mrefl = self::getMethodSpec($action);
        $cd = $this->objectizeMethod($mrefl, $input, $flags);
        $this->logger->debug('objectize: ends with {cnt} errors.', ['cnt' => count($cd->describe())]);
        return $cd;
    }

    public function validate($action, $input, $flags = 0): Condition 
    {
        $this->logger->debug('validate: starts for {action}.', ['action' => self::actionToString($action)]);
        $mrefl = self::getMethodSpec($action);
        $cd = $this->validateMethod($mrefl, $input, $flags);
        $this->logger->debug('validate: ends with {cnt} errors.', ['cnt' => count($cd->describe())]);
        return $cd;
    }

    protected function objectizeMethod(ReflectionFunctionAbstract $method, $input, $flags) 
    {
        $this->logger->debug('objectizeMethod: starts for {method}.', ['method' => self::reflToString($method)]);
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
            $this->logger->debug("objectizeMethod: {method}, $pi, $ai, $n", ['method' => self::reflToString($method)]);
            if (is_int($v) || is_bool($v) || is_float($v)) {
                $v = "".$v;
            }
            $cd = $this->objectizeParameter($p, $v, $flags);
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

    protected function validateMethod(ReflectionFunctionAbstract $method, $input, $flags) 
    {
        $this->logger->debug('validateMethod: {method}', ['method' => self::reflToString($method)]);
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
        $v = $input[$key];
        if (is_int($v) || is_bool($v) || is_float($v)) {
            $v = "".$v;
        }
        $this->logger->debug("validateMethod: $pi, {p}", ['p' => $p]);
        return $this->validateParameter($p, $v, $flags);
    }

    protected function filterValue(ReflectionParameter $p, $cd) 
    {
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

    protected function getValidatorName(ReflectionParameter $p) :string 
    {
        $m = $p->getDeclaringFunction();
        if ($m->isConstructor()) {
            return "validate".ucfirst($p->getName());
        } else {
            return "validate".ucfirst($p->getName())."For".ucfirst($m->getName());
        }
    }

    protected function objectizeParameter(ReflectionParameter $p, $v, $flags) 
    {
        if ($p->getClass()) {
            // class specified
            $c = $p->getClass();
            if (is_null($v)) {
                if ($p->isDefaultValueAvailable()) {
                    $this->logger->debug('objectizeParameter: class defaultValue');
                    return Condition::fine($p->getDefaultValue());
                } else if ($this->detectMinArity($c) == 0) {
                    // rescue. Classes with 0 parameters can be omitted.
                    $this->logger->debug('objectizeParameter: rescue (Class with 0 params)');
                    $v = [];
                } else {
                    if (self::testFlag($flags, self::REPAIR)) {
                        $v = [];
                    } else {
                        $this->logger->debug('objectizeParameter: class arrayRequired!');
                        return Condition::poor($this->mapError('arrayRequired'));
                    }
                }
            } else if (is_string($v)) {
                if (self::testFlag($flags, self::REPAIR)) {
                    $v2 = [0 => $v];
                    $v = $v2;
                } else {
                    $this->logger->debug('objectizeParameter: class arrayRequired!');
                    return Condition::poor($this->mapError('arrayRequired'));
                }
            }
            if ($c->isAbstract() || $c->isInterface()) {
                return $this->objectizeAbstractClass($c, $v, $flags);
            } else {
                return $this->objectizeClass($c, $v, $flags);
            }
        } else if ($p->getType() == 'array') {
            // array specified
            if (is_null($v)) {
                if ($p->isDefaultValueAvailable()) {
                    $this->logger->debug('objectizeParameter: array defaultValue');
                    return Condition::fine($p->getDefaultValue());
                } else {
                    // rescue. Arrays with 0 elements can be omitted.
                    $this->logger->debug('objectizeParameter: rescue (array with 0 elems)');
                    $v = [];
                }
            }
            if (is_array($v)) {
                $this->logger->debug('objectizeParameter: array asis');
                return $this->filterValue($p, Condition::fine($v));
            } else {
                // $v is not null nor an array, so that is a string.
                if (self::testFlag($flags, self::REPAIR)) {
                    $this->logger->debug('objectizeParameter: repair string to array');
                    return $this->filterValue($p, Condition::fine([$v]));
                } else {
                    $this->logger->debug('objectizeParameter: array arrayRequired!');
                    return Condition::poor($this->mapError('arrayRequired'));
                }
            }
        } else if ($p->hasType()) {
            // scalar type specified
            if (is_array($v)) {
                if (self::testFlag($flags, self::REPAIR) && count($v) > 0 && is_string($v[0])) {
                    $this->logger->debug('objectizeParameter: repair array to string');
                    switch ($p->getType().'') {
                        case 'string': return $this->filterValue($p, $this->objectizeString($v[0], $flags));
                        case 'int':    return $this->filterValue($p, $this->objectizeInt($v[0], $flags));
                        case 'float':  return $this->filterValue($p, $this->objectizeFloat($v[0], $flags));
                        case 'bool':   return $this->filterValue($p, $this->objectizeBool($v[0], $flags));
                    }
                } else {
                    $this->logger->debug('objectizeParameter: scalar scalarRequired!');
                    return Condition::poor($this->mapError('scalarRequired'));
                }
            } else if (is_null($v)) {
                if ($p->isDefaultValueAvailable()) {
                    $this->logger->debug('objectizeParameter: scalar defaultValue');
                    return Condition::fine($p->getDefaultValue());
                } else if ($p->getType() == 'bool') {
                    // rescue. Empty value for bool is interpreted as false; for checkbox.
                    $this->logger->debug('objectizeParameter: rescue (empty to false)');
                    return Condition::fine(false);
                } else {
                    $this->logger->debug('objectizeParameter: scalar valueRequired!');
                    return Condition::poor($this->mapError('required'));
                }
            } else {
                switch ($p->getType().'') {
                    case 'string': return $this->filterValue($p, $this->objectizeString($v, $flags));
                    case 'int':    return $this->filterValue($p, $this->objectizeInt($v, $flags));
                    case 'float':  return $this->filterValue($p, $this->objectizeFloat($v, $flags));
                    case 'bool':   return $this->filterValue($p, $this->objectizeBool($v, $flags));
                }
            }
        } else {
            // not specified
            $this->logger->debug('objectizeParameter: any asis');
            return $this->filterValue($p, Condition::fine($v));
        }
    }

    protected function validateParameter(ReflectionParameter $p, $v, $flags) 
    {
        if ($p->getClass()) {
            // class specified
            $c = $p->getClass();
            if (is_string($v)) {
                if (self::testFlag($flags, self::REPAIR)) {
                    $this->logger->debug('validateParameter: repair string to array');
                    $v2 = [0 => $v];
                    $v = $v2;
                } else {
                    $this->logger->debug('validateParameter: class arrayRequired!');
                    return Condition::poor($this->mapError('arrayRequired'));
                }
            }
            if ($c->isAbstract() || $c->isInterface()) {
                return $this->validateAbstractClass($c, $v, $flags);
            } else {
                return $this->validateClass($c, $v, $flags);
            }
        } else if ($p->getType() == 'array') {
            // array specified
            if (is_array($v)) {
                $this->logger->debug('validateParameter: array asis');
                return $this->filterValue($p, Condition::fine($v));
            } else {
                $this->logger->debug('validateParameter: array arrayRequired!');
                return Condition::poor($this->mapError('arrayRequired'));
            }
        } else if ($p->hasType()) {
            // scalar type specified
            if (is_array($v)) {
                if (self::testFlag($flags, self::REPAIR) && count($v) > 0 && is_string($v[0])) {
                    $this->logger->debug('validateParameter: repair array to string');
                    switch ($p->getType().'') {
                        case 'string': return $this->filterValue($p, $this->objectizeString($v[0], $flags));
                        case 'int':    return $this->filterValue($p, $this->objectizeInt($v[0], $flags));
                        case 'float':  return $this->filterValue($p, $this->objectizeFloat($v[0], $flags));
                        case 'bool':   return $this->filterValue($p, $this->objectizeBool($v[0], $flags));
                    }
                } else {
                    $this->logger->debug('validateParameter: scalar scalarRequired!');
                    return Condition::poor($this->mapError('scalarRequired'));
                }
            } else {
                switch ($p->getType().'') {
                    case 'string': return $this->filterValue($p, $this->objectizeString($v, $flags));
                    case 'int':    return $this->filterValue($p, $this->objectizeInt($v, $flags));
                    case 'float':  return $this->filterValue($p, $this->objectizeFloat($v, $flags));
                    case 'bool':   return $this->filterValue($p, $this->objectizeBool($v, $flags));
                }
            }
        } else {
            // not specified
            $this->logger->debug('validateParameter: any asis');
            return $this->filterValue($p, Condition::fine($v));
        }
    }

    protected function objectizeInt(string $v, $flags) 
    {
        if (trim($v) === '') {
            $this->logger->debug("objectizeInt: required! $v");
            return Condition::poor($this->mapError('required'));
        }
        $v = trim($v);
        if (self::testFlag($flags, self::LENIENT) && is_numeric($v)) {
            $v = ''.intval($v);
        }
        $i = intval($v);
        if (''.$i === $v) {
            $this->logger->debug("objectizeInt: $i");
            return Condition::fine($i);
        } else {
            $this->logger->debug("objectizeInt: invalid! $v");
            return Condition::poor($this->mapError('invalid'));
        }
    }

    protected function objectizeBool(string $v, $flags) 
    {
        $v = trim($v);
        if ($v === '') {
            $this->logger->debug('objectizeBool: required!');
            return Condition::poor($this->mapError('required'));
        }
        if (self::testFlag($flags, self::LENIENT)) {
            if (strcasecmp($v, 't') === 0 || strcmp($v, '1') === 0 || strcasecmp($v, 'yes') === 0 || strcasecmp($v, 'y') === 0) {
                $this->logger->debug("objectizeBool: promote $v to true");
                $v = 'true';
            } else if (strcasecmp($v, 'f') === 0 || strcmp($v, '0') === 0 || strcasecmp($v, 'no') === 0 || strcasecmp($v, 'n') === 0) {
                $this->logger->debug("objectizeBool: promote $v to false");
                $v = 'false';
            }
        }
        if (strcasecmp($v, 'true') === 0) {
            $this->logger->debug("objectizeBool: true");
            return Condition::fine(true);
        } else if (strcasecmp($v, 'false') === 0) {
            $this->logger->debug("objectizeBool: false");
            return Condition::fine(false);
        } else {
            $this->logger->debug("objectizeBool: invalid! $v");
            return Condition::poor($this->mapError('invalid'));
        }
    }

    protected function objectizeString(string $v, $flags) 
    {
        $this->logger->debug("objectizeString: $v");
        return Condition::fine($v);
    }

    protected function objectizeFloat(string $v, $flags) 
    {
        $v = trim($v);
        if ($v === '') {
            $this->logger->debug('objectizeFloat: required!');
            return Condition::poor($this->mapError('required'));
        }
        if (is_numeric($v)) {
            $f = floatval($v);
            $this->logger->debug('objectizeFloat: {f}', ['f' => $f]);
            return Condition::fine($f);
        } else {
            $this->logger->debug("objectizeFloat: invalid! $v");
            return Condition::poor($this->mapError('invalid'));
        }
    }

    protected function detectMinArity(ReflectionClass $c) 
    {
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

    protected function objectizeClass(ReflectionClass $c, array $v, $flags) 
    {
        if (isset($this->ctrmap[$c->getName()])) {
            $ctr = self::getMethodSpec($this->ctrmap[$c->getName()]);
            $this->logger->debug("objectizeClass: map constructor of {c}", ['c' => self::reflToString($c)]);
            $cd = $this->objectizeMethod($ctr, $v, $flags);
            if (!$cd()) {
                return $cd;
            } else {
                $obj = $ctr->invokeArgs(null, $cd->get());
                return Condition::fine($obj);
            }
        } else {
            $ctr = $c->getConstructor();
            if ($ctr) {
                $this->logger->debug("objectizeClass: construct {c}", ['c' => self::reflToString($c)]);
                $cd = $this->objectizeMethod($ctr, $v, $flags);
                if (!$cd()) {
                    return $cd;
                } else {
                    $obj = $c->newInstanceArgs($cd->get());
                    return Condition::fine($obj);
                }
            } else {
                $this->logger->debug("objectizeClass: instantiate {c}", ['c' => self::reflToString($c)]);
                $obj = $c->newInstance();
                return Condition::fine($obj);
            }
        }
    }

    protected function validateClass(ReflectionClass $c, array $v, $flags) 
    {
        if (isset($this->ctrmap[$c->getName()])) {
            $ctr = self::getMethodSpec($this->ctrmap[$c->getName()]);
            $this->logger->debug("validateClass: map constructor of {c}", ['c' => self::reflToString($c)]);
            $cd = $this->validateMethod($ctr, $v, $flags);
            if (!$cd()) {
                return $cd;
            } else {
                return $cd;
            }
        } else {
            $ctr = $c->getConstructor();
            if ($ctr) {
                $this->logger->debug("validateClass: construct {c}", ['c' => self::reflToString($c)]);
                $cd = $this->validateMethod($ctr, $v, $flags);
                if (!$cd()) {
                    return $cd;
                } else {
                    return $cd;
                }
            } else {
                $this->logger->debug("validateClass: instantiate {c}", ['c' => self::reflToString($c)]);
                return Condition::fine(true);
            }
        }
    }

    protected function objectizeAbstractClass($c, $v, $flags) 
    {
        if (! isset($v['__selection'])) {
            $this->logger->debug('objectizeAbstractClass: selection required!');
            return Condition::poor(['__selection' => $this->mapError('required')]);
        }

        $className = $c->getNamespaceName().'\\'.$v['__selection'];
        if (class_exists($className)) {
            $ci = new ReflectionClass($className);
            $this->logger->debug("objectizeAbstractClass: resolve {c} to {ci}", ['c' => self::reflToString($c), 'ci' => self::reflToString($ci)]);
            $cd = $this->objectizeClass($ci, $v[$v['__selection']], $flags);
            if (!$cd()) {
                return Condition::poor(['__selection' => $cd->describe()]);
            } else {
                return $cd;
            }
        } else {
            $this->logger->debug("objectizeAbstractClass: selection invalid! ($className)");
            return Condition::poor(['__selection' => $this->mapError('invalid')]);
        }
    }

    protected function validateAbstractClass($c, $v, $flags) 
    {
        $cls = array_keys($v)[0];
        $className = $c->getNamespaceName().'\\'.$cls;
        if (class_exists($className)) {
            $ci = new ReflectionClass($className);
            $this->logger->debug("validateAbstractClass: resolve {c} to {ci}", ['c' => self::reflToString($c), 'ci' => self::reflToString($ci)]);
            $cd = $this->validateClass($ci, $v[$cls], $flags);
            return $cd;
        } else {
            $this->logger->debug("validateAbstractClass: selection invalid! ($className)");
            return Condition::poor($this->mapError('invalid'));
        }
    }

    public function formulize($action, array $args): array 
    {
        $this->logger->debug('formulize: starts for {action}.', ['action' => self::actionToString($action)]);
        $mrefl = self::getMethodSpec($action);
        $form = $this->formulizeMethod($mrefl, $args);
        $this->logger->debug('formulize: end.');
        return $form;
    }

    protected function getGetterName(ReflectionParameter $p): string 
    {
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

    protected function getExtractorName(ReflectionFunctionAbstract $ctr): string 
    {
        if ($ctr->getName() == '__invoke') {
            return 'extract';
        } else {
            return 'extractFor'.ucfirst($ctr->getName());
        }
    }

    protected function formulizeMethod(ReflectionFunctionAbstract $method, $args) 
    {
        $this->logger->debug("formulizeMethod: starts for {method}", ['method' => self::reflToString($method)]);
        
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
                    $this->logger->debug("formulizeMethod: {method}, $pi, $ai, $name", ['method' => self::reflToString($method)]);
                    $a = $this->formulizeParameter($p, $o);
                    if (! is_null($a)) {
                        $as[$ai] = $a;
                    }
                }
            } else {
                $o = $args[$ai];
                $this->logger->debug("formulizeMethod: {method}, $pi, $ai, $name", ['method' => self::reflToString($method)]);
                $a = $this->formulizeParameter($p, $o);
                if (! is_null($a)) {
                    $as[$pi] = $as[$name] = $a;
                }
            }
        }
        return $as;
    }

    protected function formulizeParameter(ReflectionParameter $p, $o) 
    {
        $pc = $p->getClass();
        $oc = (is_object($o)) ? new ReflectionClass($o) : null;
        if ($pc && $oc && $pc != $oc) {
            $as = [];
            $as['__selection'] = $oc->getShortName();
            $as[$oc->getShortName()] = $this->formulizeClass($oc, $o);
            $this->logger->debug("formulizeParameter: $oc as abstract $pc");
            return $as;
        } else if (is_array($o)) {
            return $this->formulizeArray($o);
        } else if (is_int($o)) {
            $this->logger->debug("formulizeParameter: int($o)");
            return ''.$o;
        } else if (is_bool($o)) {
            $this->logger->debug("formulizeParameter: bool($o)");
            return ($o) ? 'true' : 'false';
        } else if (is_string($o)) {
            $this->logger->debug("formulizeParameter: string($o)");
            return $o;
        } else if (is_float($o)) {
            $this->logger->debug("formulizeParameter: float($o)");
            return ''.$o;
        } else if (is_null($o)) {
            $this->logger->debug("formulizeParameter: null");
            return null;
        } else if ($oc) {
            return $this->formulizeClass($oc, $o);
        } else {
            throw new \LogicException('unhandled object');
        }
    }

    protected function formulizeClass(ReflectionClass $c, $o) 
    {
        if (isset($this->ctrmap[$c->getName()])) {
            $ctr = self::getMethodSpec($this->ctrmap[$c->getName()]);
            $this->logger->debug("formulizeClass: map constructor of {c}", ['c' => self::reflToString($c)]);
            $extractor = $this->getExtractorName($ctr);
            $subj = $this->ctrmap[$c->getName()][0];
            $vs = call_user_func([$subj, $extractor], $o);
            $ps = $ctr->getParameters();
            $plen = count($ps);
            $args = [];
            for ($pi = 0; $pi < $plen; $pi++) {
                $p = $ps[$pi];
                $this->logger->debug("formulizeClass: {ctr}, $pi", ['ctr' => self::reflToString($ctr)]);
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
        } else if (is_null($c->getConstructor())) {
            return [];
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
                $this->logger->debug("formulizeClass: {c}, $pi, $name", ['c' => self::reflToString($c)]);
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

    protected function formulizeArray($o) 
    {
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
                throw new \LogicException('unhandled object');
            }
        }
        $this->logger->debug("formulizeArray: {c} values.", ['c' => count($as)]);
        return $as;
    }
}