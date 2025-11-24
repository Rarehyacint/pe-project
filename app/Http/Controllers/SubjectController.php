<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Validator;

class SubjectController extends Controller
{

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
    public function index(){
        $subject_courses = $this->userService->getSubjectsForUser(auth()->user())->paginate(10);
        $subject_courses->load('courses');
        $header = ['Courses', 'Code', 'Name', 'Year Level'];
        $rows = $subject_courses->map(function ($subject) {
            $courses_abbreviations = $subject->courses->map(function ($course){
                return $course->abbreviation;
            });
            return [
                'id' => $subject->id,
                'courses' => $courses_abbreviations,
                'code' => $subject->code,
                'name' => $subject->name,
                'year level' => $subject->year_level
            ];
        });

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'models' => $subject_courses,
            'url' => 'subjects'
        ];

        if (request()->hasHeader('HX-Request') && !request()->hasHeader('HX-History-Restore-Request')) {
            // Return only the partial view for HTMX
            return view('components/core/index-table', $data);
        }

        return view('subjects/index', $data);
    }

    public function show(Subject $subject){
        $subject->load('courses');
        $header = ['Topic', 'Name', 'Type', 'Date Created'];
        $questions = Question::with(['topic'])
            ->whereIn('topic_id', $subject->topics->pluck('id'))
            ->Paginate(5);

        $rows = $questions->map(fn($question) => [
            'id' => $question->id,
            'topic' => $question->topic->name,
            'name' => $question->name,
            'type' => $question->question_type->name,
            'Date Created' => Carbon::parse($question->created_at)->format('m/d/Y'),
        ]);
        $data = [
            'headers' => $header,
            'rows' => $rows,
            'subject'=>$subject,
            'models' => $questions,
            'url' => 'questions'
        ];

        if (request()->hasHeader('HX-Request') && !request()->hasHeader('HX-History-Restore-Request')) {
            // Return only the partial view for HTMX
            return view('components/core/index-table', $data);
        }

        return view('subjects/show', $data);
    }

    public function create(){
        $courses = $this->userService->getCoursesForUser(auth()->user());

        return view('subjects/create', ['courses' => $courses]);
    }

    public function store(){

        $validator = Validator::make(request()->post(), [
            'name'    => ['required', 'unique:subjects,name'],
            'code' => ['required', 'unique:subjects,code'],
            'courses' => 'required|array',
            'courses.*' => 'exists:courses,id',
            'year_level' => ['required', 'integer', 'min:1', 'max:4']
        ]);

        if ($validator->fails()) {
            $courses = $this->userService->getCoursesForUser(auth()->user());
            return response()->view('subjects.create', [
                'errors' => $validator->errors(),
                'courses' => $courses,
                'old' => request()->all()]);
        }

        $validated = $validator->validate();
        $subject = Subject::create([
            'name' => request('name'),
            'code' => request('code'),
            'year_level' => request('year_level'),
        ]);

        $subject->courses()->attach($validated['courses']);

        session()->flash('toast', json_encode([
            'status' => 'Created!',
            'message' => 'Subject: ' . $subject->name,
            'type' => 'success'
        ]));

        return response('', 200)->header('HX-Redirect', route('subjects.index'));
    }

    public function edit(Subject $subject){
        $courses = Course::all()->pluck('name', 'id');

        $data = [
            'subject' => $subject, 
            'courses' => $courses
        ];
    
        return view('subjects/edit', $data);
    }

    public function update(Subject $subject){
        request()->validate([
            'name'    => ['required', Rule::unique('subjects', 'name')->ignore($subject->id)],
            'code'    => ['required', Rule::unique('subjects', 'code')->ignore($subject->id)],
            'year_level' => ['required', 'integer', 'min:1', 'max:4']
        ]); 
        $subject->update([
            'name' => request('name'),
            'code' => request('code'),
            'year_level' => request('year_level'),
        ]);

        
        session()->flash('toast', json_encode([
            'status' => 'Updated!',
            'message' => 'Subject: ' . $subject->name,
            'type' => 'info'
        ]));

        return redirect()->route('subjects.show', $subject);
    }
    public function destroy(Subject $subject){

        $this->authorize('delete', $subject);

        if ($subject->topics()->exists()) {
            return back()->with('error', 'You cannot delete a subject that has topics.');
        }

        session()->flash('toast', json_encode([
            'status' => 'Destroyed!',
            'message' => 'Subject: ' . $subject->name,
            'type' => 'warning'
        ]));

        $subject->delete();

        return redirect('/subjects');

    }

    public function showQuestions(Subject $subject){
        $subject->load('topics.questions');
        $header = ['Topic', 'Name', 'Type', 'Author', 'Date Created'];
        $rows = $subject->topics->flatMap(function ($topics) {
            return $topics->questions;
        })->map(fn($question) => [
            'id' => $question->id,
            'topic' => $question->topic->name,
            'name' => $question->name,
            'type' => $question->question_type->name,
            'author' => $question->author->getFullName(),
            'Date Created' => Carbon::parse($question->created_at)->format('m/d/Y')
        ]);
        $data = [
            'headers' => $header,
            'rows' => $rows,
            'subject'=>$subject
        ];
        return view('subjects/questions', $data);
    }
}
