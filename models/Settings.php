<?php namespace JumpLink\Events\Models;

use Model;

/**
 * Plugin-Einstellungen (Buchungs-Empfänger, Absender, Buchung aktiv/inaktiv).
 */
class Settings extends Model
{
    public $implement = ['System.Behaviors.SettingsModel'];

    public $settingsCode = 'jumplink_events_settings';

    public $settingsFields = 'fields.yaml';

    public function initSettingsData()
    {
        $this->booking_enabled = true;
        $this->notify_name = null;
        $this->notify_email = null;
        $this->sender_email = null;
        $this->sender_name = null;
        $this->send_customer_copy = true;
    }
}
