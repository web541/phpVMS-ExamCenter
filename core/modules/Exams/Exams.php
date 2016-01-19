<?php
//simpilotgroup addon module for phpVMS virtual airline system
//
//simpilotgroup addon modules are licenced under the following license:
//Creative Commons Attribution Non-commercial Share Alike (by-nc-sa)
//To view full icense text visit http://creativecommons.org/licenses/by-nc-sa/3.0/
//
//@author David Clark (simpilot)
//@copyright Copyright (c) 2009-2010, David Clark
//@license http://creativecommons.org/licenses/by-nc-sa/3.0/

class Exams extends CodonModule {
	
	public $title = 'Exam Center';

    public function index() 
	{
        if(!Auth::LoggedIn()) {
            $this->set('message', '<div id="error"><b>You must be logged in to access this feature!</b></div><br />');
            $this->show('frontpage_main');
            return;
        }
        else {
            $open = ExamsData::get_setting_info('2');
            if ($open->value == '0') {
                $message = ExamsData::get_setting_info('3');
                echo '<div id="error">'.$message->value.'</div>';
            }
            else {
                $pid = Auth::$userinfo->pilotid;
                $message = ExamsData::get_setting_info('4');
                $this->set('message', '<h4>'.$message->value.'</h4>');
                $this->set('exams', ExamsData::get_exams());
                $this->set('pilotmoney', Auth::$userinfo->totalpay);
                $this->show('exams/exam_list');
            }
        }
    }

    public function request_exam()  
	{
        $id = $_GET['id'];
        $pilot_id = Auth::$userinfo->pilotid;

        ExamsData::request_exam($pilot_id, $id);

        $pid = Auth::$userinfo->pilotid;
        $message = ExamsData::get_setting_info('4');
        $this->set('message', '<h4>'.$message->value.'</h4>');
        $this->set('exams', ExamsData::get_exams());
        $this->set('pilotmoney', Auth::$userinfo->totalpay);
        $this->show('exams/exam_list');
    }

    public function buy_exam() 
	{
        $id = $_GET['id'];
        $pid = Auth::$userinfo->pilotid;

        $examcost = ExamsData::buy_exam($id);
        $pilotmoney = Auth::$userinfo->totalpay;

        if ($examcost->cost > $pilotmoney) {
            $this->set('message', '<div id="error"><b>You do not have enough funds in your company account to purchase the '.$examcost->exam_description.' exam!</b></div>');
            $this->set('exams', ExamsData::get_exams());
            $this->set('pilotmoney', $pilotmoney);
            $this->show('exams/exam_list');
        } else {
            $this->set('examdescription', $examcost->exam_description);
            $this->set('examid', $examcost->id);
            $this->set('examcost', $examcost->cost);
            $this->set('pilotmoney', $pilotmoney);
            $this->show('exams/exam_purchase_confirm');
        }
    }

    public function purchase_exam() 
	{
        $exam_id = $_GET['id'];

        $exam = ExamsData::get_exam($exam_id);

        $pid = Auth::$userinfo->pilotid;

        $this->set('pilotpay', ExamsData::pay_for_exam($pid, $exam_id));
        $this->set('questions', $exam);
        $this->set('title', ExamsData::get_exam_title($exam_id));
        $this->set('howmany_questions', ExamsData::get_howmany_questions($exam_id));
        $this->show('exams/exam');
    }

    public function grade_exam() 
	{
        $exam_id = DB::escape($this->post->exam_id);
        $howmany = DB::escape($this->post->howmany);
        $exam_description = DB::escape($this->post->exam_description);
        $passing = DB::escape($this->post->passing);
        $version = DB::escape($this->post->version);
        $i=1;
        $correct=0;
        $this->set('title', $exam_description);
        $this->show('exams/exam_question_result_header');
        while ($i<= $howmany):

            $id = 'question_id' . $i;
            $question_id = DB::escape($this->post->$id);
            $id2 = 'question' . $i;
            $answer = DB::escape($this->post->$id2);

            $cor = ExamsData::compare_answer($question_id, $answer);
            if ($cor->correct_answer == $answer) {
                Template::ClearVars($wrong);
                $question = ExamsData::get_question($question_id);
                $this->set('question', $question->question );
                if ($question->correct_answer == '1') {$this->set('answer', $question->answer_1 );}
                elseif ($question->correct_answer == '2') {$this->set('answer', $question->answer_2 );}
                elseif ($question->correct_answer == '3') {$this->set('answer', $question->answer_3 );}
                elseif ($question->correct_answer == '4') {$this->set('answer', $question->answer_4 );}
                $this->set('number', $i);
                $this->set('div', 'success');
                $this->show('exams/exam_question_result');
                $correct++;
            } else {
                $question = ExamsData::get_question($question_id);
                $this->set('question', $question->question );
                if ($question->correct_answer == '1') {$this->set('answer', $question->answer_1 );}
                elseif ($question->correct_answer == '2') {$this->set('answer', $question->answer_2 );}
                elseif ($question->correct_answer == '3') {$this->set('answer', $question->answer_3 );}
                elseif ($question->correct_answer == '4') {$this->set('answer', $question->answer_4 );}

                if ($answer == '1') {$this->set('wrong', $question->answer_1 );}
                elseif ($answer == '2') {$this->set('wrong', $question->answer_2 );}
                elseif ($answer == '3') {$this->set('wrong', $question->answer_3 );}
                elseif ($answer == '4') {$this->set('wrong', $question->answer_4 );}
                $this->set('number', $i);
                $this->set('div', 'error');
                $this->show('exams/exam_question_result');
            }
            $i++;
        endwhile;
        $result = round((($correct / $howmany) * 100), 0);
        if ($result >= $passing) {
            $passfail = 1;
        }
        else {
            $passfail = 0;
        }

        $pid = Auth::$userinfo->pilotid;

        $approve = ExamsData::get_setting_info('5');
        if ($approve->value == '1'); {ExamsData::unassign_exam($pid, $exam_id);}
        ExamsData::record_results($pid, $exam_id, $exam_description, $result, $passfail, $version);

        if ($result >= $passing) {
            echo '<tr><td colspan="2"><br /><h4>You Passed With A '.$result.'% On The Exam.</h4></td></tr>';
        }
        else {
            echo '<tr><td colspan="2"><br /><h4>You Did Not Pass The Exam.<br /> A '.$passing.'% Is Required To Pass The Exam.<br />You Scored '.$result.'%</h4></td></tr>';
        }
        echo '</table><br />';
        echo '<form method="link" action="'.SITE_URL.'/index.php/Exams/view_profile">';
        echo '<input type="submit" value="Return To Exam Center"></form>';
    }

    public function view_profile() {
        $id = Auth::$userinfo->pilotid;

        $this->set('pilotdata', ExamsData::get_pilot_data($id));
        $this->show('exams/exam_view_profile');
    }
}