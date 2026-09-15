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

trait Main {
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
            'node' => $object->config('project.dir.node') . 'Data' . $object->config('ds') . 'System.Server.Extension.json',
            'controller' => $object->config('controller.dir.data') . self::EXTENSION_ENABLED . $object->config('extension.json'),
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
    public function install_system_application(object $flags, object $options)
    {
        $object = $this->object();
        if(!property_exists($options, 'url')){
            throw new Exception('Option -url not set');
        }
        if (!property_exists($options->url, 'node')) {
            throw new Exception('Option -url.node not set');
        }
        if(!property_exists($options->url, 'controller')){
            throw new Exception('Option -url.controller not set');
        }
        $read = $object->data_read($options->url->node);
        if (!$read) {
            throw new Exception('System.Server.Extension.json not found aborting...');
        }
        $list_search = [];
        foreach ($read->data('System.Server.Extension') as $extension) {
            $list_search[$extension->name] = $extension->uuid;
        }
        $data_extension = $object->data_read($options->url->controller);
        $extensions = [];
        if ($data_extension) {
            foreach ($data_extension->data(self::EXTENSION_ENABLED) as $extension) {
                if (
                    is_object($extension) &&
                    property_exists($extension, 'name')) {
                    if (!in_array($extension->extension, $extensions, true)) {
                        if (array_key_exists($extension->name, $list_search)) {
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
        $user_list = [];
        for ($page = 1; $page <= $page_count; $page++) {
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
                foreach ($response['list'] as $nr => $user) {
                    $user_list[] = $user->uuid ?? null;
                }
            }
        }
        $class = 'System.Application';
        $role = $node->role_system();
        $record = (object)[
            'name' => self::NAME,
            'user' => $user_list,
            'display' => (object)[
                'name' => self::DISPLAY_NAME,
            ],
            'directory' => (object)[
                'application' => 'Application/' . self::NAME . '/',
                'icon' => '/Application/' . self::NAME . '/Icon/Icon.png',
            ],
            'method' => null,
            'target' => null,
            'description' => self::DESCRIPTION,
            'extension' => $extensions,
        ];
        $environment = $object->config('framework.environment');
        if (property_exists($options->frontend->url, $environment)) {
            $record->url = $options->frontend->url->{$environment} . $record->directory->application;
            $record->icon_url = $options->frontend->url->{$environment} . $record->directory->icon;
        } else {
            throw new Exception('Frontend url not set for environment: ' . $environment);
        }
        $exist = $node->record($class, $role, [
            'where' => [
                [
                    'value' => self::NAME,
                    'attribute' => 'name',
                    'operator' => '===',
                ]
            ]
        ]);
        if ($exist === null) {
            $response = $node->create($class, $role, $record);
            echo $record->name . ' created...' . PHP_EOL;
        } else {
            if (
                property_exists($options, 'patch') &&
                $options->patch === true
            ) {
                $record->uuid = $exist['node']->uuid;
                $response = $node->patch($class, $role, $record);
                echo $record->name . ' patched...' . PHP_EOL;
            }
        }
    }
}