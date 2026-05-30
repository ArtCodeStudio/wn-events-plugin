<?php namespace JumpLink\Events\Components;

use BackendAuth;
use Cms\Classes\ComponentBase;
use JumpLink\Events\Models\Event;

/**
 * EventInlineEditor – OPTIONALE Frontend-Inline-Bearbeitung von Veranstaltungs-
 * Titel/Untertitel/Beschreibung über Samuell.ContentEditor (ContentTools).
 *
 * Die Komponente lädt nur dann etwas, wenn (a) das ContentEditor-Plugin
 * installiert ist UND (b) ein Backend-Nutzer mit Editor-Berechtigung
 * eingeloggt ist. Für normale Besucher ist sie vollständig inaktiv.
 *
 * Ablauf: ContentTools wird bereits durch die im Layout eingebundene
 * `[contenteditor]`-Komponente geladen. Diese Komponente ergänzt nur ein
 * kleines Bridge-Skript (assets/inline-editor.js), das die rivets-gerenderten
 * Event-Felder nachträglich als ContentTools-Regionen markiert. Beim Speichern
 * ruft ContentTools den hier definierten Handler `onSave` auf (statt in eine
 * Content-Datei zu schreiben), der den Wert in das Event-Model speichert.
 */
class EventInlineEditor extends ComponentBase
{
    /** Felder, die per Inline-Editor bearbeitet werden dürfen. */
    protected $editableFields = ['title', 'subtitle', 'description'];

    public function componentDetails()
    {
        return [
            'name'        => 'Veranstaltungs-Inline-Editor',
            'description' => 'Optionales Bearbeiten von Titel/Untertitel/Beschreibung direkt im Frontend (benötigt das Samuell.ContentEditor-Plugin).',
        ];
    }

    /**
     * Ist das ContentEditor-Plugin überhaupt vorhanden? (Integration optional.)
     */
    public function contentEditorInstalled()
    {
        return class_exists('\Samuell\ContentEditor\Components\ContentEditor');
    }

    /**
     * Darf der aktuelle Nutzer inline bearbeiten? Gleiche Berechtigung wie
     * ContentEditor selbst – wer ContentEditor nutzen darf, darf auch Events
     * bearbeiten.
     */
    public function canEdit()
    {
        if (!$this->contentEditorInstalled()) {
            return false;
        }
        $user = BackendAuth::getUser();
        return $user && $user->hasAccess('samuell.contenteditor.editor');
    }

    public function onRun()
    {
        if (!$this->canEdit()) {
            return;
        }
        // ContentTools selbst wird bereits von der [contenteditor]-Komponente im
        // Layout geladen – hier nur die Bridge ergänzen.
        $this->addJs('assets/inline-editor.js');
        $this->page['jlEventsInlineEdit'] = true;
    }

    /**
     * AJAX-Handler, den ContentTools beim Speichern aufruft
     * (data-component="eventInlineEditor::onSave"). Erwartet:
     *   file    = Regionsname "jlevent:{id}:{field}"
     *   content = neuer Inhalt der Region
     */
    public function onSave()
    {
        if (!$this->canEdit()) {
            throw new \ApplicationException('Keine Berechtigung zum Bearbeiten.');
        }

        $region = (string) post('file');
        if (!preg_match('/^jlevent:(\d+):([a-z]+)$/', $region, $m)) {
            throw new \ApplicationException('Ungültige Editor-Region: ' . $region);
        }

        $id    = (int) $m[1];
        $field = $m[2];

        if (!in_array($field, $this->editableFields, true)) {
            throw new \ApplicationException('Feld ist nicht editierbar: ' . $field);
        }

        $event = Event::find($id);
        if (!$event) {
            throw new \ApplicationException('Veranstaltung #' . $id . ' nicht gefunden.');
        }

        $content = (string) post('content');

        if ($field === 'description') {
            // Beschreibung ist ein HTML-Feld (richeditor) – HTML erhalten.
            $event->description = trim($content);
        } else {
            // Titel/Untertitel sind reine Textfelder – Markup entfernen.
            $event->{$field} = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        }

        $event->save();

        return ['jlevent_saved' => $id . ':' . $field];
    }
}
