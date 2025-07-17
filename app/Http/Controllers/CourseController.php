<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CourseController extends Controller
{

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
    public function index(){
        $course_courses = Course::all();
        $header = ['ID', 'Name', 'Date Created'];
        $rows = $course_courses->map(function ($course) {
            return [
                'id' => $course->id,
                'name' => $course->name,
                'Date Created' => Carbon::parse($course->created_at)->format('m/d/Y')
            ];  
        });

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'courses' => $course_courses
        ];

        return view('courses/index', $data);
    }

    public function show(Course $course){
        return view('courses/show', ['course' => $course]);
    }

    public function create(){
        return view('courses/create');
    }

    public function store(){
        request()->validate([
            'name'    => ['required'],
            'abbreviation' => ['required']
        ]);

        Course::create([
            'name' => request('name'),
            'abbreviation' => request('abbreviation')
        ]);

        return redirect('/courses');
    }

    public function edit(Course $course){
        $data = [
            'course' => $course, 
        ];
    
        return view('courses/edit', $data);
    }

    public function update(Course $course){
        request()->validate([
            'name'    => ['required'],
            'abbreviation' => ['required']        
        ]); 
        
        $course->update([
            'name' => request('name'),
            'abbreviation' => request('abbreviation')        
        ]);

        return redirect()->route('courses.show', $course);
    }
    public function destroy(Course $course){

        $this->authorize('delete', $course);

        if ($course->subjects()->exists()) {
            return back()->with('error', 'You cannot delete a course that has subjects.');
        }

        $course->delete();

        return redirect('/courses');

    }

        public function showSubjects(Course $course){
        $course->load('subjects')->paginate(10);

        $header = ['ID', 'Name', 'Year Level', 'Date Created'];
        $rows = $course->subjects->map(function ($subject) {
            return [
                'id' => $subject->id,
                'name' => $subject->name,
                'type' => $subject->year_level,
                'Date Created' => Carbon::parse($subject->created_at)->format('m/d/Y')
            ];
        });

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'course'=>$course
        ];
        return view('courses/subjects', $data);
    }
}
