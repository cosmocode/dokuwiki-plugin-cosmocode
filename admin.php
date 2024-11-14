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

    /** @var helper_plugin_cosmocode_support */
    protected $hlp_support;
    /** @var helper_plugin_cosmocode_partner */
    protected $hlp_partner;

    public function __construct()
    {
        $this->hlp_support = $this->loadHelper('cosmocode_support');
        $this->hlp_partner = $this->loadHelper('cosmocode_partner');
    }

    /** @inheritDoc */
    public function handle()
    {
        // FIXME data processing
    }

    /** @inheritDoc */
    public function html()
    {
        global $ID;
        global $INPUT;

        $tabs = [
            'support' => wl($ID, ['do' => 'admin', 'page' => 'cosmocode', 'tab' => 'support']),
            'partner' => wl($ID, ['do' => 'admin', 'page' => 'cosmocode', 'tab' => 'partner']),
        ];
        $current = $INPUT->str('tab', 'support');

        echo '<div class="plugin_cosmocode">';
        echo '<h1>' . $this->getLang('menu') . '</h1>';
        echo '<ul class="tabs">';
        foreach ($tabs as $tab => $url) {
            $class = ($current === $tab) ? 'active' : '';

            echo "<li class='$class'>";
            echo '<a href="' . $url . '">' . $this->getLang('tab_' . $tab) . '</a>';
            echo '</li>';
        }
        echo '</ul>';
        echo '<br>';

        switch ($current) {
            case 'partner':
                $this->showPartnerTab();
                break;
            case 'support':
                $this->showSupportTab();
                break;
        }
        echo '</div>';
    }

    protected function showPartnerTab()
    {
        $this->showTokenInfo();
        $this->showFeed();
    }

    protected function showSupportTab()
    {
        echo $this->locale_xhtml('support');

        global $conf;
        $data = [
            'dt' => dformat(null, '%Y-%m-%d'),
            'partner' => array_values($this->hlp_partner->getTokens()),
            'dokuwiki' => getVersionData(),
            'conf' => array_merge(
                array_intersect_key($conf, array_flip(
                    ['title', 'tagline', 'baseurl', 'basedir', 'savedir', 'useacl', 'authtype', 'template']
                )),
                [
                    'wiki-id' => md5(auth_cookiesalt()),
                    'inc' => DOKU_INC,
                ],
            ),
            'environment' => $this->hlp_support->getRuntimeVersions(),
            'plugins' => $this->hlp_support->getPlugins(),
            'extensions' => get_loaded_extensions(),
        ];

        echo '<div class="envdata">';
        echo json_encode($data, JSON_PRETTY_PRINT);
        echo '</div>';
    }


    /**
     * Show the list of available extensions
     */
    protected function showFeed()
    {
        try {
            $extensions = $this->hlp_partner->getExtensions();
        } catch (\Exception $e) {
            msg(nl2br(hsc($e->getMessage())), -1);
            return;
        }

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

    /**
     * Tell the user about their current partner status
     * @return void
     */
    protected function showTokenInfo()
    {
        $tokens = $this->hlp_partner->getTokens();
        if (!$tokens) {
            echo $this->locale_xhtml('partner-no');
            return;
        }
        echo $this->locale_xhtml('partner-yes');

        echo '<ul class="tokens">';
        foreach ($tokens as $token => $data) {
            echo '<li class="token"><div class="li">';
            if ($data['scopes'][0] === '') {
                echo 'Partner Token';
            } else {
                echo hsc($data['scopes'][0]) . ' Token';
            }
            echo ' ' . sprintf($this->getLang('valid_until'), dformat($data['exp']));
            echo '</div></li>';
        }
        echo '</ul>';
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
        $dl = $this->hlp_partner->getDownloadUrl($ext['base'], $ext['token']);
        $action = wl($ID, ['do' => 'admin', 'page' => 'extension', 'tab' => 'install'], false, '&');
        $form = new Form(['action' => $action]);
        $form->setHiddenField('installurl', $dl);
        $form->setHiddenField('overwrite', 1);
        $form->addButton('submit', $this->getLang('btn_install'))->attr('type', 'submit');
        return $form->toHTML();
    }
}
