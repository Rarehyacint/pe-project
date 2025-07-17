<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SubjectController extends Controller
{

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
    public function index(){
        $subject_courses = $this->userService->getSubjectsForUser(auth()->user())->paginate(10);
        $header = ['ID', 'Course', 'Name',  'Year Level', 'Date Created'];
        $rows = $subject_courses->map(function ($subject) {
            return [
                'id' => $subject->id,
                'course' => $subject->course->name,
                'name' => $subject->name,
                'year_level' => $subject->year_level,
                'Date Created' => Carbon::parse($subject->created_at)->format('m/d/Y')
            ];
        });

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'subjects' => $subject_courses
        ];

        return view('subjects/index', $data);
    }

    public function show(Subject $subject){
    
        return view('subjects/show', ['subject' => $subject]);
    }

    public function create(){
        $courses = Course::all()->pluck('name', 'id');

        return view('subjects/create', ['courses' => $courses]);
    }

    public function store(){
        request()->validate([
            'name'    => ['required'],
            'course'     => ['required', 'integer'],
            'year_level' => ['required', 'integer', 'min:1', 'max:4']
        ]);

        Subject::create([
            'name' => request('name'),
            'course_id' => request('course'),
            'year_level' => request('year_level'),
        ]);

        return redirect('/subjects');
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
            'name'    => ['required'],
            'year_level' => ['required', 'integer', 'min:1', 'max:4']
        ]); 
        $subject->update([
            'name' => request('name'),
            'year_level' => request('year_level'),
        ]);

        return redirect()->route('subjects.show', $subject);
    }
    public function destroy(Subject $subject){

        $this->authorize('delete', $subject);

        if ($subject->topics()->exists()) {
            return back()->with('error', 'You cannot delete a subject that has topics.');
        }

        $subject->delete();

        return redirect('/subjects');

    }

    public function showQuestions(Subject $subject){
        $subject->load('topics.questions');
        $header = ['ID', 'Topic', 'Name', 'Type', 'Author', 'Date Created'];
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
