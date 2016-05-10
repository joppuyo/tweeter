<?php
require 'vendor/autoload.php';
require 'config.php';

$container = new \Slim\Container;

$cache = new Illuminate\Cache\FileStore(new Illuminate\Filesystem\Filesystem(), 'cache');

$container['cache'] = $cache;

$container['httpCache'] = function () {
    return new \Slim\HttpCache\CacheProvider();
};

$app = new Slim\App($container);

$app->add(new \Slim\HttpCache\Cache());

use Slim\Http\Request;
use Slim\Http\Response;

$app->get('/api', function(Request $request, Response $response, $arguments) {

    $cachedTweets = $this->cache->get('tweets');

    if ($cachedTweets) {
        if (ETAG_ENABLED) {
            $response = $this->httpCache->withEtag($response, md5(json_encode($cachedTweets)));
        }
        return $response->withJson($cachedTweets, 200, JSON_PRETTY_PRINT);
    }

    $client = new Freebird\Services\freebird\Client();
    $client->init_bearer_token(TWITTER_API_KEY, TWITTER_API_SECRET);
    $tweets = $client->api_request('statuses/user_timeline.json', ['screen_name' => TWITTER_SEARCH_USERNAME]);
    $tweets = json_decode($tweets);

    if (CACHE_ENABLED) {
        $this->cache->put('tweets', $tweets, CACHE_LIFETIME);
    }

    if (ETAG_ENABLED) {
        $response = $this->httpCache->withEtag($response, md5(json_encode($tweets)));
    }

    return $response->withJson($tweets, 200, JSON_PRETTY_PRINT);
});

$app->get('/', function(Request $request, Response $response, $arguments) {

});

$app->run();
