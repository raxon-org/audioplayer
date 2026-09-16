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
            'content_type' => $object->config('controller.dir.data') . 'System.Server.ContentType' . $object->config('extension.json'),
            'system_application' => $object->config('controller.dir.data') . 'System.Application' . $object->config('extension.json')
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

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function user_list($flags, $options): array
    {
        $object = $this->object();
        $class = 'Account.User';
        $node = new Node($object);
        $role_system = $node->role_system();
        $limit = 100;
        $count = $node->count($class, $role_system);
        $page_count = 1;
        if ($limit > 0) {
            $page_count = ceil($count / $limit);
        }
        if (!property_exists($options, 'sort')) {
            $options->sort = 'uuid';
        }
        if (!is_array($options->sort)) {
            $options->sort = [
                $options->sort => 'ASC'
            ];
        }
        $sort = $options->sort ?? ['uuid' => 'ASC'];
        $filter = $options->filter ?? [];
        if (empty($filter)) {
            $filter = [];
        } elseif (!is_array($filter)) {
            throw new Exception('Filter must be an array.');
        }
        $where = $options->where ?? [];
        if (empty($where)) {
            $where = [];
        } elseif (!is_array($where)) {
            throw new Exception('Where must be an array.');
        }
        $user_list = [];
        for ($page = 1; $page <= $page_count; $page++) {
            $response = $node->list($class, $role_system, [
                'sort' => $sort,
                'filter' => $filter,
                'where' => $where,
                'limit' => $limit,
                'page' => $page
            ]);
            if (
                $response !== null &&
                is_array($response) &&
                array_key_exists('list', $response)
            ) {
                foreach ($response['list'] as $nr => $user) {
                    $user_list[] = $user->uuid ?? null;
                }
            }
        }
        return $user_list;
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function extension_list($flags, $options): array
    {
        $object = $this->object();
        $read = $object->data_read($options->url->node_extension);
        if (!$read) {
            throw new Exception('Node: System.Server.Extension.json not found aborting...');
        }
        $list_search = [];
        $active = [];
        $node_system_server_extension = $read->data('System.Server.Extension');
        foreach ($node_system_server_extension as $extension) {
            $active[] = $extension->name;
            $list_search[$extension->name] = $extension->uuid;
        }
        $data_extension = $object->data_read($options->url->extension);
        if(!$data_extension){
            throw new Exception('Node (Import): System.Server.Extension.json not found aborting...');
        }
        $extensions = [];
        $count = 0;
        foreach ($data_extension->data('System.Server.Extension') as $extension) {
            if (
                is_object($extension) &&
                property_exists($extension, 'name')) {
                if (!in_array($extension->extension, $extensions, true)) {
                    if (array_key_exists($extension->name, $list_search)) {
                        $extensions[] = $list_search[$extension->name];
                    }
                }
                /*
                if(!in_array($extension->name, $active, true)){
                    $record = (object)[
                        'name' => $extension->name,
                        'extension' => $extension->extension,
                    ];
                    $node = new Node($object);
                    $role_system = $node->role_system();
                    $response = $node->create('System.Server.Extension', $role_system, $record);
                    $count++;
                }
                */
            }
        }
        return $extensions;
    }
}