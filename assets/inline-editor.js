/**
 * JumpLink.Events – Frontend-Inline-Editor-Bridge
 *
 * Wird NUR geladen, wenn ein Backend-Editor eingeloggt und Samuell.ContentEditor
 * installiert ist (siehe EventInlineEditor::onRun). ContentTools selbst wird
 * bereits durch die [contenteditor]-Komponente im Layout geladen und einmalig
 * via contenteditor.js initialisiert (editor.init auf [data-editable],
 * [data-fixture]).
 *
 * Aufgabe dieser Bridge: Die Veranstaltungs-Felder werden clientseitig per
 * rivets gerendert – also NACH dem einmaligen editor.init(). Wir markieren die
 * gerenderten Felder daher nachträglich als ContentTools-Regionen und rufen
 * syncRegions() auf, damit ContentTools sie aufgreift. Das Speichern läuft über
 * den Standard-„saved"-Handler von contenteditor.js, der den in data-component
 * genannten AJAX-Handler (eventInlineEditor::onSave) mit { file, content }
 * aufruft – dieser speichert in das Event-Model statt in eine Content-Datei.
 *
 * Die Template-Hooks pro Feld:
 *   data-jl-field="title|subtitle|description"   (statisch)
 *   data-jl-id="{event.id}"                       (via rivets rv-data-jl-id)
 */
(function () {
    'use strict';

    var SAVE_HANDLER = 'eventInlineEditor::onSave';

    function annotate(node) {
        if (node.getAttribute('data-jl-ready')) {
            return false;
        }
        var id = node.getAttribute('data-jl-id');
        var field = node.getAttribute('data-jl-field');
        if (!id || !field) {
            return false; // rivets hat die id noch nicht gebunden -> später erneut
        }

        node.setAttribute('data-file', 'jlevent:' + id + ':' + field);
        node.setAttribute('data-component', SAVE_HANDLER);

        if (field === 'description') {
            // Block-Inhalt (HTML, mehrzeilig) -> volle ContentTools-Region
            node.setAttribute('data-editable', '');
        } else {
            // Titel/Untertitel -> einzeiliger Fixture (reiner Text)
            node.setAttribute('data-fixture', '');
            node.setAttribute('data-ce-tag', 'p');
        }

        node.setAttribute('data-jl-ready', '1');
        return true;
    }

    function sync() {
        if (!window.ContentTools || !window.ContentTools.EditorApp) {
            return;
        }
        var nodes = document.querySelectorAll('[data-jl-field]:not([data-jl-ready])');
        var changed = false;
        for (var i = 0; i < nodes.length; i++) {
            if (annotate(nodes[i])) {
                changed = true;
            }
        }
        if (changed) {
            try {
                window.ContentTools.EditorApp.get().syncRegions();
            } catch (e) {
                if (window.console) {
                    console.warn('[jumplink.events] inline-editor syncRegions:', e);
                }
            }
        }
    }

    function init() {
        // Direkt einmal versuchen (falls Events bereits gerendert sind) …
        setTimeout(sync, 500);

        // … und auf rivets-Nachrenderungen reagieren (Events kommen async aus der API).
        if (typeof MutationObserver !== 'undefined') {
            var timer = null;
            var observer = new MutationObserver(function () {
                clearTimeout(timer);
                timer = setTimeout(sync, 250);
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState !== 'loading') {
        init();
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }
})();
