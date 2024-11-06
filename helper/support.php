<?php

use dokuwiki\Extension\Plugin;

/**
 * DokuWiki Plugin cosmocode (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Andreas Gohr <dokuwiki@cosmocode.de>
 */
class helper_plugin_cosmocode_support extends Plugin
{
    /**
     * Get a list of all plugins and their versions
     *
     * There is no simple way to get this yet. In future versions of the extension manager we might be able to get this
     * from there. We supress all errors that might occur during loading of the plugins.
     * @return array
     */
    public function getPlugins()
    {
        ob_start();
        $plugins = [];
        foreach (['syntax', 'admin', 'auth', 'helper', 'action', 'renderer'] as $type) {
            $list = plugin_list($type);
            foreach ($list as $plugin) {
                [$name] = explode('_', $plugin);
                if (isset($plugins[$name])) continue;
                try {
                    $instance = plugin_load($type, $plugin);
                    if (!$instance) continue;
                    $info = $instance->getInfo();
                    $plugins[$name] = $info['date'];
                } catch (\Exception $ignore) {
                }
            }
        }
        ob_clean();

        return $plugins;
    }

    /**
     * Get informational data about the linux distribution this wiki is running on
     *
     * @return array an os-release array, might be empty
     * @see https://gist.github.com/natefoo/814c5bf936922dad97ff
     * @todo this will be included in future versions of DokuWiki
     */
    function getOsRelease()
    {
        $osRelease = [];
        if (file_exists('/etc/os-release')) {
            // pretty much any common Linux distribution has this
            $osRelease = parse_ini_file('/etc/os-release');
        } elseif (file_exists('/etc/synoinfo.conf') && file_exists('/etc/VERSION')) {
            // Synology DSM has its own way
            $synoInfo = parse_ini_file('/usr/lib/synoinfo.conf');
            $synoVersion = parse_ini_file('/etc/VERSION');
            $osRelease['NAME'] = 'Synology DSM';
            $osRelease['ID'] = 'synology';
            $osRelease['ID_LIKE'] = 'linux';
            $osRelease['VERSION_ID'] = $synoVersion['productversion'];
            $osRelease['VERSION'] = $synoVersion['productversion'];
            $osRelease['SYNO_MODEL'] = $synoInfo['upnpmodelname'];
            $osRelease['PRETTY_NAME'] = implode(' ', [$osRelease['NAME'], $osRelease['VERSION'], $osRelease['SYNO_MODEL']]);
        }
        return $osRelease;
    }

    /**
     * Get some data about the environment this wiki is running in
     *
     * @return array
     * @todo this will be included in future versions of DokuWiki
     */
    function getRuntimeVersions()
    {
        $data = [];
        $data['php'] = 'PHP ' . PHP_VERSION;

        $osRelease = $this->getOsRelease();
        if (isset($osRelease['PRETTY_NAME'])) {
            $data['dist'] = $osRelease['PRETTY_NAME'];
        }

        $data['os'] = php_uname('s') . ' ' . php_uname('r');
        $data['sapi'] = PHP_SAPI;

        if (getenv('KUBERNETES_SERVICE_HOST')) {
            $data['container'] = 'Kubernetes';
        } elseif (file_exists('/.dockerenv')) {
            $data['container'] = 'Docker';
        }

        return $data;
    }
}
