<?php
require 'vendor/autoload.php';
require 'config.php';

$container = new \Slim\Container;

$cache = new Illuminate\Cache\FileStore(new Illuminate\Filesystem\Filesystem(), 'cache');

$container['cache'] = $cache;

$container['httpCache'] = function () {
    return new \Slim\HttpCache\CacheProvider();
};

$container['view'] = function ($c) {
    $view = new \Slim\Views\Twig('templates');

    // Instantiate and add Slim specific extension
    $basePath = $c['request']->getUri();
    $view->addExtension(new Slim\Views\TwigExtension($c['router'], $basePath));
    return $view;
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
    $tweets = collect(json_decode($tweets));
    $tweets = $tweets->map(function($tweet){
        if (property_exists($tweet, 'retweeted_status')) {
            $userName = $tweet->user->name;
            $tweet->text = $tweet->retweeted_status->text;
            $tweet->user = $tweet->retweeted_status->user;
            $tweet->user->name = $userName . ' ' . html_entity_decode('&#x1f504;', 0, 'UTF-8') . ' ' . $tweet->user->name;
        }
        $tweet->text = html_entity_decode($tweet->text);
        return $tweet;
    });

    if (CACHE_ENABLED) {
        $this->cache->put('tweets', $tweets, CACHE_LIFETIME);
    }

    if (ETAG_ENABLED) {
        $response = $this->httpCache->withEtag($response, md5(json_encode($tweets)));
    }

    return $response->withJson($tweets, 200, JSON_PRETTY_PRINT);
});

$app->get('/', function(Request $request, Response $response, $arguments) {
    return $this->view->render($response, 'template.twig', [
        'minifyAssets' => MINIFY_ASSETS,
        'searchUsername' => TWITTER_SEARCH_USERNAME
    ]);
});

$app->run();
