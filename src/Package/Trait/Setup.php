<?php
namespace Package\Raxon\Audioplayer\Trait;

use Package\Raxon\Account\Module\User;
use Package\Raxon\Desktop\Module\Navigation;
use Package\Raxon\Basic\Trait\Install;
use Raxon\App;
use Raxon\Config;

use Raxon\Doctrine\Module\Database;
use Raxon\Exception\DirectoryCreateException;

use Raxon\Exception\ObjectException;
use Raxon\Module\Cli;
use Raxon\Module\Data;
use Raxon\Module\Destination;
use Raxon\Module\Dir;
use Raxon\Module\Core;
use Raxon\Module\File;
use Raxon\Module\OutputFilter;
use Raxon\Parse\Module\Parse;

use Raxon\Node\Module\Node;

use Exception;

trait Setup {
    const NAME = 'Audioplayer';
    const DISPLAY_NAME = 'Audio Player';
    const DESCRIPTION = 'Audio Player (Playing mp3, wav & ogg)';
    const ROUTE_NAME = 'application-audio-player';
    const EXTENSION_ENABLED = 'System.Server.Extension.Enabled';
    const PACKAGE = 'raxon/audioplayer';
    const CONTENT_TYPE_ENABLED = 'System.Server.ContentType.Enabled';

    use Install;

    /**
     * @throws DirectoryCreateException
     * @throws Exception
     */
    public function install(object $flags, object $options): void
    {
        $object = $this->object();
        if($object->config(Config::POSIX_ID) !== 0){
            return;
        }
        $options->frontend = $this->install_frontend_get($options);
        $options->backend = $this->install_backend_get($options);
        $options->package = self::PACKAGE;
        $options->url = (object) [
            'node_extension' => $object->config('project.dir.node') . 'Data' . $object->config('ds') . 'System.Server.Extension' . $object->config('extension.json'),
            'node_content_type' => $object->config('project.dir.node') . 'Data' . $object->config('ds') . 'System.Server.ContentType' . $object->config('extension.json'),
            'extension' => $object->config('controller.dir.data') . 'System.Server.Extension' . $object->config('extension.json'),
            'content_type' => $url = $object->config('controller.dir.data') . 'System.Server.ContentType' . $object->config('extension.json')
        ];
        $this->install_api($options);
        $this->install_application($options);
        $list = User::list($object, User::ROLES_ALLOWED);
        Navigation::create(
            $object,
            $list,
            (object)[
                'name' => self::NAME,
                'route' => (object) [
                    'name' => self::ROUTE_NAME,
                ]
            ]
        );
        $this->install_system_application(
            $flags,
            $options,
        );
        $command = 'app install raxon/account -patch';
        Core::execute($object, $command, $output, $notification);
        if($output){
            echo $output;
        }
        if($notification){
            echo $notification;
        }
    }
}