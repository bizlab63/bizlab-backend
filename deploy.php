<?php
namespace Deployer;

require 'recipe/symfony.php';

// Config

set('repository', '');

add('shared_files', []);
add('shared_dirs', []);
add('writable_dirs', []);

// Hosts

host('89.208.209.23')
    ->set('remote_user', 'debian')
    ->setSshMultiplexing(true)
    ->set('deploy_path', '~/bizlab');

// Hooks

after('deploy:failed', 'deploy:unlock');
