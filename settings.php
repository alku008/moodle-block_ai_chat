<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Settings for the local_ai_manager plugin.
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $ADMIN->add(
        'blocksettings',
        new admin_category('block_ai_chat_settings', new lang_string('pluginname', 'block_ai_chat'))
    );

    if ($ADMIN->fulltree) {
        $settings->add(
            new admin_setting_configtextarea(
                'block_ai_chat/showonpagetypes',
                new lang_string('showonpagetypes', 'block_ai_chat'),
                new lang_string('showonpagetypesdesc', 'block_ai_chat'),
                '',
            )
        );
        $settings->add(
            new admin_setting_configtextarea(
                'block_ai_chat/agentmodeunavailablepagetypes',
                new lang_string('agentmodeunavailablepagetypes', 'block_ai_chat'),
                new lang_string('agentmodeunavailablepagetypesdesc', 'block_ai_chat'),
                "page-mod-hvp-mod"
            )
        );
        $settings->add(
            new admin_setting_configcheckbox(
                'block_ai_chat/replacehelp',
                new lang_string('replacehelp', 'block_ai_chat'),
                '',
                0,
            )
        );
        $settings->add(
            new admin_setting_configtext(
                'block_ai_chat/personalink',
                new lang_string('personalink', 'block_ai_chat'),
                '',
                '',
                PARAM_TEXT
            )
        );

        // Lokal (15.11.2026): Wissensdateien der Personas und Dateien im Chat.
        $settings->add(new admin_setting_heading('block_ai_chat/wissensdateienheading',
            new lang_string('wissensdateienheading', 'block_ai_chat'), new lang_string('wissensdateienheadingdesc', 'block_ai_chat')));
        $settings->add(new admin_setting_configtext('block_ai_chat/kursmaxfiles',
            new lang_string('kursmaxfiles', 'block_ai_chat'), new lang_string('kursmaxfilesdesc', 'block_ai_chat'), 5, PARAM_INT));
        $settings->add(new admin_setting_configtext('block_ai_chat/personamaxfiles',
            new lang_string('personamaxfiles', 'block_ai_chat'), new lang_string('personamaxfilesdesc', 'block_ai_chat'), 5, PARAM_INT));
        $settings->add(new admin_setting_configtext('block_ai_chat/wissenmaxbytes',
            new lang_string('wissenmaxbytes', 'block_ai_chat'), '', 5, PARAM_INT));
        $settings->add(new admin_setting_configtext('block_ai_chat/weblinksmax',
            new lang_string('weblinksmax', 'block_ai_chat'), new lang_string('weblinksmaxdesc', 'block_ai_chat'), 5, PARAM_INT));
        $settings->add(
            new admin_setting_configtext(
                'block_ai_chat/embeddingmodel',
                new lang_string('embeddingmodel', 'block_ai_chat'),
                new lang_string('embeddingmodeldesc', 'block_ai_chat'),
                'BAAI/bge-m3',
                PARAM_TEXT
            )
        );
        // Prüfen, ob das Embedding-Modell erreichbar ist: eine kurze Probe-Anfrage, nur beim Aufruf genau dieser Einstellungsseite im
        // Browser (nicht bei der Admin-Suche, beim Upgrade oder auf der Kommandozeile).
        if (!CLI_SCRIPT && !during_initial_install() && empty($CFG->upgraderunning)
                && ($_GET['section'] ?? '') === 'blocksettingai_chat' && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/settings.php') !== false) {
            $probe = \block_ai_chat\local\wissen::embeddings(['Test'], 5);
            $weg = \block_ai_chat\local\wissen::ueber_ai_manager()
                ? get_string('embeddingweg_manager', 'block_ai_chat', s(\block_ai_chat\local\wissen::modell()))
                : get_string('embeddingweg_direkt', 'block_ai_chat');
            $settings->add(new admin_setting_description('block_ai_chat/embeddingstatus', '',
                ($probe ? '✅ ' . get_string('embeddingok', 'block_ai_chat', count($probe[0]))
                    : '⚠️ ' . get_string('embeddingfehlt', 'block_ai_chat')) . '<br>' . $weg));
        }
        // Lokal (17.11.2026): Wer die Fragen der Lernenden einsehen darf, ist ein Recht der Rollen (local/ai_manager:viewprompts),
        // keine Plugin-Einstellung – hier Stand und Links zu den Rollen, damit es auffindbar ist.
        $zeilen = [];
        foreach (get_archetype_roles('editingteacher') + get_archetype_roles('teacher') as $rolle) {
            $wert = $DB->get_field('role_capabilities', 'permission', ['roleid' => $rolle->id,
                'capability' => 'local/ai_manager:viewprompts', 'contextid' => context_system::instance()->id]);
            $stand = $wert == CAP_PROHIBIT ? get_string('prompteinsicht_verboten', 'block_ai_chat')
                : ($wert == CAP_ALLOW ? get_string('prompteinsicht_erlaubt', 'block_ai_chat') : get_string('prompteinsicht_nicht', 'block_ai_chat'));
            $zeilen[] = html_writer::tag('li', html_writer::link(new moodle_url('/admin/roles/define.php',
                ['action' => 'edit', 'roleid' => $rolle->id]), role_get_name($rolle)) . ': ' . $stand);
        }
        // Als abgesetzter, farbiger Kasten mit Linien oben und unten (Wunsch der Nutzerin, 17.11.2026).
        $linie = '<hr style="border: 0; border-top: 1px solid #c9d1db; opacity: 1; margin: 1.2em 0;">';
        $settings->add(new admin_setting_heading('block_ai_chat/prompteinsicht', '', '<div style="height: 0.8em;"></div>' . $linie
            . '<div style="background: #fffbea; border: 1px solid #f2e3a6; border-left: 4px solid #e8c547; border-radius: 6px; '
            . 'padding: 14px 18px;">'
            . '<h4 style="margin: 0 0 8px 0; font-size: 1.05rem;">' . get_string('prompteinsicht', 'block_ai_chat') . '</h4>'
            . '<div>' . get_string('prompteinsichtdesc', 'block_ai_chat') . '</div>'
            . html_writer::tag('ul', implode('', $zeilen), ['style' => 'margin: 8px 0 0 0;'])
            . '</div>' . $linie));
        $settings->add(new admin_setting_configtextarea('block_ai_chat/grundanweisung',
            new lang_string('grundanweisung', 'block_ai_chat'), new lang_string('grundanweisungdesc', 'block_ai_chat'), ''));
        $settings->add(new admin_setting_configtextarea('block_ai_chat/startvorschlaege',
            new lang_string('startvorschlaege', 'block_ai_chat'), new lang_string('startvorschlaegedesc', 'block_ai_chat'),
            \block_ai_chat\local\anhang::STARTVORSCHLAEGE));
        $settings->add(new admin_setting_configtextarea('block_ai_chat/folgevorschlaege',
            new lang_string('folgevorschlaege', 'block_ai_chat'), new lang_string('folgevorschlaegedesc', 'block_ai_chat'),
            \block_ai_chat\local\anhang::FOLGEVORSCHLAEGE));
        $settings->add(new admin_setting_configcheckbox('block_ai_chat/chatupload',
            new lang_string('chatupload', 'block_ai_chat'), new lang_string('chatuploaddesc', 'block_ai_chat'), 1));
        $settings->add(new admin_setting_configtext('block_ai_chat/chatuploadsperday',
            new lang_string('chatuploadsperday', 'block_ai_chat'), new lang_string('chatuploadsperdaydesc', 'block_ai_chat'), 10, PARAM_INT));
        $settings->add(new admin_setting_configtext('block_ai_chat/chatuploadmaxchars',
            new lang_string('chatuploadmaxchars', 'block_ai_chat'), new lang_string('chatuploadmaxcharsdesc', 'block_ai_chat'),
            20000, PARAM_INT));

        // Add text with link to management as setting.
        $settings->add(new admin_setting_description(
            'block_ai_chat/manageaicontext',
            get_string('linktomanageaicontextpage', 'block_ai_chat'),
            html_writer::link(
                new moodle_url('/blocks/ai_chat/manage_aicontext.php'),
                get_string('manageaicontext', 'block_ai_chat'),
                ['class' => 'btn btn-secondary mb-5']
            )
        ));
    }
}
