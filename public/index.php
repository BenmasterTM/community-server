<?php

    use Phalcon\Mvc\Micro;
    use Phalcon\Di\FactoryDefault;
    use Phalcon\Loader;
    use Phalcon\Config;
    
    use Phalcon\Cache\Backend\Redis;
    use Phalcon\Cache\Backend\Apc;
    use Phalcon\Cache\Backend\Memcache;
    use Phalcon\Cache\Backend\Libmemcached;
    use Phalcon\Cache\Frontend\Data as FrontData;
    
    
    define('BASE_PATH', dirname(__DIR__));
    
    // new loader instance
    $loader = new Loader();
    
    $loader->registerNamespaces(
        [
            'Controllers' => BASE_PATH . '/controllers/'
        ]
    );
      
    $loader->register();
    
    // new Dependency injector instance
    $di = new FactoryDefault();
    
    // Set up the config loader
    $di->set(
        'config',
        function () {
            return  new \Phalcon\Config\Adapter\Php(BASE_PATH . '/config.php');
        }
    );
        
    // Set up the database service
    $di->set(
        'db',
        function () {            
            return new \Phalcon\Db\Adapter\Pdo\Mysql(
                [
                    'host'     => $this->getShared('config')->database->host,
                    'username' => $this->getShared('config')->database->username,
                    'password' => $this->getShared('config')->database->password,
                    'dbname'   => $this->getShared('config')->database->dbname,
                    'options'  => [
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
                        PDO::ATTR_CASE               => PDO::CASE_LOWER,
                    ]
                ]
            ); 
        }
    );
    
    // Set up request service
    $di->set(
        'request',
        function () {
            return new \Phalcon\Http\Request(); 
        }
    );
    
    $di->set(
        'cache',
        function () {
            
            // Cache data for 2 days
            $frontCache = new FrontData(
                [
                    "lifetime" => $this->getShared('config')->cache->lifetime
                ]
            );
            
            switch (strtolower($this->getShared('config')->cache->engine)) {
                
                case "redis":
                    
                    $cache = new Phalcon\Cache\Backend\Redis(
                        $frontCache,
                        [
                            "host"       => $this->getShared('config')->cache->host,
                            "port"       => $this->getShared('config')->cache->port,
                            "auth"       => $this->getShared('config')->cache->auth,
                            "persistent" => $this->getShared('config')->cache->persistent,
                            "index"      => $this->getShared('config')->cache->index,
                        ]
                    );
                    
                break;
                
                case "apc":
                    
                    $cache = new Phalcon\Cache\Backend\Apc(
                        $frontCache,
                        [
                            "prefix" => $this->getShared('config')->cache->prefix,
                        ]
                    );
                    
                break;
                
                case "memcache":
                    
                    $cache = new Phalcon\Cache\Backend\Memcache(
                        $frontCache,
                        [
                            "host"       => $this->getShared('config')->cache->host,
                            "port"       => $this->getShared('config')->cache->port,
                            "persistent" => $this->getShared('config')->cache->persistent,
                        ]
                    );
                    
                break;
                
                case "libmemcached":
                default:
                    
                    $cache = new Phalcon\Cache\Backend\Libmemcached(
                        $frontCache,
                        [
                            "servers" => [
                                [
                                    "host"   => $this->getShared('config')->cache->host,
                                    "port"   => $this->getShared('config')->cache->port,
                                    "weight" => $this->getShared('config')->cache->weight,
                                ],
                            ],
                            "client" => [
                                \Memcached::OPT_HASH       => \Memcached::HASH_MD5,
                                \Memcached::OPT_PREFIX_KEY => $this->getShared('config')->cache->prefix.".",
                            ],
                            "statsKey" => "_PHCM"
                        ]
                    );
                    
                break;
            
            }
            
            return $cache;
        }
    );   

    // Create and bind the DI to the application
    $app = new Micro($di);
    
    // Declare new instance of the RestController
    $RestController = new Controllers\RestController();
    
    // GET /library ?page (without page param, return only the total count, NOT limit)
    $app->get('/library', [
        $RestController,
        "libraryAction"
    ]);

    // GET /library/search ?query&tags&languages&page (NOT limit)
    $app->get('/library/search', [
        $RestController,
        "librarySearchAction"
    ]);
    
    // POST /library/announce (array of JSON of new products, body POST contain the JSON ARRAY)
    $app->post('/library/announce', [
        $RestController,
        "libraryAnnounce"
    ]);
    
    $app->notFound(function () use ($app) {
        $app->response->setStatusCode(404, "Not Found")->sendHeaders();
        echo 'not implemented';
    });

 
    $app->handle();
    


