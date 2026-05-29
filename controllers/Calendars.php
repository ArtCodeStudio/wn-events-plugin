<?php namespace JumpLink\Events\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Calendars Backend Controller
 */
class Calendars extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ReorderController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';
    public $reorderConfig = 'config_reorder.yaml';

    public $requiredPermissions = ['jumplink.events.manage_events'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('JumpLink.Events', 'events', 'calendars');
    }
}
