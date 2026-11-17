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

namespace block_ai_chat\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for block_ai_chat.
 *
 * Conversations themselves are stored and handled by local_ai_manager (request log). Local extension (AIS.chat Hamburg):
 * the question is also sent to an embedding endpoint to search the knowledge files, and the knowledge file sections used for
 * an answer are stored per log entry (block_ai_chat_quellen).
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /** Log entries of block_ai_chat that have sources, joined to the request log. */
    private const JOIN = "FROM {block_ai_chat_quellen} q
                          JOIN {local_ai_manager_request_log} l ON l.id = q.logid AND l.component = 'block_ai_chat'";

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_ai_chat_quellen', [
            'logid' => 'privacy:metadata:quellen:logid',
            'quellen' => 'privacy:metadata:quellen:quellen',
        ], 'privacy:metadata:quellen');
        $collection->add_external_location_link('embedding', [
            'prompttext' => 'privacy:metadata:embedding:prompttext',
        ], 'privacy:metadata:embedding');
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:files');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql("SELECT DISTINCT l.contextid " . self::JOIN . " WHERE l.userid = :userid", ['userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $userlist->add_from_sql('userid', "SELECT l.userid " . self::JOIN . " WHERE l.contextid = :contextid",
            ['contextid' => $userlist->get_context()->id]);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $rows = $DB->get_records_sql("SELECT q.id, q.logid, q.quellen, l.timecreated " . self::JOIN
                . " WHERE l.userid = :userid AND l.contextid = :contextid", ['userid' => $userid, 'contextid' => $context->id]);
            if ($rows) {
                writer::with_context($context)->export_data([get_string('pluginname', 'block_ai_chat'), 'quellen'],
                    (object)['quellen' => array_values(array_map(fn($r) => ['logid' => $r->logid,
                        'quellen' => json_decode($r->quellen, true), 'timecreated' => $r->timecreated], $rows))]);
            }
        }
    }

    /** Delete the source rows of the given log selection. */
    private static function loeschen(string $where, array $params): void {
        global $DB;
        $ids = $DB->get_fieldset_sql("SELECT q.id " . self::JOIN . " WHERE $where", $params);
        foreach (array_chunk($ids, 500) as $teil) {
            $DB->delete_records_list('block_ai_chat_quellen', 'id', $teil);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        self::loeschen('l.contextid = :contextid', ['contextid' => $context->id]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        foreach ($contextlist->get_contexts() as $context) {
            self::loeschen('l.contextid = :contextid AND l.userid = :userid',
                ['contextid' => $context->id, 'userid' => $contextlist->get_user()->id]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        if (!$userlist->get_userids()) {
            return;
        }
        [$in, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        self::loeschen("l.contextid = :contextid AND l.userid $in", $params + ['contextid' => $userlist->get_context()->id]);
    }
}
