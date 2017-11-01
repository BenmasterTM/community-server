<?php

// db access: https://docs.phalconphp.com/en/3.2/db-layer
// request: https://olddocs.phalconphp.com/en/3.0.3/api/Phalcon_Http_Request.html

namespace Controllers;

use Phalcon\Mvc\Controller;
use Phalcon\Http\Response;

class RestController extends Controller
{
    
    private $resultPerPage = 100;
    
    // GET /library ?page (without page param, return only the total count, NOT limit)
    public function libraryAction() {
        
        $cache_token = md5(print_r($this->request->getQuery(), true));
        $cache = $this->checkCache($cache_token);
        if ($cache !== null) {
            $response = new Response();
            $response->setJsonContent( $cache );
            return $response;
        }
                
        $results = [];
                
        if (empty($this->request->get('page'))) {

            // set cache working indicator
            if ($this->config->options->enable_cache === true) {
                $this->cache->save($cache_token, ['time' => time(), 'working' =>  true]);
            }
            
            $result = $this->db->query( 'SELECT count(hash) AS count FROM torrents' );
            $row = $result->fetch();
            $json = ['state' => 'ok', 'count' => intval($row['count'])];
        
        } else {
            
            $page = (!empty($this->request->get('page')) && is_numeric($this->request->get('page')) && $this->request->get('page') > 0) ? intval($this->request->get('page')) : 1;
            $limit = $this->resultPerPage ;
            $offset = ($page * $limit) - $limit;

            // set cache working indicator
            if ($this->config->options->enable_cache === true) {
                $this->cache->save($cache_token, ['time' => time(), 'working' =>  true]);
            }
                        
            $result = $this->db->query( 'SELECT id, hash, name, magnet, description, tags, languages, date, metadata FROM torrents ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset );
            while ($row = $result->fetch()) {
                
                $results[] = [
                    'id' => $row['id'],
                    'hash' => $row['hash'],
                    'name' => $row['name'],
                    'magnet' => $row['magnet'],
                    'description' => $row['description'],
                    'tags' => json_decode($row['tags']),
                    'languages' => json_decode($row['languages']),
                    'date' => $row['date'],
                    'metadata' => json_decode($row['metadata'])
                ];
            }

            $result = $this->db->query( 'SELECT count(hash) AS count FROM torrents' );
            $row = $result->fetch();
            
            $json = ['state' => 'ok', 'rows' => $results, 'total' => intval($row['count']), 'limit' => $this->resultPerPage, 'page' => $page];
            
        }
        
        // set cache value if enabled
        if ($this->config->options->enable_cache === true) {
            $this->cache->save($cache_token, ['time' => time(), 'working' => false, 'data' => $json]);
        }
        
        // create a response
        $response = new Response();
        $response->setJsonContent( $json );

        return $response;
    }

    
    // GET /library/search ?query&tags&languages&page (NOT limit)
    public function librarySearchAction() {
        
        $cache_token = md5(print_r($this->request->getQuery(), true));
        $cache = $this->checkCache($cache_token);
        if ($cache !== null) {
            $response = new Response();
            $response->setJsonContent( $cache );
            return $response;
        }
       
        $options = [];
        $query = [];
        $params = [];
        
        $page = (!empty($this->request->get('page')) && is_numeric($this->request->get('page')) && $this->request->get('page') > 0) ? $this->request->get('page') : 1;
        $limit = $this->resultPerPage ;
        $offset = ($page * $limit) - $limit;
        
        $options['id'] = (!empty($this->request->get('id'))) ? explode(',', $this->request->get('id')) : null;
        $options['hash'] = (!empty($this->request->get('hash'))) ? explode(',', $this->request->get('hash')) : null;
        $options['tags'] = (!empty($this->request->get('tags'))) ? explode(',', $this->request->get('tags')) : null;
        $options['languages'] = (!empty($this->request->get('languages'))) ? explode(',', $this->request->get('languages')) : null;
        $options['search'] = $this->request->get('search');
                
        if (!empty($options['id'])) {
            $query[] = ("id IN (" . implode(',', array_fill(0, count($options['id']), '?' )) . ")");
            for ($i=0; $i<count($options['id']); $i++) {
                $params[] = $options['id'][$i];
            }
        }
        
        if (!empty($options['hash'])) {
        
            if (count($options['hash']) !== 1) {
                $query[] = ("hash IN (" . implode(',', array_fill(0, count($options['hash']), '?' )) . ")");
                for ($i=0; $i<count($options['hash']); $i++) {
                    $params[] = $options['hash'][$i];
                }
            } else {
                $query[] = "hash = ?";
                $params[] = $options['hash'][0];
            }
            
        }
        
        if (!empty($options['search'])) {
            $query[] = ("(name LIKE ? OR description LIKE ?)");
            $params[] = ('%' . $options['search'] . '%');
            $params[] = ('%' . $options['search'] . '%');
            /*$query[] = " MATCH (name, description) AGAINST(? IN BOOLEAN MODE) ";
            $params[] = ('"' . $options['search'] . '"');*/
        }

        
        if (!empty($options['tags'])) {
            $q = [];
            for ($i=0; $i<count($options['tags']); $i++) {
                $params[] = '%"' . $options['tags'][$i] . '"%';
                $q[] = "tags LIKE ? ";
            }
            $query[] = "(" . implode(' OR ', $q) . ")";
        }

        if (!empty($options['languages'])) {
            $q = [];
            for ($i=0; $i<count($options['languages']); $i++) {
                $params[] = '%"' . $options['languages'][$i] . '"%';
                $q[] = "languages LIKE ? ";
            }
            $query[] = "(" . implode(' OR ', $q) . ")";
        }
        
        $queryString = (count($query) > 0) ? "WHERE " . implode(' AND ', $query) : '';
        $querySelect = "SELECT id, hash, name, magnet, description, tags, languages, date, metadata FROM torrents " . $queryString . " ORDER BY id DESC";
        //$querySelect = "SELECT t.id AS id, t.hash AS hash, t.name AS name, t.magnet AS magnet, t.description AS description, t.tags AS tags, t.languages AS languages, t.date AS date, t.metadata AS metadata FROM (SELECT torrents.id FROM torrents " . $queryString . " ORDER BY torrents.id DESC) tf JOIN torrents t ON t.id = tf.id";
        $queryLimit = " LIMIT " . $limit . " OFFSET " . $offset;
        
        //print_r($querySelect) . "\n"; print_r($queryLimit) . "\n"; print_r($params) . "\n"; die();
        
        $JSON = [ 'total' => 0, 'limit' => $limit, 'page' => $page, 'rows' => [], 'state' => 'ok' ];
        
        // if token exists, return in the results
        if (!empty($this->request->get('token'))) {
            $JSON['token'] = $this->request->get('token');
        }

        // set cache working indicator
        if ($this->config->options->enable_cache === true) {
            $this->cache->save($cache_token, ['time' => time(), 'working' =>  true]);
        }
        
        // note: this slow down the query result as hell, can be a good idea store this in cache, but.. umm.. enable cache and is fine, no?
        $result = $this->db->query( "SELECT count(id) as count FROM torrents " . $queryString, $params );
        $row = $result->fetch();
        $JSON['total'] = intval($row['count']);
        
        $result = $this->db->query($querySelect . $queryLimit, $params);
        while ($row = $result->fetch()) {
            
            $JSON['rows'][] = [
                'id' => $row['id'],
                'hash' => $row['hash'],
                'name' => $row['name'],
                'magnet' => $row['magnet'],
                'description' => $row['description'],
                'tags' => json_decode($row['tags']),
                'languages' => json_decode($row['languages']),
                'date' => $row['date'],
                'metadata' => json_decode($row['metadata'])
            ];
            
        }            
        
        // set cache value if enabled
        if ($this->config->options->enable_cache === true) {
            $this->cache->save($cache_token, ['time' => time(), 'working' =>  false, 'data' => $JSON]);
        }
        
        // create a response
        $response = new Response();
        $response->setJsonContent( $JSON );

        return $response;
        
    }
    
    
    // POST /library/announce (array of JSON of new products, body POST contain the JSON ARRAY)
    public function libraryAnnounce() {
                
        // if is configured, disable the announce returning state: disabled
        if ($this->config->options->enable_announce !== true) {
            
            $JSON = [ 'state' => 'disabled', 'message' => 'endpoint disabled' ];
            
            $response = new Response();
            $response->setJsonContent( $JSON );
            $response->setStatusCode (405);
            
            return $response;
        }
        
        $json = $this->request->getJsonRawBody(true);
        
        if ($json) {
            
            // if the user send many torrents, do in blocks not all at same time.. im lacy for now prevent flood
            if (count($json) > 200) {
                
                $JSON = [ 'state' => 'error', 'message' => 'to many torrents' ];
                
                $response = new Response();
                $response->setJsonContent( $JSON );
                
                return $response;
            }
            
            
            $inserts = [];
            $hashes_found = [];
            
            // get all torrent hashes
            $hashes = [];
            for ($i=0; $i<count($json); $i++) {
                if (!empty($json[$i]['hash'])) {
                    $hashes[] = $json[$i]['hash'];
                }
            }
            
            // check that are not inserted already 
            $result = $this->db->query("SELECT id, hash from torrents WHERE hash IN (" . implode(',', array_fill(0, count($hashes), '?') ) . ")", $hashes);
            while ($row = $result->fetch()) {
                $hashes_found[] = $row['hash'];
            }
                        
            // try to insert every 
            for ($i=0; $i<count($json); $i++) {
                
                $validate = true;
                
                // validate metadata JSON
                $metadata = (!empty($json[$i]['metadata'])) ? json_encode($json[$i]['metadata']) : '{}' ;
                
                $json[$i]['hash'] = (!empty($json[$i]['hash'])) ? $json[$i]['hash'] : null ;
                $json[$i]['name'] = (!empty($json[$i]['name'])) ? $json[$i]['name'] : null ;
                $json[$i]['description'] = (!empty($json[$i]['description'])) ? $json[$i]['description'] : null ;
                $json[$i]['tags'] = (!empty($json[$i]['tags'])) ? json_encode($json[$i]['tags']) : json_encode([]) ;
                $json[$i]['languages'] = (!empty($json[$i]['languages'])) ? json_encode($json[$i]['languages']) : json_encode([]) ;
                $json[$i]['magnet'] = (!empty($json[$i]['magnet'])) ? $json[$i]['magnet'] : null ;
                $json[$i]['date'] = (!empty($json[$i]['date'])) ? $json[$i]['date'] : null ;
                $json[$i]['metadata'] = ($metadata !== false) ? $metadata : '{}' ;
                                
                if (preg_match('/^[A-Za-f0-9]{6,60}$/i', $json[$i]['hash']) == false) { $validate = false; }
                if (preg_match('/.{3,}/i', $json[$i]['name']) == false) { $validate = false; }
                if (in_array($json[$i]['hash'], $hashes_found)) { $validate = false; }
                                
                if ($validate === true) {
                    
                    $sql = 'INSERT INTO `torrents` (`hash`, `name`, `description`, `tags`, `languages`, `magnet`, `date`, `metadata`, `insert_date`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
                    $success = $this->db->execute( $sql,
                        [
                            $json[$i]['hash'],
                            $json[$i]['name'],
                            $json[$i]['description'],
                            $json[$i]['tags'],
                            $json[$i]['languages'],
                            $json[$i]['magnet'],
                            $json[$i]['date'],
                            $json[$i]['metadata'],
                            time()
                        ]
                    );
                    
                    if ($success === true) {
                        $hashes_found[] = $json[$i]['hash']; // prevent insert same hash multiple times if the hash is inserted previously saving the hash inserte
                        $inserts[] = $json[$i]['hash'];
                    }
                    
                }
                
            }
            
            $JSON = [ 'state' => 'ok', 'hashes' => $inserts, 'count' => count($inserts) ]; // { state: 'ok', hashes: hashes, count: inserts.length }
            
            // create a response
            $response = new Response();
            $response->setJsonContent( $JSON );
            
            $this->invalidateCache(); // clear cache older than the new items inserted
            
        } else {
            
            $JSON = [ 'state' => 'error', 'message' => 'malformed or wrong data' ];
            
            // create a response
            $response = new Response();
            $response->setJsonContent( $JSON );
            $response->setStatusCode (422);
            
        }
        
        return $response;                        
        
    }
    
    // check if cache is enabled and the key exists to return that and dont continue processing things
    private function checkCache($cache_token) {
        
        /*  To prevent some specific attack we store the cache BEFORE the query is done, the while prevent hight speed request from same client(s)
        *   with random params create multiple instances of mysql while the first query is finishing.
        *   If you dont like this method, you can evade this adding request limit, max request per IP, and some other funny things in your web server or here.
        *   You can test with: ab -n 1000 -c 100 http://localhost/library/search?search=random_strings_here
        */
        
        if ($this->config->options->enable_cache === true) {
            
            $this->clearInvalidateCache();
                        
            $data = $this->cache->get($cache_token);
                        
            if ($data !== null) {
                
                if ($data['working'] == true) {
                    
                    error_log('query working, waiting is done...');
                    $iterations = 0;
                    while ($data['working'] == true && $iterations < 20) { // prevent infinite loop if the the query fails
                        usleep(100000); // 100ms (100ms * 20 iterations = ... 2 seconds!)
                        $data = $this->cache->get($cache_token);
                        $iterations++;
                    }
                }
                
                if (!empty($data['data'])) {
                    return $data['data'];
                }
            }
            
        }
        
        return null;
        
    }
    
    // remove all cache keys with time before the cache_invalidation_time created by the invalidateCache() function
    private function clearInvalidateCache() {
                
        // Query all keys used in the cache
        $keys = $this->cache->queryKeys();
        $cache_invalidation_time = $this->cache->get('cache_invalidation_time');
        $cache_last_clean = $this->cache->get('cache_last_clean');
                
        // clean only cache if we have cache_invalidation_time AND the last clean are 5 minutes or more ago, to prevent attacks of create(invalidate)->query->create(invalidate)->query...
        if (!empty($cache_invalidation_time) && $cache_invalidation_time > 0 && $cache_last_clean < (time() - (60))) {
            
            $this->cache->save('cache_last_clean', time());
            
            foreach ($keys as $key) {
                $data = $this->cache->get($key);
                if (isset($data['time']) && $data['time'] < $cache_invalidation_time) {
                    $this->cache->delete($key);
                    error_log('invalidate cache ' . $key . ' time: ' . $data['time'] . 'remaining ', 0);
                }
            }
        }
        
    }
    
    // call this function to invalidate cache before this to send correct items
    private function invalidateCache() {
        if ($this->config->options->enable_cache === true) {
            $this->cache->save('cache_invalidation_time', time());
        }
    }
    
    
    // test function to fill the db with examples
    public function testFill() {
        /*
        
        $string = 'Contrary to popular belief, Lorem Ipsum is not simply random text. It has roots in a piece of classical Latin literature from 45 BC, making it over 2000 years old. Richard McClintock, a Latin professor at Hampden-Sydney College in Virginia, looked up one of the more obscure Latin words, consectetur, from a Lorem Ipsum passage, and going through the cites of the word in classical literature, discovered the undoubtable source. Lorem Ipsum comes from sections 1.10.32 and 1.10.33 of "de Finibus Bonorum et Malorum" (The Extremes of Good and Evil) by Cicero, written in 45 BC. This book is a treatise on the theory of ethics, very popular during the Renaissance. The first line of Lorem Ipsum, "Lorem ipsum dolor sit amet..", comes from a line in section 1.10.32';
        $langs = ['esp', 'eng', 'ita', 'fre', 'ger', 'deu', 'cub', 'jap', 'kor'];
        $tags = ['video', 'audio', 'music', 'game', 'console', 'xbox', 'pc', 'ps4', 'nintendo', 'ebook', 'document', 'windows', 'linux', 'mac', 'hd', '720p', '1080p', 'blueray', 'dvd', 'xvid', 'divx', 'mkv', 'mp4', 'mp3'];
        
        $metadata = [
            'image' => [ 'type' => 'image', 'value' => 'https://image.ibb.co/ifhmzQ/poster_opt.jpg' ],
            'description' => [ 'type' => 'html', 'value' => 'lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem ipsum dolor sti amet. <br> conectetum lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem lorem itsum est. <br> <b>strong test</b> <i style="color:red">cursive shit</i>' ],
            'author' => [ 'type' => 'text', 'value' => 'Hideo Nacamura' ],
            'iframe_test' => [ 'type' => 'iframe', 'value' => 'https://www.google.es' ],
            'quality' => [ 'type' => 'text', 'value' => '4K superHD' ],
            'some_list' => [
                'type' => 'list', 'value' => [
                    [ 'type' => 'text', 'value' => 'xxxxxxxx1' ],
                    [ 'type' => 'text', 'value' => 'xxxxxxxx2' ],
                    [ 'type' => 'text', 'value' => 'xxxxxxxx3' ]
                ]
            ]
        ];
        
        for ($i=0; $i<150000; $i++) {
            
            $randname =  substr($string, (rand(0, strlen($string)/2)), 100);
            $randlorem = substr($string, (rand(strlen($string), strlen($string))), rand(strlen($string), strlen($string)));
            $randhash = md5($randlorem);
            $randtag = [ $tags[rand(0, count($tags)-1)], $tags[rand(0, count($tags)-1)], $tags[rand(0, count($tags)-1)] ];
            $randlang = [ $langs[rand(0, count($langs)-1)], $langs[rand(0, count($langs)-1)], $langs[rand(0, count($langs)-1)] ];
            $magnet = 'magnet:?xt=urn:btih:565DB305A27FFB321FCC7B064AFD7BD73AEDDA2B&dn=bbb_sunflower_1080p_60fps_normal.mp4&tr=udp%3a%2f%2ftracker.openbittorrent.com%3a80%2fannounce&tr=udp%3a%2f%2ftracker.publicbt.com%3a80%2fannounce&ws=http%3a%2f%2fdistribution.bbb3d.renderfarming.net%2fvideo%2fmp4%2fbbb_sunflower_1080p_60fps_normal.mp4';

            $sql = 'INSERT INTO `torrents` (`hash`, `name`, `description`, `tags`, `languages`, `magnet`, `date`, `metadata`, `insert_date`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
            $success = $this->db->execute( $sql,
                [
                    $randhash,
                    $randname,
                    $randname,
                    json_encode($randtag),
                    json_encode($randlang),
                    $magnet,
                    time(true),
                    json_encode($metadata),
                    time(true),
                ]
            );
            
        }
        */
        
    }
    
}