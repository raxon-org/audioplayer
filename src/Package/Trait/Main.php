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
    const DISPLAY_NAME = 'Audio Player';
    const ROUTE_NAME = 'application-audio-player';
    const ICON_URL = '/Application/Audioplayer/Icon/Icon.png';
    const EXTENSION_ENABLED = 'System.Server.Extension.Enabled';
    const CONTENT_TYPE_ENABLED = 'System.Server.ContentType.Enabled';
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
        $url = $object->config('project.dir.node') . 'Data' . $object->config('ds') . 'System.Server.Extension.json';
        $read = $object->data_read($url);
        if(!$read){
            throw new Exception('System.Server.Extension.json not found aborting...');
        }
        $list_search = [];
        foreach($read->data() as $extension){
            dd($extension);           
            $list_search[$extension->name] = $extension->uuid;
        }
        /*
        $url = $object->config('controller.dir.data') .
            self::EXTENSION_ENABLED .
            $object->config('extension.json');
        $data_extension = $object->data_read($url);
        $extensions_add = [];
        $content_types_add = [];
        if($data_extension){
            foreach($data_extension->data(self::EXTENSION_ENABLED) as $extension){
                if(
                    is_object($extension) &&
                    property_exists($extension, 'name')){
                    if(!in_array($extension->extension, $extensions_add, true)){
                        $extensions_add[] = $extension;
                    }
                }
            }
        }
        */
        $url = $object->config('controller.dir.data') .
            self::EXTENSION_ENABLED .
            $object->config('extension.json');
        $data_extension = $object->data_read($url);
        $extensions = [];
        if($data_extension){
            foreach($data_extension->data(self::EXTENSION_ENABLED) as $extension){
                if(
                    is_object($extension) &&
                    property_exists($extension, 'name')){
                    if(!in_array($extension->extension, $extensions, true)){
                        if(array_key_exists($extension->name, $list_search)){
                            $extensions[] = $list_search[$extension->name];
                        }
                    }
                }
            }
        }
        $class = 'Account.User';
        $node = new Node($object);
        $role_system = $node->role_system();
        $limit = 100;
        $count = $node->count($class, $role_system);
        $page_count = 1;
        if($limit > 0){
            $page_count = ceil($count / $limit);
        }
        if(!property_exists($options, 'sort')){
            $options->sort = 'uuid';
        }
        if(!is_array($options->sort)){
            $options->sort = [
                $options->sort => 'ASC'
            ];
        }
        $sort = $options->sort ?? ['uuid'=> 'ASC'];
        $filter = $options->filter ?? [];
        if(empty($filter)){
            $filter = [];
        }
        elseif(!is_array($filter)){
            throw new Exception('Filter must be an array.');
        }
        for($page = 1; $page <= $page_count; $page++) {
            $response = $node->list($class, $role_system, [
                'sort' => $sort,
                'filter' => $filter,
                'limit' => $limit,
                'page' => $page
            ]);
            if (
                $response !== null &&
                is_array($response) &&
                array_key_exists('list', $response)
            ) {
                $user_list = [];
                foreach ($response['list'] as $nr => $user) {
                    $user_list[] = $user->uuid ?? null;
                }
                $class = 'System.Application';
                $role = $node->role_system();
                $record = [
                    "name" => self::NAME,
                    "user" => $user_list,
                    "display" => (object) [
                        'name' => self::DISPLAY_NAME,
                    ],
                    "url" => '',
                    "icon_url" => '/Application/' . self::NAME . '/Icon/Icon.png',
                    'description' => 'Audio Player (Playing mp3, wav & ogg)',
                    'extension' => $extensions,
                ];
                $exist = $node->record($class, $role, [
                    'where' => [
                        [
                            'value' => self::NAME,
                            'attribute' => 'name',
                            'operator' => '===',
                        ]
                    ]
                ]);
                if($exist === null){
                    $response = $node->create($class, $role, $record);
                } else {
                    dd($exist);
                }
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
        }
        return $connection;
    }

}