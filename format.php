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

defined('MOODLE_INTERNAL') || die();

/**
 * Simple CSV question format
 *
 * A simple format for fast and easy creating multiple choice questions with
 * one or more correct answers and no feedback using tables in any spreadsheet
 * by saving it as plain/csv file.
 *
 * The format looks like this:
 *
 * "Category","Question text","CA 1",...,"CA n","","WA 1", ... ,"WA m"
 *
 * That is,
 *
 *  - CA - Correct Answer, WA - Wrong Answer
 *  - one question - one row
 *  - en empty cell sepparates correct answers from wrong ones
 *  - category column is optional and it is used when selected during import
 *  - numbers of CAs and WAs may differ from each other inside the row and between rows
 *
 * @package    qformat_simplecsv
 * @copyright  2020 Paweł Suwiński
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qformat_simplecsv extends qformat_default {

    public static $csvdelimiter = ',';
    public static $csvenclosure = '"';

    public function provide_import() {
        return true;
    }

    public function export_file_extension() {
        return '.csv';
    }

    public function readdata($filename) {
        if (($handle = fopen($filename, "r")) === false) {
            return false;
        }
        while (($data = fgetcsv($handle, 4096, self::$csvdelimiter,
                self::$csvenclosure)) !== false) {
            $filearray[] = $data;
        }
        fclose($handle);
        return $filearray;
    }

    protected function readquestions($lines) {
        $questions = array();

        foreach ($lines as $row) {

            $question = $this->defaultquestion();

            if (!is_array($row) || empty($row[0])) {
                continue;
            }

            // Category processing.
            if ($this->catfromfile) {
                $newcategory = addslashes(htmlspecialchars(array_shift($row)));
                if (!isset($category) || $category != $newcategory) {
                    $category = $newcategory;
                    $question->qtype = 'category';
                    $question->category = $category;
                    $questions[] = $question;

                    // Clear array for next question set.
                    $question = $this->defaultquestion();
                } else {
                    unset($newcategory);
                }
            }

            // Build a new question.
            $question->qtype = 'multichoice';
            $question->single = 1; // One CA by default.
            $question->questiontext = addslashes(htmlspecialchars(array_shift($row)));
            $question->questiontextformat = FORMAT_HTML;
            // Skip row if empty.
            if (empty($question->questiontext)) {
                // Recall newcategory creation for this row.
                if ($this->catfromfile && isset($newcategory)) {
                    array_pop($questions);
                }
                continue;
            }
            $question->name = $this->create_default_question_name($question->questiontext, '');
            $question->answer = array();
            $question->fraction = array();
            $question->feedback = array();

            // Collect CORRECT ANSWERS and WRONG ANSWERS.
            $cafraction = 1; // Correct answer fraction for single = 1.
            $correctanswers = array();
            $wronganswers = array();
            $cacollected = false;

            foreach ($row as $field) {
                // Empty filed breakes CAs and starts WAs.
                if (empty($field)) {
                    $cacollected = true;
                    continue;
                }
                if (!$cacollected) {
                    $correctanswers[] = $field;
                } else {
                    $wronganswers[] = $field;
                }
            }

            // Skip row if  CAa or WAs empty.
            if (empty($correctanswers) || empty($wronganswers)) {
                // Recall newcategory creation for this row.
                if ($this->catfromfile && isset($newcategory)) {
                    array_pop($questions);
                }
                continue;
            }

            $cacnt = count($correctanswers);

            // More than one CA case.
            if ($cacnt > 1) {
                $question->single = 0;
                $cafraction = 1 / $cacnt;
            }

            // Make answers from CAs and WAs.
            foreach ($correctanswers as $answer) {
                 $this->answer_push($question, $answer, $cafraction);
            }
            foreach ($wronganswers as $answer) {
                 $this->answer_push($question, $answer);
            }

            $questions[] = $question;

        }
        $this->setMatchgrades('nearest');
        return $questions;
    }

    private function answer_push(stdClass $question, string $answer, float $fraction = 0) {
        $question->answer[] = $this->text_field($answer);
        $question->fraction[] = $fraction;
        $question->feedback[] = $this->text_field('');
    }

    protected function text_field($text) {
        return array(
            'text' => addslashes(htmlspecialchars(trim($text), ENT_NOQUOTES)),
            'format' => FORMAT_HTML,
            'files' => array(),
        );
    }

    protected function readquestion($lines) {
        return;
    }
}

