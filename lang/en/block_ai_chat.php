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
 * Language file for block_ai_chat
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addaicontext'] = 'Add AI context entry';
$string['addblockinstance'] = 'Add an AI Chat to this course';
$string['addblockinstance_help'] = 'Adds an AI Chat to this course. The AI chat will be removed including all conversations if the checkbox is unchecked.';
$string['addpersonatitle'] = 'Add new persona';
$string['agentmodeunavailableonpagemessage'] = 'You cannot use the agent mode here, because it is not working with this page type.';
$string['agentmodeunavailableonpagetitle'] = 'Agent mode unavailable';
$string['agentmodeunavailablepagetypes'] = 'Pagetypes on which the agent mode will be unavailable';
$string['agentmodeunavailablepagetypesdesc'] = 'Insert page ids (one string per line) on which the agent mode should not be available. The string has to exactly match the id attribute of the body tag.';
$string['ai_chat'] = 'AI Chat';
$string['ai_chat:addinstance'] = 'Add an AI Chat block';
$string['ai_chat:edit'] = 'Configure the AI Chat block';
$string['ai_chat:manageaicontext'] = 'Manage AI context sent along with requests';
$string['ai_chat:managepersonatemplates'] = 'Manage global persona templates';
$string['ai_chat:myaddinstance'] = 'Add an AI Chat block to my moodle';
$string['ai_chat:useagentmode'] = 'Use agent mode';
$string['ai_chat:view'] = 'Access the AI Chat block';
$string['aichatnotavailable'] = 'The AI chat is currently not available.';
$string['aicontext'] = 'AI context';
$string['aicontextdeleted'] = 'AI context deleted';
$string['aicontextdescription'] = 'Description what the context is and what it is for (optional)';
$string['aicontextsaved'] = 'The AI context has been saved';
$string['areyousuredelete'] = 'Are you sure you want to delete this persona?';
$string['areyousuredeletetemplate'] = 'This will remove the global template for all AI chats on the whole site. Are you sure you want to delete this template?';
$string['awaitanswer'] = 'AI generating...';
$string['backtochat'] = 'Back to chat';
$string['chatwindow'] = 'Open as chat window';
$string['delete'] = 'Delete current dialog';
$string['deletetemplate'] = 'Delete template';
$string['deletewarning'] = 'Are you sure you want to delete this conversation? The conversation will be permanently hidden from you, but will remain stored in the system.';
$string['disabled'] = 'disabled';
$string['dockright'] = 'Dock on the right';
$string['duplicatepersonaname'] = '{$a} - copy';
$string['editpersonatitle'] = 'Edit persona';
$string['enabled'] = 'enabled';
$string['error_managepersonanotallowed'] = 'You do not have the permission to manage this persona.';
$string['error_viewpersonanotallowed'] = 'You do not have the permission to view this persona.';
$string['erroremptyprompt'] = 'Please enter a non-empty prompt';
$string['errorformfieldempty'] = 'The field must not be empty';
$string['errorhistorycontextmax'] = 'History must be a number and bigger than 0';
$string['errorname'] = 'Name can\'t be empty';
$string['errornotallowedtochangetype'] = 'You are not allowed to change the type of the persona';
$string['errorpersonanotfound'] = 'Persona with the specified ID not found in the database.';
$string['errorprompt'] = 'Prompt can\'t be empty';
$string['errorpromptdataurl'] = 'The prompt may contain only formatted text. Multimedia content is not allowed.';
$string['erroruserinfo'] = 'Userinfo can\'t be empty';
$string['errorwithcode'] = 'An error occurred with code {$a}';
$string['exception_aicontextidmissing'] = 'Cannot find an AI context record with the given id';
$string['exception_aicontextnotfound'] = 'Could not find request AI context record';
$string['floatingbuttontitle'] = 'AI Chat bot';
$string['history'] = 'History';
$string['historycontextmax'] = 'Amount of last messages sent along for context';
$string['historylengthinfo'] = 'Number of previous messages that are sent to the AI as conversation context.';
$string['input'] = 'Send a message to the AI';
$string['linktomanageaicontextpage'] = 'Link to the AI context management page';
$string['manageaicontext'] = 'Manage additional AI context';
$string['managepersona'] = 'Manage personas';
$string['modeagent'] = 'Agent';
$string['modechat'] = 'Chat';
$string['modeinfo'] = 'Click to toggle between "chat" and "agent" mode';
$string['name'] = 'Name';
$string['newdialog'] = 'New AI Chat';
$string['newpersonadefaultname'] = 'New persona';
$string['newpersonadefaultprompt'] = 'You are a new persona without any specific instructions. Answer as helpfully as possible.';
$string['newpersonadefaultuserinfo'] = 'New persona display information';
$string['nohistory'] = 'Chat history not found';
$string['nopersona'] = 'No persona';
$string['notice'] = 'Notice';
$string['openfull'] = 'Use full width';
$string['pagetypes'] = 'Page types on which the context should be injected automatically';
$string['personabannerinfo'] = 'The chat is currently using this persona';
$string['personainfomodalpersonalink'] = 'What is a persona?';
$string['personainfomodaltitle'] = 'Persona information';
$string['personalink'] = 'Infolink to personas';
$string['personaprompt'] = 'Persona prompt';
$string['personaprompt_help'] = 'You can use a full text editor for formatting your persona prompt. Please consider that the complete formatting will be sent as HTML to the external AI system.<br/>For best results it is recommended to use only basic formatting that helps to structure the prompt.';
$string['personatemplateditwarning'] = 'You are editing a global persona template. Changes will affect all AI chats who use this persona.';
$string['pluginname'] = 'AI Chat';
$string['pluginname_userfaced'] = 'AI chatbot';
$string['preferences'] = 'Preferences';
$string['privacy:metadata'] = 'Conversations are saved by local_ai_manager.';
$string['prompt'] = 'Prompt';
$string['purposeplacedescription_mainwindow'] = 'Chat functionality in the main window';
$string['replacehelp'] = 'Replace help button with block_ai_chat button';
$string['selectpersona'] = 'Select persona';
$string['showhistory'] = 'Show history';
$string['showonpagetypes'] = 'Pagetypes on which the chat bot floating button should be shown';
$string['showonpagetypesdesc'] = 'Insert a list of page types (one string per line) on which the floating button should be shown. Insert "*" to always show the block.';
$string['suggestionaccept'] = 'Accept suggestion';
$string['suggestiondecline'] = 'Decline suggestion';
$string['suggestionexplanation'] = 'Explanation';
$string['suggestionfieldname'] = 'Settingname';
$string['suggestionlabel'] = 'Suggestion';
$string['suggestiontarget'] = 'Target field';
$string['templatepersonas'] = 'System templates';
$string['toolsofaibutton'] = 'AI tools accessible via the AI button: {$a}';
$string['type'] = 'Persona type';
$string['typetemplate'] = 'Global template';
$string['typeuser'] = 'User persona';
$string['userinfo'] = 'Info shown to users';
$string['userpersonas'] = 'My personas';
$string['yesterday'] = 'Yesterday';

// Local extension (15.11.2026): knowledge files for personas.
$string['embeddingmodel'] = 'Embedding model for persona knowledge files';
$string['embeddingmodeldesc'] = 'Model name for /v1/embeddings of the Telli connector (AIS.chat API). Without a reachable embedding model, matching sections are found by word comparison.';
$string['wissensdateien'] = 'Knowledge files';
$string['wissensdateienheading'] = 'Knowledge files and file attachments';
$string['wissensdateienheadingdesc'] = 'Personas and courses can have knowledge files: persona files apply in all courses using the persona, course files (AI control center) only in that course. Matching sections are sent with each question. Users can also attach a text file to a chat question.';
$string['embeddingok'] = 'Embedding model reachable ({$a} dimensions).';
$string['embeddingfehlt'] = 'Embedding model not reachable – knowledge files are searched by word comparison.';
$string['chatupload'] = 'Allow file attachments in the chat';
$string['chatuploaddesc'] = 'Users (including students) can attach a text file (txt, md, py, ipynb, csv, …) to a question.';
$string['chatuploadsperday'] = 'Attachments per user and day';
$string['chatuploadsperdaydesc'] = '0 = unlimited.';
$string['chatuploadmaxchars'] = 'Maximum characters per attachment';
$string['chatuploadmaxcharsdesc'] = 'Longer files are cut. About 4 characters correspond to one token.';
$string['kursmaxfiles'] = 'Knowledge files per course';
$string['kursmaxfilesdesc'] = 'Teachers manage them via the AI control center of the course. 0 = no course knowledge files.';
$string['kurswissen'] = 'Knowledge files for the AI chat in this course';
$string['kurswissen_help'] = 'Text, code, notebook, Word or PDF files. For every chat question in this course, only the best matching sections are sent to the AI. Do not upload solutions.';
$string['kurswisseninfo'] = '📍 <strong>Applies only in this course</strong>, for every persona: for every question in the AI chat of this course, the best matching sections of these files are sent along. Files for all courses belong to a persona (AI chat: edit persona). Upload tasks, notebooks or fact sheets, but no solutions: the AI might reveal them.';
$string['kurswissengespeichert'] = 'Knowledge files saved: {$a->dateien} files, {$a->abschnitte} sections.';
$string['kurswissenunveraendert'] = 'No changes to the knowledge files.';
$string['kurswissenaus'] = 'Course knowledge files are switched off by the administration.';
$string['personawissen'] = 'Knowledge files of this persona';
$string['personawissen_help'] = 'These files apply in ALL courses where this persona is selected. Files for one course only are uploaded in the AI control center of that course. Do not upload solutions.';
$string['personawissenhinweis'] = '🌐 <strong>Applies in all courses</strong> where this persona is selected. Files <strong>for one course only</strong> go into the <strong>AI control center</strong> of that course (📚 knowledge files).';
$string['wissenmaxbytes'] = 'Maximum size per knowledge file (MB)';
$string['personawissen'] = 'Knowledge files of this persona';
$string['personawissen_help'] = 'These files apply in ALL courses where this persona is selected. Files for one course only are uploaded in the AI control center of that course. Do not upload solutions.';
$string['personawissenhinweis'] = '🌐 <strong>Applies in all courses</strong> where this persona is selected. Files <strong>for one course only</strong> go into the <strong>AI control center</strong> of that course (📚 knowledge files).';
$string['wissenmaxbytes'] = 'Maximum size per knowledge file (MB)';
$string['personamaxfiles'] = 'Knowledge files per persona';
$string['personamaxfilesdesc'] = 'They apply in all courses where the persona is selected. 0 = no persona knowledge files.';
$string['startvorschlaege'] = 'Starter suggestions in an empty chat';
$string['startvorschlaegedesc'] = 'One suggestion per line. A click writes it into the input field; "…" is selected. Empty = none.';
$string['folgevorschlaege'] = 'Buttons after an answer (stepwise help)';
$string['folgevorschlaegedesc'] = 'One line per button: "Label | text that is sent". Empty = none.';
$string['kurswissenerzeugen'] = 'Create from course contents';
$string['kurswissenerzeugeninfo'] = 'Builds the knowledge files from the course contents: text areas, pages, assignment descriptions, Unilabel, lessons and books plus files in them (notebooks, PDF, Word, text). In the next step you see everything that was found and choose. Quizzes, surveys and H5P are always left out.';
$string['kurswissenerzeugenfrage'] = 'The current knowledge files of this course will be replaced. Continue?';
$string['kurswissenerzeugt'] = '{$a} knowledge files created from the course contents. Sections are prepared in the background (a few minutes).';
$string['kurswissenerzeugenjetzt'] = 'Create knowledge files';
$string['kurswissenvorschauinfo'] = 'Check the selection: only ticked contents go into the knowledge files. ⚠️ marks contents that are not preselected – hidden, with access restrictions (e.g. group only, after submission, from a date) or named like a solution or for teachers. Never tick solutions: the AI might reveal them. Files named like solutions are always skipped.';
