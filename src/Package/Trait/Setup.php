<?php
namespace Package\Raxon\Audioplayer\Trait;

use Exception;
use Package\Raxon\Account\Module\User;
use Package\Raxon\Desktop\Module\Navigation;
use Package\Raxon\Basic\Trait\Install;
use Raxon\Config;
use Raxon\Exception\DirectoryCreateException;
use Raxon\Module\Core;

trait Setup {
    const NAME = 'Audioplayer';

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
//        $options->frontend = $this->install_frontend_get($options);
//        $options->backend = $this->install_backend_get($options);
        //$options->package = self::PACKAGE;
        /*
        $options->url = (object) [
            'node_extension' => $object->config('project.dir.node') . 'Data' . $object->config('ds') . 'System.Server.Extension' . $object->config('extension.json'),
            'node_content_type' => $object->config('project.dir.node') . 'Data' . $object->config('ds') . 'System.Server.ContentType' . $object->config('extension.json'),
            'extension' => $object->config('controller.dir.data') . 'System.Server.Extension' . $object->config('extension.json'),
            'content_type' => $object->config('controller.dir.data') . 'System.Server.ContentType' . $object->config('extension.json'),
            'system_application' => $object->config('controller.dir.data') . 'System.Application' . $object->config('extension.json')
        ];
        */
//        $object->data(App::OPTIONS, $options);
        $application_list = $this->install_system_application(
            $flags,
            $options,
        );
        foreach($application_list as $application){
            $this->install_api($options, $application);
            $this->install_application($options, $application);
            Navigation::create(
                $object,
                $options,
                $application
            );
        }
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