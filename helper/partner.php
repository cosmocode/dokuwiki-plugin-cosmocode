<?php

use dokuwiki\Extension\Plugin;

/**
 * DokuWiki Plugin cosmocode (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Andreas Gohr <dokuwiki@cosmocode.de>
 */
class helper_plugin_cosmocode_partner extends Plugin
{
    const HOST = 'https://partnerapi.cosmocode.de/';

    /**
     * Construct the URL at which a extension can be downloaded
     *
     * @param string $base
     * @param string $token
     * @return string
     */
    public function getDownloadUrl($base, $token)
    {
        return self::HOST . 'download/' . $base . '.zip?token=' . $token;
    }

    /**
     * Talk to the API to get the list of extensions
     *
     * Results are cached for 24 hours
     *
     * @return array
     * @throws Exception
     */
    public function getExtensions()
    {
        $http = new \dokuwiki\HTTP\DokuHTTPClient();
        $url = self::HOST . 'feed';
        $domain = parse_url(self::HOST, PHP_URL_HOST);

        $tokens = $this->getTokens();
        if ($tokens) {
            $http->headers['x-token'] = join(',', array_keys($tokens));
        }
        $http->headers['x-wiki-id'] = md5(auth_cookiesalt());

        $cache = getCacheName($url . join(',', array_keys($tokens)), '.json');
        if (@filemtime($cache) > time() - 60 * 60 * 24) {
            $data = io_readFile($cache, false);
        } else {
            $data = $http->get($url);
            if ($data === false) {
                $data = $http->resp_body;
                $decoded = json_decode($data, true);
                if ($decoded && isset($decoded['error'])) {
                    throw new \RuntimeException(
                        sprintf($this->getLang('error_api'), $decoded['error'])
                    );
                } else {
                    throw new \RuntimeException(
                        sprintf($this->getLang('error_connect'), $domain, $http->error)
                    );
                }
            }
            io_saveFile($cache, $data);
        }
        return json_decode($data, true);
    }

    /**
     * Get the tokens from the config
     *
     * Decodes the payload and filters out expired tokens
     *
     * @return array
     */
    public function getTokens()
    {
        $lines = $this->getConf('tokens');
        if (!$lines) return [];
        $lines = explode("\n", $lines);

        $tokens = [];
        foreach ($lines as $token) {
            $token = trim($token);
            if (!$token) continue;
            $decoded = $this->decodeJWT($token);
            if (!$decoded) continue;
            if (!isset($decoded['exp'])) continue;
            if ($decoded['exp'] < time()) continue;

            $tokens[$token] = $decoded;
        }

        return $tokens;
    }

    /**
     * Decode the payload of a JWT token
     *
     * Does not validate expiration or signature
     *
     * @link https://www.converticacommerce.com/support-maintenance/security/php-one-liner-decode-jwt-json-web-tokens/
     * @param $jwt
     * @return array
     */
    protected function decodeJWT($jwt)
    {
        return json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $jwt)[1]))), true);
    }

}
