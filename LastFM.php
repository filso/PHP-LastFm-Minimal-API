<?php

/*
 * This is minimal PHP library - it implements all the necessary
 * stuff, and ONLY that.
 *
 * Implemented:
 * - authentication flow
 * - api calls wrapper
 * - error wrapper
 */

if (!function_exists('curl_init')) {
    throw new Exception('LastFM needs the CURL PHP extension.');
}
if (!function_exists('json_decode')) {
    throw new Exception('LastFM needs the JSON PHP extension.');
}

/**
 * Thrown when an API call returns an exception.
 *
 * @author Filip Sobczak <f@digitalinvaders.pl>
 */
class LastFMException extends Exception
{

    /**
     * The result from the API server that represents the exception information.
     */
    protected $result;

    /**
     * Make a new API Exception with the given result.
     *
     * @param array $result the result from the API server
     */
    public function __construct($result)
    {
        $this->result = $result;

        $code = isset($result['error']) ? $result['error'] : 0;

        if (isset($result['message'])) {
            $msg = $result['message'];
        } else {
            $msg = 'Unknown Error. Check getResult()';
        }

        parent::__construct($msg, $code);
    }

    /**
     * Return the associated result object returned by the API server.
     *
     * @return array the result from the API server
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * To make debugging easier.
     *
     * @return string the string representation of the error
     */
    public function __toString()
    {
        $str = '';
        if ($this->code != 0) {
            $str .= $this->code . ': ';
        }

        return $str . $this->message;
    }
}

class LastFMInvalidSessionException extends LastFMException
{
    public function __construct($result)
    {
        parent::__construct($result);
    }
}

/**
 * Provides access to the LastFM platform.
 *
 * @author Filip Sobczak <f@digitalinvaders.pl>
 */
class LastFM
{
    const VERSION = '0.9';

    /**
     * Default options for curl.
     */
    public static $CURL_OPTS = array(
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'lastfm-php-0.9',
    );
    /**
     * The Application API Secret.
     */
    protected $apiSecret;
    /**
     * The Application API Key.
     */
    protected $apiKey;
    /**
     * The active user session key, if one is available.
     */
    protected $sk;
    public static $DOMAIN_MAP = array(
        'www' => 'https://www.last.fm/',
        'webservice' => 'https://ws.audioscrobbler.com/2.0/',
    );
    protected $apiReturnAssoc = false;

    const METHOD_AUTH = 1;
    const METHOD_WRITE = 2;
    const METHOD_GET_AUTH = 3;
    const METHOD_UNKNOWN = 4;

    /*
     * Some methods require authentication (type auth),
     * they all send api_sig and sk
     * some methods are used to get authenticated (type get_auth)
     * they all send api_sig
     * some methods are used to write data (type write)
     * they all send api_sig and sk, and use POST http method
     *
     * All letters are small because users might use
     * variations of letter sizes, and we need to
     * find these values fast, so strtolower is executed on method name.
     */
    public static $METHOD_TYPE = array(
        'auth.getmobilesession' => self::METHOD_GET_AUTH,
        'auth.getsession' => self::METHOD_GET_AUTH,
        'auth.gettoken' => self::METHOD_GET_AUTH,
        'album.addtags' => self::METHOD_WRITE,
        'album.gettags' => self::METHOD_AUTH,
        'album.removetag' => self::METHOD_WRITE,
        'album.share' => self::METHOD_WRITE,
        'artist.addtags' => self::METHOD_WRITE,
        'artist.gettags' => self::METHOD_AUTH,
        'artist.removetag' => self::METHOD_WRITE,
        'artist.share' => self::METHOD_WRITE,
        'artist.shout' => self::METHOD_WRITE,
        'event.attend' => self::METHOD_WRITE,
        'event.share' => self::METHOD_WRITE,
        'event.shout' => self::METHOD_WRITE,
        'library.addalbum' => self::METHOD_WRITE,
        'library.addartist' => self::METHOD_WRITE,
        'library.addtrack' => self::METHOD_WRITE,
        'library.removealbum' => self::METHOD_WRITE,
        'library.removeartist' => self::METHOD_WRITE,
        'library.removescrobble' => self::METHOD_WRITE,
        'library.removetrack' => self::METHOD_WRITE,
        'playlist.addtrack' => self::METHOD_WRITE,
        'playlist.create' => self::METHOD_WRITE,
        'radio.getplaylist' => self::METHOD_AUTH,
        'radio.tune' => self::METHOD_WRITE,
        'track.addtags' => self::METHOD_WRITE,
        'track.ban' => self::METHOD_WRITE,
        'track.gettags' => self::METHOD_AUTH,
        'track.love' => self::METHOD_WRITE,
        'track.removetag' => self::METHOD_WRITE,
        'track.scrobble' => self::METHOD_WRITE,
        'track.share' => self::METHOD_WRITE,
        'track.unban' => self::METHOD_WRITE,
        'track.unlove' => self::METHOD_WRITE,
        'track.updatenowplaying' => self::METHOD_WRITE,
        'user.getrecentstations' => self::METHOD_AUTH,
        'user.getrecommendedartists' => self::METHOD_AUTH,
        'user.getrecommendedevents' => self::METHOD_AUTH,
        'user.shout' => self::METHOD_WRITE,
    );

    /**
     * Initialize LastFM application.
     *
     * @param string $apiKey
     * @param string|null $apiSecret (optional) Required for authenticated calls
     * @param string|null $sessionKey (optional) Required for authenticated calls
     */
    public function __construct($apiKey, $apiSecret = NULL, $sessionKey = NULL)
    {
        $this->setApiKey($apiKey);
        if ($apiSecret !== NULL) {
            $this->setApiSecret($apiSecret);
        }
        if ($sessionKey !== NULL) {
            $this->setSessionKey($sessionKey);
        }
    }

    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
        return $this;
    }

    public function getApiSecret()
    {
        return $this->apiSecret;
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

    public function setSessionKey($sk)
    {
        $this->sk = $sk;

        return $this;
    }

    public function getSessionKey()
    {
        return $this->sk;
    }

    public function setReturnAssoc()
    {
        $this->apiReturnAssoc = true;
    }

    public function setReturnObject()
    {
        $this->apiReturnAssoc = false;
    }

    private function methodType($method)
    {
        if (isset(self::$METHOD_TYPE[strtolower($method)])) {
            return self::$METHOD_TYPE[strtolower($method)];
        } else {
            return self::METHOD_UNKNOWN;
        }
    }

    /**
     * Get a Login URL for use with redirects.
     *
     * The parameters:
     * - api_key: application api key
     *
     * @param array $callback override default redirect
     * @return string the URL for the login flow
     */
    public function getLoginUrl($callback=array())
    {
        $params = array('api_key' => $this->getApiKey());
        if ($callback)
            $params['cb'] = $callback;

        return $this->getUrl('www', 'api/auth', $params);
    }

    /**
     * @param string $token 32-char ASCII MD5 hash, gained by granting permissions
     * @return array
     */
    public function fetchSession($token = '')
    {
        if (!$token) {
            if (isset($_GET['token']))
                $token = $_GET['token'];
        }

        $result = $this->api('auth.getSession', array('token' => $token));
        $name = $result['session']['name'];
        $sessionKey = $result['session']['key'];
        $this->setSessionKey($sessionKey);

        return array('name' => $name, 'sk' => $sessionKey);
    }

    public function __call($name, $arguments)
    {
        $method = str_replace('_', '.', $name);
        $params = isset($arguments[0]) ? $arguments[0] : array();

        return $this->api($method, $params);
    }

    /**
     * Make an API call
     *
     * @param string $method
     * @param array $params method call object
     * @throws LastFMException
     * @throws LastFMInvalidSessionException
     * @return array the decoded response object
     */
    public function api($method, $params = array())
    {
        // generic application level parameters
        $params['api_key'] = $this->getApiKey();
        $params['format'] = 'json';

        // required api method
        $params['method'] = $method;

        $methodType = $this->methodType($method);

        if ($methodType == self::METHOD_AUTH || $methodType == self::METHOD_WRITE) {
            if (!isset($params['sk'])) {
                $params['sk'] = $this->getSessionKey();
            }
            if (!$params['sk']) {
                throw new LastFMException(array("message" => "No session key provided"));
            }
        } else {
            if (isset($params['sk']))
                unset($params['sk']);
        }

        if ($methodType == self::METHOD_GET_AUTH || $methodType == self::METHOD_WRITE) {
            $params['api_sig'] = $this->generateSignature($params);
        }

        $raw = $this->makeRequest(self::getUrl('webservice'), $params);
        $result = json_decode($raw, $this->apiReturnAssoc);

        if (is_array($result) && isset($result['error'])) {
            if ($result['error'] == 9) {
                // Invalid session key - Please re-authenticate
                // this is different so that when user invalidates
                // session the situation can be handled easily
                throw new LastFMInvalidSessionException($result);
            } else
                throw new LastFMException($result);
        }

        return $result;
    }

    /**
     * Makes an HTTP request.
     *
     * @param string $url the URL to make the request to
     * @param array $params the parameters to use for the POST body
     * @throws LastFMException
     * @return string the response text
     */
    protected function makeRequest($url, $params)
    {
        $ch = curl_init();
        $opts = self::$CURL_OPTS;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params, null, '&');
        $opts[CURLOPT_URL] = $url;

        curl_setopt_array($ch, $opts);
        $result = curl_exec($ch);

        if ($result === false) {
            $e = new LastFMException(array(
                        'error' => curl_errno($ch),
                        'message' => curl_error($ch),
                    ));
            curl_close($ch);
            throw $e;
        }
        curl_close($ch);

        return $result;
    }

    /**
     * Build the URL for given domain alias, path and parameters.
     *
     * @param string $name the name of the domain
     * @param string $path optional path (without a leading slash)
     * @param array $params optional query parameters
     * @return string the URL for the given parameters
     */
    protected function getUrl($name, $path='', $params=array())
    {
        $url = self::$DOMAIN_MAP[$name];
        if ($path) {
            if ($path[0] === '/') {
                $path = substr($path, 1);
            }
            $url .= $path;
        }

        if ($params) {
            $url .= '?' . http_build_query($params, null, '&');
        }

        return $url;
    }

    /**
     * Generate a signature for the given params and secret.
     *
     * @param array $params the parameters to sign
     * @return string the generated signature
     */
    protected function generateSignature($params)
    {
        // work with sorted data
        ksort($params);

        $base_string = '';
        foreach ($params as $key => $value) {
            if ($key == 'format' || $key == 'callback')
                continue;
            $base_string .= $key . $value;
        }
        $base_string .= $this->getApiSecret();

        return md5(utf8_encode($base_string));
    }
}