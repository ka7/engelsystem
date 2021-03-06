<?php

use Engelsystem\Database\DB;

/**
 * @return string
 */
function admin_questions_title()
{
    return _('Answer questions');
}

/**
 * Renders a hint for new questions to answer.
 *
 * @return string|null
 */
function admin_new_questions()
{
    global $privileges, $page;

    if ($page != 'admin_questions') {
        if (in_array('admin_questions', $privileges)) {
            $new_messages = count(DB::select('SELECT `QID` FROM `Questions` WHERE `AID` IS NULL'));

            if ($new_messages > 0) {
                return '<a href="' . page_link_to('admin_questions') . '">' . _('There are unanswered questions!') . '</a>';
            }
        }
    }

    return null;
}

/**
 * @return string
 */
function admin_questions()
{
    global $user;
    $request = request();

    if (!$request->has('action')) {
        $unanswered_questions_table = [];
        $questions = DB::select('SELECT * FROM `Questions` WHERE `AID` IS NULL');
        foreach ($questions as $question) {
            $user_source = User($question['UID']);

            $unanswered_questions_table[] = [
                'from'     => User_Nick_render($user_source),
                'question' => str_replace("\n", '<br />', $question['Question']),
                'answer'   => form([
                    form_textarea('answer', '', ''),
                    form_submit('submit', _('Save'))
                ], page_link_to('admin_questions', ['action' => 'answer', 'id' => $question['QID']])),
                'actions'  => button(
                    page_link_to('admin_questions', ['action' => 'delete', 'id' => $question['QID']]),
                    _('delete'),
                    'btn-xs'
                )
            ];
        }

        $answered_questions_table = [];
        $questions = DB::select('SELECT * FROM `Questions` WHERE NOT `AID` IS NULL');
        foreach ($questions as $question) {
            $user_source = User($question['UID']);
            $answer_user_source = User($question['AID']);
            $answered_questions_table[] = [
                'from'        => User_Nick_render($user_source),
                'question'    => str_replace("\n", '<br />', $question['Question']),
                'answered_by' => User_Nick_render($answer_user_source),
                'answer'      => str_replace("\n", '<br />', $question['Answer']),
                'actions'     => button(
                    page_link_to('admin_questions', ['action' => 'delete', 'id' => $question['QID']]),
                    _('delete'),
                    'btn-xs'
                )
            ];
        }

        return page_with_title(admin_questions_title(), [
            '<h2>' . _('Unanswered questions') . '</h2>',
            table([
                'from'     => _('From'),
                'question' => _('Question'),
                'answer'   => _('Answer'),
                'actions'  => ''
            ], $unanswered_questions_table),
            '<h2>' . _('Answered questions') . '</h2>',
            table([
                'from'        => _('From'),
                'question'    => _('Question'),
                'answered_by' => _('Answered by'),
                'answer'      => _('Answer'),
                'actions'     => ''
            ], $answered_questions_table)
        ]);
    } else {
        switch ($request->input('action')) {
            case 'answer':
                if ($request->has('id') && preg_match('/^\d{1,11}$/', $request->input('id'))) {
                    $question_id = $request->input('id');
                } else {
                    return error('Incomplete call, missing Question ID.', true);
                }

                $question = DB::selectOne(
                    'SELECT * FROM `Questions` WHERE `QID`=? LIMIT 1',
                    [$question_id]
                );
                if (!empty($question) && $question['AID'] == null) {
                    $answer = trim(
                        preg_replace("/([^\p{L}\p{P}\p{Z}\p{N}\n]{1,})/ui",
                            '',
                            strip_tags($request->input('answer'))
                        ));

                    if ($answer != '') {
                        DB::update('
                                UPDATE `Questions`
                                SET `AID`=?, `Answer`=?
                                WHERE `QID`=?
                                LIMIT 1
                            ',
                            [
                                $user['UID'],
                                $answer,
                                $question_id,
                            ]
                        );
                        engelsystem_log('Question ' . $question['Question'] . ' answered: ' . $answer);
                        redirect(page_link_to('admin_questions'));
                    } else {
                        return error('Enter an answer!', true);
                    }
                } else {
                    return error('No question found.', true);
                }
                break;
            case 'delete':
                if ($request->has('id') && preg_match('/^\d{1,11}$/', $request->input('id'))) {
                    $question_id = $request->input('id');
                } else {
                    return error('Incomplete call, missing Question ID.', true);
                }

                $question = DB::selectOne(
                    'SELECT * FROM `Questions` WHERE `QID`=? LIMIT 1',
                    [$question_id]
                );
                if (!empty($question)) {
                    DB::delete('DELETE FROM `Questions` WHERE `QID`=? LIMIT 1', [$question_id]);
                    engelsystem_log('Question deleted: ' . $question['Question']);
                    redirect(page_link_to('admin_questions'));
                } else {
                    return error('No question found.', true);
                }
                break;
        }
    }

    return '';
}
