<?php namespace JumpLink\Events\Console;

use Illuminate\Console\Command;

/**
 * ImportFirestore – importiert Kalender & Events aus der Firestore-REST-API
 * in die lokale Datenbank. (Implementierung folgt.)
 */
class ImportFirestore extends Command
{
    protected $signature = 'jumplink:events-import
                            {--dry-run : Nur anzeigen, nichts schreiben}
                            {--force : Vorhandene Datensätze überschreiben}';

    protected $description = 'Importiert Kalender und Events aus Firestore in die lokale Datenbank.';

    public function handle()
    {
        $this->info('Import-Befehl noch nicht implementiert.');
        return 0;
    }
}
