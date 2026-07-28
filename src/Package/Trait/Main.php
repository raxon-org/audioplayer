<?php
namespace Package\Raxon\Audioplayer\Trait;

use Raxon\App;
use Raxon\Config;

use Raxon\Doctrine\Module\Database;
use Raxon\Exception\DirectoryCreateException;

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

trait Main {
    const NAME = 'Audioplayer';
    const ROUTE_NAME = 'application-audio-player';
    const ICON_URL = '/Application/Audioplayer/Icon/Icon.png';
    const EXTENSION_ENABLED = 'System.Server.Extension.Enabled';
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
        $has_frontend = false;
        $frontend_options = [];
        $backend_options = [];
        if(property_exists($options, 'frontend')){
            if(property_exists($options->frontend, 'host')){                
                $has_frontend = true;
                $frontend_options = [
                    'where' => [
                        [
                            'value' => $options->frontend->host,
                            'attribute' => 'name',
                            'operator' => 'partial',
                        ]
                    ]
                ];
            }                
        }        
        $has_backend = false;
        if(property_exists($options, 'backend')){
            if(property_exists($options->backend, 'host')){                
                $has_backend = true;
                $backend_options = [
                    'where' => [
                        [
                            'value' => $options->backend->host,
                            'attribute' => 'name',
                            'operator' => 'partial',
                        ]
                    ]
                ];                
            }
        }
        if($has_frontend === false){
            throw new Exception('Frontend.host option is required and must be defined in Node/System.Host.json aborting...');
        }
        if($has_backend === false){
            throw new Exception('Backend.host option is required and must be defined in Node/System.Host.json aborting...');
        }

        $class = 'System.Host';
        $node = new Node($object);
        $response_frontend = $node->record($class, $node->role_system(), $frontend_options);
        $response_backend = $node->record($class, $node->role_system(), $backend_options);
        $dir_read = $object->config('project.dir.vendor') .
            $object->request('package') .
            $object->config('ds') .
            'src' .
            $object->config('ds') .
            $object->config('dictionary.application') .
            $object->config('ds')
        ;
        $dir_application = $object->config('project.dir.domain') .
            $response_frontend['node']->name .
            $object->config('ds') .
            $object->config('dictionary.application') .
            $object->config('ds')
        ;
        $dir_target = $dir_application .
            self::NAME .
            $object->config('ds')
        ;
        if(!File::exist($dir_target)){
            Dir::create($dir_target, Dir::CHMOD);
            File::permission($object, [
                'target' => $dir_target,
                'application' => $dir_application,
            ]);
        }
        $dir = new Dir();
        $read = $dir->read($dir_read, true);
        foreach($read as $nr => $file){
            $explode = explode($dir_read, $file->url, 2);
            if(array_key_exists(1, $explode)){
                $file->target = $dir_target . $explode[1];
            }
        }
        foreach($read as $nr => $file){
            if($file->type === Dir::TYPE){
                if(!File::exist($file->target)){
                    Dir::create($file->target, Dir::CHMOD);
                    File::permission($object, [
                        'target' => $file->target,
                    ]);
                }
            }
        }
        $patch = $options->patch ?? null;
        foreach($read as $nr => $file){
            if($file->type === File::TYPE){
                $file->extension = File::extension($file->target);
                if($file->extension === 'rax'){
                    $explode = explode('.rax', $file->target, 2);
                    if(array_key_exists(1, $explode)){
                        $file->target = $explode[0];
                        $file->original_extension = File::extension($file->target);
                        if(!File::exist($file->target) || $patch !== null){
                            $clone_options = new Data();
                            if(!property_exists($response_frontend['node'],'subdomain')){
                                $clone_options->set('frontend.host', $response_frontend['node']->domain . '.' . $response_frontend['node']->extension);
                            } else {
                                $clone_options->set('frontend.host', $response_frontend['node']->subdomain . '.' . $response_frontend['node']->domain . '.' . $response_frontend['node']->extension);
                            }
                            if(!property_exists($response_backend['node'],'subdomain')){
                                $clone_options->set('backend.host', $response_backend['node']->domain . '.' . $response_backend['node']->extension);
                            } else {
                                $clone_options->set('backend.host', $response_backend['node']->subdomain . '.' . $response_backend['node']->domain . '.' . $response_backend['node']->extension);
                            }
                            $data = new Data($object->data());
                            $clone = clone $object;
                            $clone->data(App::OPTIONS, $clone_options->data());                                                        
                            switch($file->original_extension){
                                case 'json':                                    
                                    echo Cli::info('Processing file:') . $file->target . PHP_EOL;
                                    $content = $clone->parse_read($file->url);
                                    if($patch !== null) {
                                        File::delete($file->target);
                                    }                                    
                                    File::write($file->target, Core::object($content->data(), Core::JSON));
                                    File::permission($object, [
                                        'target' => $file->target,
                                    ]);
                                    //imports should be in a json file (class => url/contains)
                                    if(str_contains($file->target, 'System.Route')){
                                        $command = 'app raxon/node object import -class=System.Route -url="' . $file->target . '" -patch';
                                        Core::execute($object, $command, $output, $notification);
                                        if($output){
                                            echo $output;
                                        }
                                        if($notification){
                                            echo $notification;
                                        }
                                    }
                                break;
                                default:
                                    echo Cli::info('Processing file:') . $file->target . PHP_EOL;
                                    $clone_options->set('source', $file->url);
                                    $flags = App::flags($clone);
                                    $parse = new Parse($clone, $data, $flags, $clone_options->data());
                                    $read = File::read($file->url);
                                    $content = $parse->compile($read, $data);
                                    if($patch !== null) {
                                        File::delete($file->target);
                                    }                                    
                                    File::write($file->target, $content);
                                    File::permission($object, [
                                        'target' => $file->target,
                                    ]);
                                break;
                            }
                        }                                    
                    }
                } else {
                    if($patch !== null) {
                        File::delete($file->target);
                    }
                    echo Cli::info('Processing file:') . $file->target . PHP_EOL;
                    File::copy($file->url, $file->target);                    
                    File::permission($object, [
                        'target' => $file->target,
                    ]);
                }                
            }
        }

        if(!property_exists($options, 'connection')){
            $options->connection = 'system';
        }
        if(!property_exists($options, 'environment')){
            $options->environment = $object->config('framework.environment');;
        }
        if(empty($options->environment)){
            $options->environment = '*';
        }
        $config = Database::config($object);
        $connection = $this->connection($flags, $options);
        breakpint($connection);
        //need output filter or decorator on tje json object
        $temp_connection = clone $connection;
        unset($temp_connection->{'#class'});
        unset($temp_connection->uuid);
        unset($temp_connection->{'#index'});

        d($connection);

        $connection->manager = Database::entity_manager($object, $config, $temp_connection);
        $repository = $connection->manager->getRepository('\\Entity\\Extension');
        $url = $object->config('controller.dir.data') .
            self::EXTENSION_ENABLED .
            $object->config('extension.json');
        $data_extension = $object->data_read($url);
        $extensions = [];
        if($data_extension){
            foreach($data_extension->data(self::EXTENSION_ENABLED) as $extension){
                if(
                    is_object($extension) &&
                    property_exists($extension, 'extension')){
                    if(!in_array($extension->extension, $extensions, true)){
                        $extensions[] = $extension->extension;
                    }
                }
            }
        }
        $list =$repository->findBy([
            'name' => $extensions
        ]);
        $list_application = [];
        foreach($list as $nr => $extension){
            $applications = $extension->getApplications();
            foreach($applications as $application_nr => $application){
                if(!in_array($application->getName(), $list_application, true)){
                    $list_application[] = $application->getName();
                }
            }
            if(!in_array(self::NAME, $list_application, true)){
                //adding application to the extension and add extensions to the application
                $repository = $connection->manager->getRepository('\Entity\Application');
            $application_url = '{{route.get(\''. self::ROUTE_NAME . '\')}}';
                $entity_application = $repository->findOneBy([
                    'url' => $application_url
                ]);
                if(!$entity_application){
                    $entity_application = new \Entity\Application();
                    $entity_application->setUrl('{{route.get(\''. self::ROUTE_NAME . '\')}}');
                    $entity_application->setName(self::NAME);
                    $entity_application->iconUrl(self::ICON_URL);
                    $entity_application->setExtensions($list);
                    $connection->manager->persist($entity_application);
                } else {
                    $entity_application->setExtensions($list);
                    $connection->manager->persist($entity_application);
                }
                $extension->addApplication($entity_application);
                $connection->manager->persist($extension);
                $connection->manager->flush();
            }
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

    public function connection(object $flags, null|object $options = null): object
    {
        $object = $this->object();
        $connection = $object->config('doctrine.environment.' . $options->connection . '.' . $options->environment);
        if($connection === null){
            $connection = $object->config('doctrine.environment.' . $options->connection . '.' . '*');
        }
        if($connection === null){
            throw new Exception('Connection not found aborting...');
        }$connection = $object->config('doctrine.environment.' . $options->connection . '.' . $options->environment);
        if($connection === null){
            $connection = $object->config('doctrine.environment.' . $options->connection . '.' . '*');
        }
        if($connection === null){
            throw new Exception('Connection not found aborting...');
        }
        foreach($connection as $key => $value){
            if(substr($key, 0, 1) === '#'){
                unset($connection->{$key});
            }
            $connection->{$key} = $value;
        }
        return $connection;
    }

}