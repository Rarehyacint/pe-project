<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Exam;
use App\Models\User;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;

class LandingPageController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function facultyShow(){
        $ttl = Redis::ttl('dashboard:refresh_timer');
        
        // fallback if key doesn't exist
        $ttl = max(0, $ttl);

        $data = ['ttl' => $ttl];


        return view('faculty-home', $data);
    }

    public function systemReportShow(){
        $exam = Exam::count();

        return view('components/graphs/homepage-system', ['exam' =>$exam]);
    }

    public function examReportShow(){
        $exam_dashboard_data = Http::timeout(30)->get('http://fastapi:8080/api/dashboard/load-exam')->json();
        $examination_dates = collect($exam_dashboard_data['examination_dates'])
            ->map(function ($row) {
                return [
                    'id'   => $row[0],
                    'name' => $row[1],
                    'date' => $row[2],
                ];
            })->values()->toArray();

        $grouped = collect($examination_dates)    
            ->groupBy('date')
            ->map(function ($items) {
                return [
                    'names' => $items->pluck('name')->all(),
                    'count' => $items->count(),
                ];
            });

        $question_exams = collect($exam_dashboard_data['question_exams'])->map(function ($row) {
            try {
                $formatted_date = Carbon::parse($row[3])->format('F j, Y');
            } catch (\Exception $e) {
                $formatted_date = 'No Examination Date';
            }

            return [
                'id'   => $row[0],
                'name' => $row[1],
                'is_published' => $row[2],
                'examination_date' => $formatted_date,
                'question_count' => $row[4]
            ];
        })->toJson();

        $exam_courses = collect($exam_dashboard_data['exam_courses'])->map(function ($row) {
            return [
                'id'   => $row[0],
                'name' => $row[1],
                'abbreviation' => $row[2],
                'exam_count' => $row[3]
            ];
        })->toJson();

        $exam = [
            'count' => $exam_dashboard_data['exam_count'],
            'published_count' => $exam_dashboard_data['published_count'],
            'unpublished_count' => $exam_dashboard_data['unpublished_count'],
            'examination_dates' => $grouped,
            'question_exams' => $question_exams,
            'exam_courses' => $exam_courses,
            'exams' => $exam_dashboard_data
        ];
        
        return view('components/graphs/homepage-exam', $exam);
    }
    public function courseReportShow(){
        $user = auth()->user();
        $user_courses = Course::find($user->getCourseIds());
        $courses_abbv = $user_courses->mapWithKeys(function ($course) {
            return [$course->id => $course->abbreviation];
        });

        return view('components/graphs/homepage-course', ['courses' => $courses_abbv]);
    }

    public function specificCourseReportShow(Request $request){
        $course_id = $request->query('course');
        $course_dashboard_data = Http::timeout(30)
            ->get("http://fastapi:8080/api/dashboard/load-course/{$course_id}")
            ->json();

        $subject_graph_data = collect($course_dashboard_data['subject_table'])->map(function ($row) {
            return [
                'id'   => $row[0],
                'name' => $row[1],
                'question_count' => $row[2]
            ];
        })->toJson();

        $topic_graph_data = collect($course_dashboard_data['topic_table'])->map(function ($row) {
            return [
                'id'   => $row[0],
                'name' => $row[1],
                'question_count' => $row[2]
            ];
        })->toJson();

        $relabel_type = [
            'multiple_choice' => 'MCQ',
            'true_or_false'   => 'T/F',
            'identification'  => 'Identification',
            'ranking'         => 'Ranking',
            'matching'        => 'Matching',
            'coding'          => 'Coding',
        ];

        $question_type_graph_data = collect($course_dashboard_data['question_type_table'])->map(function ($row) use ($relabel_type) {
            return [
                'name'  => $relabel_type[$row[0]] ?? ucfirst(str_replace('_', ' ', $row[2])),
                'question_count' => $row[1],
            ];
        })->toJson();

        $reused_question_graph_data = collect($course_dashboard_data['reused_questions'])->map(function ($row) use ($relabel_type) {
            return [
                'id'             => $row[0],
                'name'           => $row[1],
                'question_type'  => $relabel_type[$row[2]] ?? ucfirst(str_replace('_', ' ', $row[2])),
                'level' => $row[3],
                'status'   => $row[4],
            ];
        })->toJson();

        $course = [
            'question_count' => $course_dashboard_data['question_count'],
            'subject_count' => $course_dashboard_data['subject_count'],
            'topic_count' => $course_dashboard_data['topic_count'],
            'exam_count' => $course_dashboard_data['exam_count'],
            'unused_question_count' => $course_dashboard_data['unused_question_count'],
            'reused_question_count' => $course_dashboard_data['reused_question_count'],
            'subject_graph_data' => $subject_graph_data,
            'topic_graph_data' => $topic_graph_data,
            'question_type_graph_data' => $question_type_graph_data,
            'reused_question_graph_data' => $reused_question_graph_data
        ];

        return view('components/graphs/homepage-specific-course', $course);
    }

    public function refreshDashboard(){
        Http::timeout(30)->get("http://fastapi:8080/api/dashboard/refresh");

        return response('', 200)->header('HX-Refresh', 'true');
    }

    public function getTimer(){
        $ttl = Redis::ttl('dashboard:refresh_timer');

        // fallback if key doesn't exist
        $ttl = max(0, $ttl);

        return view('components/graphs/homepage-dashboard-timer', compact('ttl'));
    }
}
