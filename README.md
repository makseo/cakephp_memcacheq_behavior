CakePHP MemcacheQ Behavior
===

If you are working with Queue Service MemcacheQ (http://memcachedb.org/memcacheq/) in your CakePHP project, you might find this bihavior

It`s used like any other Behavior in CakePHP

```php
public $actsAs = array('Memcacheq');
```

To change default settings

```php
$settings = array
(
    'hostname' => '127.0.0.1',
    'port' => 22222,
    'timeout' => 10,
    'pconnect' => true,
);

$this->Model->Behaviors->Memcacheq->setup($this->Model, array('pconnect' => true));
```