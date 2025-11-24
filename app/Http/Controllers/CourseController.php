<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Subject;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }
    public function index(){
        $courses = Course::with('subjects')->paginate(10);
        $header = ['Name'];
        $rows = $courses->map(function ($course) {
            return [
                'id' => $course->id,
                'name' => $course->name
            ];  
        });

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'models' => $courses,
            'url' => 'courses'
        ];

        if (request()->hasHeader('HX-Request') && !request()->hasHeader('HX-History-Restore-Request')) {
            // Return only the partial view for HTMX
            return view('components/core/index-table', $data);
        }

        return view('courses/index', $data);
    }

    public function show(Course $course){
        $subject_count = $course->subjects()->count();
        $header = ['Name', 'Year Level', 'Date Created'];
        $subjects = $course->subjects()->paginate(5);


        $rows = collect($subjects->items())->map(fn($subject) => [
            'id' => $subject->id,
            'name' => $subject->name,
            'type' => $subject->year_level,
            'Date Created' => $subject->created_at->format('m/d/Y'),
        ]);

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'course'=>$course,
            'models' => $subjects,
            'subject_count' => $subject_count,
            'url' => 'subjects'
        ];

        
        if (request()->hasHeader('HX-Request') && !request()->hasHeader('HX-History-Restore-Request')) {
            // Return only the partial view for HTMX
            return view('components/core/index-table', $data);
        }

        return view('courses/show', $data);
    }

    public function create(){
        return view('courses/create');
    }

    public function store(){
        $data = request()->post();
        $data['abbreviation'] = strtoupper($data['abbreviation']);

        $validator = Validator::make($data, [
            'name'    => ['required', 'unique:courses,name'],
            'abbreviation' => ['required', 'unique:courses,abbreviation']
        ]);

        if ($validator->fails()) {
            return response()->view('courses.create', [
                'errors' => $validator->errors(),
                'old' => $data]);
        }

        $course = Course::create([
            'name' => $data['name'],
            'abbreviation' => $data['abbreviation']
        ]);

        session()->flash('toast', json_encode([
            'status' => 'Created!',
            'message' => 'Course: ' . $course->name,
            'type' => 'success'
        ]));

        return response('', 200)->header('HX-Redirect', route('courses.index'));
    }

    public function edit(Course $course){
        $data = [
            'course' => $course, 
        ];
    
        return view('courses/edit', $data);
    }

    public function update(Course $course){
        $data = request()->all();
        $data['abbreviation'] = strtoupper($data['abbreviation']);
        request()->merge($data);

        request()->validate([
            'name' => [
                'required',
                Rule::unique('courses', 'name')->ignore($course->id),
            ],
            'abbreviation' => [
                'required',
                Rule::unique('courses', 'abbreviation')->ignore($course->id),
            ],
        ]);


        $course->update([
            'name' => $data['name'],
            'abbreviation' => $data['abbreviation']   
        ]);

        session()->flash('toast', json_encode([
            'status' => 'Updated!',
            'message' => 'Course: ' . $course->name,
            'type' => 'info'
        ]));

        return redirect()->route('courses.show', $course);
    }
    public function destroy(Course $course){

        $this->authorize('delete', $course);

        if ($course->subjects()->exists()) {
            return back()->with('error', 'You cannot delete a course that has subjects.');
        }

        session()->flash('toast', json_encode([
            'status' => 'Destroyed!',
            'message' => 'Course: ' . $course->name,
            'type' => 'warning'
        ]));

        $course->delete();

        return redirect('/courses');

    }
}
