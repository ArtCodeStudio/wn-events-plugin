<?php namespace JumpLink\Events\Controllers;

use BackendMenu;
use Backend\Classes\Controller;

/**
 * Bookings Backend Controller
 */
class Bookings extends Controller
{
    public $implement = [
        \Backend\Behaviors\ListController::class,
        \Backend\Behaviors\FormController::class,
    ];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['jumplink.events.manage_bookings'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('JumpLink.Events', 'events', 'bookings');
    }
}
