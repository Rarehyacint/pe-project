<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Report;
use App\Services\ExamService;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class ReportController extends Controller
{
    protected $userService;
    protected $examService;

    public function __construct(UserService $userService, ExamService $examService)
    {
        $this->userService = $userService;
        $this->examService = $examService;
    }

    public function index(){
        $courseIds = $this->userService->getCoursesForUser(auth()->user())->pluck('id');
        $exams = Exam::with(['courses', 'questions'])
            ->whereHas('courses', function ($query) use ($courseIds) {
                $query->whereIn('courses.id', $courseIds);
            })->get();

        $data = [
            'exams' => $exams
        ];
        return view('reports/index', $data);

    }

    public function index_exam(Exam $exam){
        $exam->load('courses');
        $exam->load(['reports' => function ($query) {
            $query->select(
                'id', 
                'exam_id',
                'course_count', 
                'subject_count',
                'topic_count',
                'question_count',
                'student_count',
                'created_at'
            );
        }]);
        $reports = $exam->reports;
    
        return view('reports/index_exam', ['exam' => $exam, 'reports' => $reports]);

    }

    public function show(Exam $exam, Report $report){
        $report_data = $report->report_data;
        $overview_data = $report_data['exam_overview_data'];

        $exam_name = $exam->name;
        $created_date = $report->created_at;

        $courses =  $overview_data['courses'];
        $subject_count = $overview_data['subject_count'];
        $topic_count = $overview_data['topic_count'];
        $question_count = $overview_data['question_count'];
        $student_count = $overview_data['student_count'];
        $question_levels = $overview_data['questions_levels'];

        $exam_summary_data = $report_data['exam_summary_data'];
        $question_levels_summary_data = $report_data['question_levels_summary_data'];
        $subjects_min_max_data = $report_data['subjects_min_max_data'];
        $exam_histogram_boxplot_data = $report_data['exam_histogram_boxplot_data'];
        $normalized_exam_scores_by_subjects = $report_data['normalized_exam_scores_by_subjects'];
        $normalized_exam_scores_by_topics = $report_data['normalized_exam_scores_by_topics'];
        $exam_by_types_with_levels = $report_data['exam_by_types_with_levels'];
        $exam_question_heatstrip = $report_data['exam_question_heatstrip'];
        $data = [
            'exam' => $exam->id,
            'report' => $report->id,
            // metadata
            'exam_name' => $exam_name,
            'created_date' => $created_date,
            'courses' => $courses,
            'blooms_level_contained' => $question_levels,
            // data overview
            'students_count' => $student_count,
            'subjects_count' => $subject_count,
            'topics_count' => $topic_count,
            'questions_count' => $question_count,
            'max_score_range' => $exam_summary_data['exam_maximum_score'],
            // descriptive statistics
            'mean' => $exam_summary_data['mean'],
            'median' => $exam_summary_data['median'],
            'mode' => $exam_summary_data['mode'],
            'max' => $exam_summary_data['max'],
            'min' => $exam_summary_data['min'],
            'range' => $exam_summary_data['range'],
            'std_dev' => $exam_summary_data['standard_deviation'] ?? 0,
            'question_level_summary' => $question_levels_summary_data,
            'subjects_three_min' => $subjects_min_max_data['top_three_min_subjects'],
            'subjects_three_max' => $subjects_min_max_data['top_three_max_subjects'],
            // Exam Scores
            'exam_histogram_boxplot_data' => $exam_histogram_boxplot_data,
            'normalized_exam_scores_by_subjects_data' => $normalized_exam_scores_by_subjects,
            'normalized_exam_scores_by_topics_data' => $normalized_exam_scores_by_topics,
            'exam_question_heatstrip_data' => $exam_question_heatstrip,
            'exam_compare_types_and_blooms_data' =>$exam_by_types_with_levels
        ];

        return view('reports/show', $data);
    }

    public function create(Exam $exam){
        $topics = $this->examService->getTopicsForExam($exam)->count();
        $subjects = $this->examService->getSubjectsForExam($exam)->count();
        $questions =  $exam->questions()->count();
        $enrolled_students = $exam->users()->count();
        $students_that_took_exam = $exam->takers()->count();
        $data = [
            'exam' => $exam,
            'subjects' => $subjects,
            'topics' => $topics,
            'questions' =>$questions,
            'students' => $enrolled_students,
            'takers' => $students_that_took_exam
        ];

        return view('reports/create', $data);
    }

    public function store(Exam $exam){
        $response = Http::timeout(30)
            ->get("http://fastapi:8080/api/reports/create-store/{$exam->id}");

        if (!$response->successful()) {

            session()->flash('toast', json_encode([
                'status' => 'Error!',
                'message' => 'Create Report Unsuccessful, CHECK FASTAPI',
                'type' => 'error'
            ]));

            return response('', 200)->header('HX-Redirect', route('reports.index_exam', ['exam' => $exam]));
        }

        $report_data = $response->json('exam_performance');
        $raw_report_data = $response->json('raw_exam_performance');

        $exam_overview_data = $report_data['exam_overview_data'];
        $course_count = $exam_overview_data['course_count'];
        $subject_count = $exam_overview_data['subject_count'];
        $topic_count = $exam_overview_data['topic_count'];
        $question_count = $exam_overview_data['question_count'];
        $student_count = $exam_overview_data['student_count'];

        $report = $exam->reports()->create([
            'course_count' => $course_count,
            'subject_count' => $subject_count,
            'topic_count' => $topic_count,
            'question_count' => $question_count,
            'student_count' => $student_count,
            'report_data' => $report_data,
            'raw_report_data' =>  $raw_report_data
        ]);



        session()->flash('toast', json_encode([
            'status' => 'Created!',
            'message' => 'Report for: ' . $exam->name,
            'type' => 'success'
        ]));

        return response('', 200)->header('HX-Redirect', route('reports.show', ['exam' => $exam, 'report' => $report]));
    }

    public function destroy(Exam $exam, Report $report){
        // $this->authorize('delete', $subject);

        session()->flash('toast', json_encode([
            'status' => 'Destroyed!',
            'message' => 'Report for: ' . $report->created_at,
            'type' => 'warning'
        ]));

        $report->delete();

        return response('', 200)->header('HX-Redirect', route('reports.index_exam', ['exam' => $exam]));
    }

    public function info(){
        /* Data
        Metadata:
            Exam Name
            Created Date
            Courses (groups)
            Bloom's Taxonomy Level contained

        Data Overview:
            Students Count
            Subjects
            Topics
            Questions
            Missing Values Found
            Duplicates Dropped

        Descriptive Statistics:
            Exam Scores:   
                Mean, Median, Mode
                Standard Deviation
                Range
                Top three min/max subjects for exam scores
            Level Scores:
                Bloom's level exam score normalized shown in percentage
                Top three min/max bloom's level relative to subjects
        
        Exploratory Data Analysis:
            Exam Scores Distribution
            Exam Scores Box Plots
                Overall Exam Score distribution
                    Exam Scores
                Group Exam Score Distribution
                    Course Name
                    Exam Score
            Compare by Question Types with Bloom's Levels
                    Course Name
                    {
                        name: 'MCQ',
                        blooms: {
                        'Remember':   [8, 6, 10],
                        'Understand': [7, 5, 6],
                        'Apply':      [12, 8, 13],
                        'Analyze':    [5, 4, 6],
                        'Evaluate':   [3, 2, 4],
                        'Create':     [1, 1, 2]
                        }
                    },
            Performance Distribution by Question Levels
                    Blooms Level
                    text: 
                    type: 
                    Max Score Attainable:
                    Avg. Score: 
                    % Score (Avg Accuracy)
                    topic: 
                    subject:
                    bloomLevel: 
        */
        // Metadata
        $exam_name = "Example of Exam Report";
        $created_date = "January 1 2025";
        $courses = ['BSCS', 'BSIT'];
        $question_types = ['MCQ', 'T/F', 'Identification', 'Ranking', 'Matching', 'Coding'];
        $blooms_level_contained = [
            'Remember',
            'Understand',
            'Apply',
            'Analyze',
            'Evaluate',
            'Create'
        ];
        // Data Overview
        $students_count = 45;
        $subjects_count = 10;
        $topics_count = 20;
        $questions_count = 90;
        $max_score_range = 120;
        $missing_values = 0;
        $duplicates_dropped = 0;
        //Descriptive Statistics
        $raw_exam_scores = [];
        for ($i = 0; $i < $students_count; $i++) {
            $raw_exam_scores[] = rand(25, $max_score_range);
        }
        $normalized_exam_scores = array_map(function ($score) use ($max_score_range) {
            return [
            'points' => round($score ),
            'Name' => 'Question Name Here',
            'topic' => 'Topic Here',
            'Subject' => 'Subject Here'
        ]; 
        }, $raw_exam_scores);
        $exam_scores = collect($raw_exam_scores);
        $student_per_group_sample = $this->generate_random_sum_parts($students_count, count($courses));
        $offset = 0;
        for ($i = 0; $i < count($courses); $i++) {
            $length = $student_per_group_sample[$i];
            $exam_scores_slice = array_slice($raw_exam_scores, $offset, $length);

            $group_exam_score = array_map(function($score) {   
                 return [
                    'points' => round($score),
                    'Name' => 'Question Name Here',
                    'topic' => 'Topic Here',
                    'Subject' => 'Subject Here'
                 ]; 
            }, $exam_scores_slice);

            $course = $courses[$i];
            $exam_groups[$course] = [
                'name' => $course,
                'exam_score' => $exam_scores_slice,
                'group_exam_score' =>  $group_exam_score
            ];

            $offset += $length; 
        }

        // Mean
        $total_sum = 0;
        $total_count = 0;

        foreach ($exam_groups as $group) {
            $group_scores = $group['exam_score'];
            $total_sum += array_sum($group_scores);
            $total_count += count($group_scores);
        }

        $mean = $total_count > 0 ? round($total_sum / $total_count, 2) : 0;

        // Median
        $median = $exam_scores->sort()->values()->count() % 2 === 0
            ? ($exam_scores->sort()->values()->get($exam_scores->count() / 2 - 1) + $exam_scores->sort()->values()->get($exam_scores->count() / 2)) / 2
            : $exam_scores->sort()->values()->get(floor($exam_scores->count() / 2));

        // Mode
        $counts = $exam_scores->countBy();
        $maxCount = $counts->max();
        $modes = $counts->filter(fn($count) => $count === $maxCount)->keys();
        $mode = $modes->count() === $exam_scores->count() ? null : $modes->all();

        // Range
        $max = $exam_scores->max();
        $min = $exam_scores->min();
        $range = $max - $min;

        // Standard deviation (sample)
        $meanValue = $mean;
        $variance = $exam_scores->reduce(function ($carry, $item) use ($meanValue) {
            return $carry + pow($item - $meanValue, 2);
        }, 0) / ($exam_scores->count() - 1);

        $stdDev = sqrt($variance);
        $subjects_three_min = [
            'Algorithms' => 25,
            'Computer Programming 2' => 15,
            'Data Structures And Algorithms' => 10
        ];
        $subjects_three_max = [
            'Intro to Computing' => 90,
            'Computer Programming 1' => 80,
            'Database' => 60
        ];

        // Level Scores and group scores
        $types_with_level_scores = [];

        for ($i = 0; $i < $questions_count; $i++) {
            $type = $question_types[array_rand($question_types)];

            if (in_array($type, ['MCQ', 'T/F', 'Identification'])) {
                $max_score = rand(1, 5)* $students_count;
                $actual_score = rand(1, $max_score) ;
            } else {
                $max_score = rand(5, 30)* $students_count;
                $actual_score = rand(1, $max_score) ;
            }

            $average_score = $students_count > 0 ? round($actual_score / $students_count, 2) : 0;
            $average_percentage = round(($average_score / ($max_score / $students_count)) * 100, 2);

            $bloom_level = $blooms_level_contained[array_rand($blooms_level_contained)];

            $courseCount = count($courses);
            $splitScores = $this->splitFloatEvenly($average_score, $courseCount);

            foreach (array_values($courses) as $index => $course) {
                if (!isset($types_with_level_scores[$course][$type][$bloom_level])) {
                    $types_with_level_scores[$course][$type][$bloom_level] = 0;
                }
                if (!isset($types_with_level_scores[$course][$type]['max_score_attainable'])) {
                    $types_with_level_scores[$course][$type]['max_score_attainable'] = 0;
                }

                $types_with_level_scores[$course][$type][$bloom_level] += $splitScores[$index];

                $types_with_level_scores[$course][$type]['max_score_attainable'] += $max_score / $students_count;
            }

            $flat_scores[$bloom_level][] = [
                'text' => 'Question Text Here',
                'course' => $course,
                'type' => $type,
                'raw_score' =>$actual_score,
                'average_score' => $average_score,
                'average_percentage' => $average_percentage,
                'max_score_attainable' => $max_score / $students_count,
                'topic' => 'Topic Here',
                'subject' => 'Subject Here'
            ];
        }

        $exam_compare_types_and_blooms_data = [];

        foreach ($question_types as $type) {
            $blooms_data = [];

            // Step 1: Get total raw per course (sum of all bloom scores)
            $totals_per_course = [];
            foreach ($courses as $course) {
                $sum = 0;
                foreach ($blooms_level_contained as $bloom) {
                    $sum += $types_with_level_scores[$course][$type][$bloom] ?? 0;
                }
                $totals_per_course[] = $sum;
            }

            // Step 2: Now go bloom by bloom and normalize
            foreach ($blooms_level_contained as $bloom) {
                $raw = [];
                $normalized = [];

                foreach ($courses as $i => $course) {
                    $score = $types_with_level_scores[$course][$type][$bloom] ?? 0;
                    $raw[] = $score;

                    $total = $totals_per_course[$i];
                    $normalized[] = $total > 0 ? round($score / $total, 4) : 0;
                }

                if (array_sum($raw) > 0) {
                    $blooms_data[$bloom] = [
                        'raw' => $raw,
                        'accuracy' => $normalized
                    ];
                }
            }

            if (!empty($blooms_data)) {
                $exam_compare_types_and_blooms_data[] = [
                    'name' => $type,
                    'blooms' => $blooms_data
                ];
            }
        }

        $data = [
            // metadata
            'exam_name' => $exam_name,
            'created_date' => $created_date,
            'courses' => $courses,
            'blooms_level_contained' => $blooms_level_contained,
            // data overview
            'students_count' => $students_count,
            'subjects_count' => $subjects_count,
            'topics_count' => $topics_count,
            'questions_count' => $questions_count,
            'max_score_range' => $max_score_range,
            'missing_values' => $missing_values,
            'duplicates_dropped' => $duplicates_dropped,
            // descriptive statistics
            'mean' => round($mean, 2),
            'median' => $median,
            'mode' => $mode,
            'max' => $max,
            'min' => $min,
            'range' => $range,
            'std_dev' => round($stdDev, 2),
            'subjects_three_min' => $subjects_three_min,
            'subjects_three_max' => $subjects_three_max,
            // Exam Scores
            'exam_scores' => $normalized_exam_scores,
            'level_scores' => $flat_scores,
            'exam_groups' => $exam_groups,
            'exam_compare_types_and_blooms_data' =>$exam_compare_types_and_blooms_data
        ];

        return view('reports/info', $data);

    }
function generate_random_sum_parts($total, $parts) {
    if ($parts < 1 || $total < $parts) {
        throw new InvalidArgumentException("Invalid input: total must be ≥ parts and parts must be ≥ 1.");
    }

    // Generate (parts - 1) unique cut points between 1 and total - 1
    $cuts = [];
    while (count($cuts) < $parts - 1) {
        $cut = rand(1, $total - 1);
        $cuts[$cut] = true; // use associative keys to ensure uniqueness
    }

    $cuts = array_keys($cuts);
    sort($cuts);

    // Calculate the differences between cuts to get the segments
    $segments = [];
    $previous = 0;
    foreach ($cuts as $cut) {
        $segments[] = $cut - $previous;
        $previous = $cut;
    }
    $segments[] = $total - $previous;

    return $segments;
}

function splitFloatEvenly($total, $parts, $decimals = 2) {
    $random = [];
    $sum = 0;

    // Generate random numbers
    for ($i = 0; $i < $parts; $i++) {
        $rand = mt_rand(1, 100);
        $random[] = $rand;
        $sum += $rand;
    }

    // Scale to total
    $result = [];
    $accumulated = 0;
    for ($i = 0; $i < $parts; $i++) {
        if ($i == $parts - 1) {
            // Make sure the sum matches exactly
            $value = round($total - $accumulated, $decimals);
        } else {
            $value = round(($random[$i] / $sum) * $total, $decimals);
            $accumulated += $value;
        }
        $result[] = $value;
    }

    return $result;
}

}
