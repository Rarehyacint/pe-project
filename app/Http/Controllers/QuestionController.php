<?php

namespace App\Http\Controllers;

use App\Factories\QuestionFactory;
use App\Models\MultipleChoiceQuestion;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\QuestionService;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Str;
use Validator;

class QuestionController extends Controller
{
    protected $userService;
    protected $questionService;

    public function __construct(UserService $userService, QuestionService $questionService)
    {
        $this->userService = $userService;
        $this->questionService = $questionService;

    }

    public function index(){
        $questions = $this->userService->getQuestionsForUser(auth()->user())->paginate(10);
        $header = ['ID', 'Name', 'Subject', 'Topic', 'Type', 'Author', 'Date Created'];
        $rows = $questions->map(function ($question) {
            return [
                'id' => $question->id,
                'name' => $question->name,
                'subject' => $question->topic->subject->name,
                'topic' => $question->topic->name,
                'type' => $question->question_type->name,
                'author' => $question->author->getFullName(),
                'Date Created' => Carbon::parse($question->created_at)->format('m/d/Y')
            ];
        });

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'questions' => $questions
        ];

        return view('questions/index', $data);
    }

    public function show(Question $question){
        $question->load('topic.subject.course');
        $question->load([
            'multipleChoiceQuestions',
            'trueOrFalseQuestion',
            'identificationQuestion',
            'rankingQuestions',
            'matchingQuestions',
        ]);
        $question_type = $question->getTypeModel();
        $data = [
            'question' => $question,
            'question_type' => $question_type
        ];
        return view('questions/show', $data);
    }
    public function create(){
        $courses = $this->userService->getCoursesForUser(auth()->user());
        // $subjects = $this->userService->getSubjectsForUser(auth()->user());
        $courses = $courses->pluck('name', 'id');
        // $subjects = $subjects->pluck('name', 'id');
        $question_types = [
            '' => 'Select A Question Type',
            'multiple_choice' => 'Multiple Choice',
            'true_or_false'=> 'True or False',
            'identification' => 'Identification',
            'ranking' => 'Ranking/Ordering/Process',
            'matching' => 'Matching Items'
        ];

        $data =[
            'courses' => $courses,
            // 'subjects' => $subjects,
            'question_types' => $question_types
        ];

        return view('questions/create', $data);
    }

    public function createCodingQuestion(){
        $courses = $this->userService->getCoursesForUser(auth()->user())->pluck('name', 'id');
        $programming_languages = [
            'c++' => "C++",
            'java' => "Java",
            'sql' => "SQL",
            'python' => "Python",
        ];

        $markdown = Str::of('- *Laravel*')->markdown();

        $data =[
            'courses' => $courses,
            'programming_languages' => $programming_languages,
            'markdown' => $markdown
        ];

        return view('questions-types/coding', $data);
    }

    public function store(){
        $validator = Validator::make(request()->all(), [
            'topic' => ['required', 'exists:topics,id'],
            'type' => ['required'],
            'name' => ['required', 'string', 'unique:questions,name'],
            'points' => ['required', 'integer', 'min:1'],
            'items.*' => ['required', 'string', 'min:1'],
            'solution' => ['required', 'string'], 
            'subject' => ['required'],
        ], [
            'items.*.required' => 'This field is required.',
        ]);

        $question_type = request('type');
        $item_count = count(request()->input('items', []));
        if ($validator->fails()) {
            return redirect()->route('question.types', ['type' => $question_type, 'item_count' => $item_count])
                ->withErrors($validator)
                ->withInput();
        }

        $data = $validator->validated();

        \Log::info('data can be stored', $data);
        QuestionFactory::create($data);
        \Log::info('Question Creation Successful');
        
        return response('', 200)->header('HX-Redirect', url('/questions'));
    }
    public function edit(Question $question){
        $subjects = Subject::whereIn('course_id', $question->subject->course()->get()->pluck('id'))->get()->pluck('name', 'id');

        $data = [
            'question' => $question, 
            'subjects' => $subjects
        ];
    
        return view('questions/edit', $data);
    }

    public function update(Question $question){
        $this->authorize('update', $question);


        request()->validate([
            'name'    => ['required'],
            'subject'     => ['required', 'integer'],
        ]);

        $question->update([
            'name' => request('name'),
            'subject_id' => request('subject'),
        ]);

        return redirect()->route('questions.show', $question);
    }
    public function destroy(Question $question){

        $this->authorize('delete', $question);

        $question->delete();

        return redirect('/questions');

    }

    public function getSubjectsForCourses(Request $request){
        $courseId = $request->input('course');
        
        $course = $this->userService->getCourseById($courseId);
        $subjects = $course->subjects->pluck('name', 'id');
        $subjects = $subjects->isEmpty() ? null : $subjects;

        if(empty($subjects)){
            return view('/components/core/partials-subject', ['subjects' => [""=>"No Subjects Available"]]);
        }   

        return view('/components/core/partials-subject', ['subjects' => $subjects]);    
    }
    public function getTopicsForSubjects(Request $request){
        $subjectId = $request->input('subject');

        if(empty($subjectId)){
            return view('/components/core/partial-topic', ['topics' => [""=>"No Topics Available"]]);    
        }   

        $subject = $this->userService->getSubjectById($subjectId);

        $topics = $subject->topics->pluck('name', 'id');

        if(empty($topics)){
            return view('/components/core/partial-topic', ['topics' => ["No Topics Available"]]);    
        }   

        return view('/components/core/partial-topic', ['topics' => $topics]);    
    }

    public function question_type_show(Question $question){
        $choices = $this->questionService->getQuestionTypeShow($question);
        
        $data = [
            'question' => $question,
            'choices' => $choices
        ];

        return view('questions-types/show', $data);
    }

    public function previewMarkdown(Request $request){
        $data = $request->post();
        $markdown = Str::of($request->post('instruction'))->markdown([
            'html_input' => 'strip',
        ]);

        return view('components/core/preview-markdown', ['data'=> $data, 'markdown' => $markdown]);
    }

    public function togglePreviewButton(){

        return view('components/core/toggle-preview');
    }

    public function validateCompleteSolution(Request $request){
        return view('questions-types/validate-complete-solution', ['data'=>$request->post()]);
    }

}
