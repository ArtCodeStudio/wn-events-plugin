<?php namespace JumpLink\Events\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Events Backend Controller
 */
class Events extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\RelationController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $relationConfig = 'config_relation.yaml';

    public $requiredPermissions = ['jumplink.events.manage_events'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('JumpLink.Events', 'events', 'events');
    }
}
