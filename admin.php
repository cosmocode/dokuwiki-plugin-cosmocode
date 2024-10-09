<?php

use dokuwiki\Extension\AdminPlugin;
use dokuwiki\Form\Form;

/**
 * DokuWiki Plugin cosmocode (Admin Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Andreas Gohr <dokuwiki@cosmocode.de>
 */
class admin_plugin_cosmocode extends AdminPlugin
{

    const HOST = 'http://localhost:8000/';

    /** @inheritDoc */
    public function handle()
    {
        // FIXME data processing
    }

    /** @inheritDoc */
    public function html()
    {
        // FIXME render output
        echo '<h1>' . $this->getLang('menu') . '</h1>';

        // FIXME add tabs
        // FIXME add a "get support" tab

        $this->showTokenInfo();
        $this->showFeed();
    }

    protected function showFeed()
    {
        $extensions = $this->getExtensions();
        echo '<ul class="extensions">';
        foreach ($extensions as $ext) {
            echo '<li>';
            echo '<div class="li">';
            echo '<h2>';
            echo '<a href="' . hsc($ext['url']) . '" class="urlextern" target="_blank">' . hsc($ext['name']) . '</a>';
            echo ' <span>' . hsc($ext['date']) . '</span>';
            echo '</h2>';
            echo '<p>' . hsc($ext['desc']) . '</p>';

            // FIXME check if this version is newer than the installed one and highlight updates

            if ($ext['token']) {
                echo $this->getInstallForm($ext);
            }

            echo '</div>';
            echo '</li>';
        }
        echo '</ul>';
    }

    protected function showTokenInfo()
    {
        $tokens = $this->getTokens();
        if (!$tokens) {
            echo '<p>No valid tokens configured</p>'; // FIXME load a nice text explaining how to get tokens
            return;
        }

        foreach ($tokens as $token => $data) {
            echo '<div class="token">';
            if ($data['scopes'][0] === '') {
                echo 'Partner Token';
            } else {
                echo hsc($data['scopes'][0]) . ' Token';
            }
            echo ' valid until ' . date('Y-m-d', $data['exp']);
            echo '</div>';
        }
    }

    protected function getTokens()
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
     * Create a form that submits the special download URL to the extension manager
     *
     * @param array $ext
     * @return string
     */
    protected function getInstallForm($ext)
    {
        global $ID;
        $dl = self::HOST . 'download/' . $ext['base'] . '.zip?token=' . $ext['token'];
        $action = wl($ID, ['do' => 'admin', 'page' => 'extension', 'tab' => 'install'], false, '&');
        $form = new Form(['action' => $action]);
        $form->setHiddenField('installurl', $dl);
        $form->setHiddenField('overwrite', 1);
        $form->addButton('submit', 'Install')->attr('type', 'submit');
        return $form->toHTML();
    }

    protected function getExtensions()
    {
        $http = new \dokuwiki\HTTP\DokuHTTPClient();
        $url = self::HOST . 'feed';

        $tokens = $this->getTokens();
        if ($tokens) {
            $http->headers['x-token'] = join(',', array_keys($tokens));
        }

        $cache = getCacheName($url . join(',', array_keys($tokens)), '.json');
        if (@filemtime($cache) > time() - 60 * 60 * 24) {
            $data = io_readFile($cache, false);
        } else {
            $data = $http->get($url);
            if ($data === false) {
                $data = $http->resp_body;
                $decoded = json_decode($data, true);
                if ($decoded && isset($decoded['error'])) {
                    throw new \RuntimeException('API returned error: ' . $decoded['error']);
                } else {
                    throw new \RuntimeException('Could not fetch data from API.');
                }
            }
            io_saveFile($cache, $data);
        }
        return json_decode($data, true);
    }

    /**
     * @link https://www.converticacommerce.com/support-maintenance/security/php-one-liner-decode-jwt-json-web-tokens/
     * @param $jwt
     * @return array
     */
    protected function decodeJWT($jwt)
    {
        return json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $jwt)[1]))), true);
    }
}
