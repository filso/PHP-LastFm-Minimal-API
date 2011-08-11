<?
/*
 * You can find live demo of this example here:
 * http://digitalinvaders.pl/lastfmapi
 * The api_secret and api_key below have been replaced
 */
require_once('lib/LastFM.php');
$lastfm = new LastFM(
                array('api_key' => "f71898d5abc901fd03bc52a0840a915e",
                    'api_secret' => "41a7f4945de3f6df57866f323ade70ff")
);
if (isset($_GET['token'])) {
    // we just got authorization token from lastfm site,
    // so we fetch session key
    $session = $lastfm->fetchSession($_GET['token']);
    // we set GET parameters, as if we passed it to this script
    $_GET['sk'] = $session['sk'];
    $_GET['user'] = $session['name'];
}

if (isset($_GET['sk'])) {
    // we already have user LastFM session key, let's use it!
    $lastfm->setSessionKey($_GET['sk']);
}


?>
<!DOCTYPE html>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>
            PHP LastFM Minimal API usage example
        </title>
    </head>
    <body>
        <?
        if (isset($_GET['sk'])) {
            echo "<a href='/lastfmapi'>Deauthorize</a><br/>";
        } else {
            // this is login url that, with callback set to first argument
            $login_url = $lastfm->getLoginUrl('http://digitalinvaders.pl/lastfmapi');
            echo "<a href='$login_url'>Authorize</a><br/>";
        }
        if (isset($_GET['sk'])) {
            $url = "/lastfmapi?method=user.getLovedTracks&user=".$_GET['user']
                  . "&sk=".$_GET['sk']."&limit=3";
        } else {
            $url = "/lastfmapi?method=user.getLovedTracks&user=dummy_user"
                  . "&sk=dummy_sk&limit=3";
        }
        ?>
        <a href="<?= $url ?>">Fetch first 3 of my loved tracks</a>
        <br/>
        Fetch track info from LastFM:
        <form action="/lastfmapi" method="get">
            Artist: <input type="text" name="artist"/>
            Track: <input type="text" name="track"/>
            <input type="hidden" name="method" value="track.getInfo"/>
            <? if (isset($_GET['sk'])): ?>
            <input type="hidden" name="sk" value="<?= $_GET['sk'] ?>"/>
            <input type="hidden" name="user" value="<?= $_GET['user'] ?>"/>
            <? endif; ?>
            <input type="submit" value="Fetch info"/>
        </form>
        
        <? if (isset($_GET['method'])) {
            $params = $_GET;
            unset($params['method']);
            unset($params['sk']);
            $response = $lastfm->api($_GET['method'], $params);
            
            echo "LastFM server response:";
            echo "<pre><code>";
            print_r($response);
            echo "</code></pre>";
        }
        ?>
        <style>
            pre {
                padding: 10px;
                border: 1px solid black;
            }

        </style>
    </body>
</html>
