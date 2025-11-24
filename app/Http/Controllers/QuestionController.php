<?php

namespace App\Http\Controllers;

use App\Factories\QuestionFactory;
use App\Models\MultipleChoiceQuestion;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\QuestionService;
use App\Services\UserService;
use Illuminate\Support\Facades\Http;
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
        $questions->load('author'); 
        $header = ['Name', 'Subject', 'Topic', 'Type', 'Author'];
        $rows = $questions->map(function ($question) {
            return [
                'id' => $question->id,
                'name' => $question->name,
                'subject' => $question->topic->subject->code,
                'topic' => $question->topic->name,
                'type' => $question->question_type->name,
                'author' => $question->author->getFullName() ?? "No Author"
            ];
        });

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'models' => $questions,
            'url' => 'questions'
        ];

        if (request()->hasHeader('HX-Request') && !request()->hasHeader('HX-History-Restore-Request')) {
            // Return only the partial view for HTMX
            return view('components/core/index-table', $data);
        }

        return view('questions/index', $data);
    }

    public function show(Question $question){
        $question->load([
            'questionLevel',
            'optionalTags',
            'topic.subject.courses'
        ]);
        $question_level =  $question->bloomTagLabel();
        $optional_tags = $question->getOptionalTagsArray();
        $is_in_exam = $question->exams()->exists();
        $data = [
            'question' => $question,
            'question_level' => $question_level,
            'optional_tags' => $optional_tags,
            'is_in_exam' => $is_in_exam
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
            'java' => "Java"
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
        $question_type = request('type');
        $item_count = count(request()->input('items', []));
        $rules = [
            'topic' => ['required', 'exists:topics,id'],
            'type' => ['required'],
            'name' => ['required', 'string', 'unique:questions,name'],
            'subject' => ['required'],
            'question_level' => ['required', 'in:remember,understand,apply,analyze,evaluate,create', 'string']
        ];
        switch ($question_type) {
            case('coding'):
                    $rules['syntax_points'] = ['required', 'integer', 'min:1', 'max:10'];
                    $rules['runtime_points'] = ['required', 'integer', 'min:1', 'max:10'];
                    $rules['test_case_points'] = ['required', 'integer', 'min:1', 'max:10'];
                    $rules['syntax_points_deduction'] = ['required', 'integer', 'min:1', 'max:10'];
                    $rules['runtime_points_deduction'] = ['required', 'integer', 'min:1', 'max:10'];
                    $rules['test_case_points_deduction'] = ['required', 'integer', 'min:1', 'max:10'];
                    $rules['syntax_only_checkbox'] = ['nullable'];
                    $rules['enable_student_compile'] = ['nullable'];
                    $rules['instruction'] = ['required'];
                    $rules['supported_languages'] = ['required', 'json', function ($attribute, $value, $fail) {
                        $decoded = json_decode($value, true);
                        if (empty($decoded)) {
                            $fail('Coding question must have at least one programming language.');
                        }
                    }];
                    $messages = [
                        'supported_languages.required' => 'Coding question must have at least one programming language.',
                        'syntax_points.required' => 'Syntax Points is required.',
                        'runtime_points.required' => 'Run Time Points is required.',
                        'test_case_points.required' => 'Test Case Points is required.',
                        'syntax_points_deduction.required' => 'Syntax Points deduction is required.',
                        'runtime_points_deduction.required' => 'Run Time Points deduction is required.',
                        'test_case_points_deduction.required' => 'Test Case Points deduction is required.',
                    ];
                break;
            case('matching') :
                    $rules['items.*.left'] = ['required', 'string', 'min:1'];
                    $rules['items.*.right'] = ['required', 'string', 'min:1'];
                    $rules['items.*.points'] = ['required', 'integer', 'min:1'];
                    $messages = [
                        'items.*.left.required' => 'Left side is required.',
                        'items.*.right.required' => 'Right side is required.',
                        'items.*.points.required' => 'required.'
                    ];
                    break;

            case('ranking'):
                    $rules['items.*.solution'] = ['required', 'string', 'min:1'];
                    $rules['items.*.points'] = ['required', 'integer', 'min:1'];
                    $messages = [
                        'items.*.solution.required' => 'required.',
                        'items.*.points.required' => 'required.'
                    ];
                break;

            default:
                    $rules['solution'] = ['required', 'string'];
                    $rules['points'] = ['required', 'integer'];
                    $rules['items.*'] = ['required', 'string', 'min:1'];
                    $messages = [
                        'items.*.required' => 'This field is required.',
                        'solution.*.required' => 'This field is required.',
                        'points.*.required' => 'This field is required.',
                    ];
                break;
            }

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            if($question_type == 'coding'){
                return view('components/core/coding-question-error', [
                    'errors' => $validator->errors()
                ]);
            } else {
                return redirect()->route('question.types', ['type' => $question_type, 'item_count' => $item_count])
                ->withErrors($validator)
                ->withInput();
            }
        }
      
        $data = $validator->validated();

        $optional_tags_raw = request()->input('optional_tags');
        $optional_tags = array_filter(array_unique(array_map('trim', explode(',', $optional_tags_raw))));
        $data['optional_tags'] = $optional_tags;

        if ($question_type == 'coding'){
            $is_syntax_only = request()->input('syntax_only_checkbox') ?? false;
            $syntax_points = request()->input('syntax_points', 0);
            $runtime_points = request()->input('runtime_points', 0);
            $test_case_points = request()->input('test_case_points', 0);
            if ($is_syntax_only){
                $totalPoints = $syntax_points;
            } else {
                $totalPoints = $syntax_points + $runtime_points + $test_case_points;
            }
            $data['points'] = $totalPoints;
        } else if ($question_type == 'ranking' || $question_type == 'matching'){
            $items = request()->input('items', []);
            $totalPoints = 0;
            foreach ($items as $item) {
                if (isset($item['points']) && is_numeric($item['points'])) {
                    $totalPoints += (int) $item['points'];
                }
            }
            $data['points'] = $totalPoints;
        }
        QuestionFactory::create($data);

        if (request()->header('HX-Request')) {
            return response('', 200)->header('HX-Redirect', url('/questions'));
        }


        return redirect('/questions');    
    }
    public function edit(Question $question){
        $question->load([
            'questionLevel',
            'optionalTags',
            'topic.subject.courses'
        ]);        
        $is_in_exam = $question->exams()->exists();
        if ($question->question_type->value == 'coding'){
            $data = [
            'question' => $question,
            ];
        } else {
            $course_id = $question->topic->subject->courses->pluck('id');
            $subjects = Subject::whereHas('courses', function ($query) use ($course_id) {
                $query->whereIn('courses.id', $course_id);
            })->pluck('name', 'id');            


            $question_types = [
                'multiple_choice' => 'Multiple Choice',
                'true_or_false'=> 'True or False',
                'identification' => 'Identification',
                'ranking' => 'Ranking/Ordering/Process',
                'matching' => 'Matching Items'
            ];
            $data = [
                'question' => $question, 
                'level' => $question->questionLevel()->first()->name ?? 'none',
                'optional_tags' => $question->getOptionalTagsArray(),
                'subjects' => $subjects,
                'question_types' => $question_types,
                'is_in_exam' => $is_in_exam
            ];
        }
    
        return view('questions/edit', $data);
    }

    public function update(Question $question){
        $this->authorize('update', $question);
        $is_in_exam = $question->exams()->exists();

        $question_type = request('type');
        $item_count = count(request()->input('items', []));

        if ($is_in_exam){
            $rules = [
                'type' => ['required'],
                'name' => ['required', 'string', Rule::unique('questions', 'name')->ignore($question->id)->whereNull('deleted_at'),],
                'question_level' => ['required', 'in:remember,understand,apply,analyze,evaluate,create', 'string']
            ];
        } else {
            $rules = [
                'topic' => ['required', 'exists:topics,id'],
                'type' => ['required'],
                'name' => ['required', 'string', Rule::unique('questions', 'name')->ignore($question->id)->whereNull('deleted_at'),],
                'subject' => ['required'],
                'question_level' => ['required', 'in:remember,understand,apply,analyze,evaluate,create', 'string']
            ];
        }

       switch ($question_type) {
            case('coding'):
                    $rules['syntax_points'] = ['required', 'integer', 'min:1'];
                    $rules['runtime_points'] = ['required', 'integer', 'min:1'];
                    $rules['test_case_points'] = ['required', 'integer', 'min:1'];
                    $rules['syntax_points_deduction'] = ['required', 'integer', 'min:1', 'max:10'];
                    $rules['runtime_points_deduction'] = ['required', 'integer', 'min:1', 'max:10'];
                    $rules['test_case_points_deduction'] = ['required', 'integer', 'min:1', 'max:10'];
                    $rules['syntax_only_checkbox'] = ['nullable'];
                    $rules['enable_student_compile'] = ['nullable'];
                    $rules['instruction'] = ['required'];
                    $rules['supported_languages'] = ['required', 'json', function ($attribute, $value, $fail) {
                        $decoded = json_decode($value, true);
                        if (empty($decoded)) {
                            $fail('Coding question must have at least one programming language.');
                        }
                    }];
                    $instruction = request()->post('instruction');
                    $markdown = Str::of($instruction)->markdown(['html_input' => 'strip']) ?? '';
                    $supported = json_decode(request()->post('supported_languages', '{}'), true);
                    $messages = [
                        'supported_languages.required' => 'Coding question must have at least one programming language.',
                        'syntax_points.required' => 'Syntax Points is required.',
                        'runtime_points.required' => 'Run Time Points is required.',
                        'test_case_points.required' => 'Test Case Points is required.',
                        'syntax_points_deduction.required' => 'Syntax Points deduction is required.',
                        'runtime_points_deduction.required' => 'Run Time Points deduction is required.',
                        'test_case_points_deduction.required' => 'Test Case Points deduction is required.',

                    ];
                break;
            case('matching') :
                    $rules['items.*.left'] = ['required', 'string', 'min:1'];
                    $rules['items.*.right'] = ['required', 'string', 'min:1'];
                    $rules['items.*.points'] = ['required', 'integer', 'min:1'];
                    $messages = [
                        'items.*.left.required' => 'Left side is required.',
                        'items.*.right.required' => 'Right side is required.',
                        'items.*.points.required' => 'required.'
                    ];
                    break;

            case('ranking'):
                    $rules['items.*.solution'] = ['required', 'string', 'min:1'];
                    $rules['items.*.points'] = ['required', 'integer', 'min:1'];
                    $messages = [
                        'items.*.solution.required' => 'required.',
                        'items.*.points.required' => 'required.'
                    ];
                break;

            default:
                    $rules['solution'] = ['required', 'string'];
                    $rules['points'] = ['required', 'integer'];
                    $rules['items.*'] = ['required', 'string', 'min:1'];
                    $messages = [
                        'items.*.required' => 'This field is required.',
                        'solution.*.required' => 'This field is required.',
                        'points.*.required' => 'This field is required.',
                    ];
                break;
        }

        $validator = Validator::make(request()->all(), $rules, $messages);
        if ($validator->fails()) {
            if($question_type == 'coding'){
                                dd($validator->errors());

                return view('components/core/coding-question-error', [
                    'errors' => $validator->errors()
                ]);
            } else {
                return redirect()->route('question.types', ['type' => $question_type, 'item_count' => $item_count])
                ->withErrors($validator)
                ->withInput();
            }
        }

        $data = $validator->validated();

        if($is_in_exam){
            $question->load('topic.subject');
            $data['subject'] = $question->topic->subject->id;
            $data['topic'] = $question->topic->id;
        }

        $optional_tags_raw = request()->input('optional_tags');
        $optional_tags = array_filter(array_unique(array_map('trim', explode(',', $optional_tags_raw))));
        $data['optional_tags'] = $optional_tags;

        if ($question_type == 'coding'){
            $is_syntax_only = request()->input('syntax_only_checkbox') ?? false;
            $syntax_points = request()->input('syntax_points', 0);
            $runtime_points = request()->input('runtime_points', 0);
            $test_case_points = request()->input('test_case_points', 0);
            if ($is_syntax_only){
                $totalPoints = $syntax_points;
            } else {
                $totalPoints = $syntax_points + $runtime_points + $test_case_points;
            }
            $data['points'] = $totalPoints;
        } else if ($question_type == 'ranking' || $question_type == 'matching'){
            $items = request()->input('items', []);
            $totalPoints = 0;

            foreach ($items as $item) {
                if (isset($item['points']) && is_numeric($item['points'])) {
                    $totalPoints += (int) $item['points'];
                }
            }
            $data['points'] = $totalPoints;
        }
        \Log::info('data can be updated', $data);
        QuestionFactory::update($question, $data);
        \Log::info('Question Update Successful');
        
        if (request()->header('HX-Request')) {
            return response('', 200)->header('HX-Redirect', route('questions.show', $question));
        }
        
        return redirect()->route('questions.show', $question);
    }
    public function destroy(Question $question){

        $this->authorize('delete', $question);

        if ($question->exams()->exists()) {
            return back()->with('error', 'You cannot delete a question that is in exam.');
        }

        session()->flash('toast', json_encode([
            'status' => 'Destroyed!',
            'message' => 'Question: ' . $question->name,
            'type' => 'warning'
        ]));

        $question->delete();

        return redirect('/questions');

    }

    public function getSubjectsForCourses(Request $request){
    $courseId = $request->input('course');

    if (empty($courseId)) {
        return view('/components/core/partials-subject', [
            'subjects' => ["" => "No Course Selected"]
        ]);
    }

    $course = $this->userService->getCourseById($courseId);

    if (!$course || $course->subjects->isEmpty()) {
        return view('/components/core/partials-subject', [
            'subjects' => ["" => "No Subjects Available"]
        ]);
    }

    $subjects = $course->subjects->pluck('name', 'id');

    return view('/components/core/partials-subject', [
        'subjects' => $subjects
    ]);   
    }
    public function getTopicsForSubjects(Request $request)
    {
        $subject_id = $request->input('subject');
        $selected_topic_id = $request->input('topic_id');

        if (empty($subject_id)) {
            return view('/components/core/partial-topic', [
                'topics' => ["" => "No Topics Available"]
            ]);
        }

        $subject = $this->userService->getSubjectById($subject_id);

        if (!$subject || $subject->topics->isEmpty()) {
            return view('/components/core/partial-topic', [
                'topics' => ["" => "No Topics Available"]
            ]);
        }

        $topics = $subject->topics->pluck('name', 'id');

        return view('/components/core/partial-topic', [
            'topics' => $topics,
            'topic_id' => $selected_topic_id
        ]);
    }


    public function question_type_show(Question $question){
        $question_type_data = $this->questionService->getQuestionTypeShow($question);

        $data = [
            'question' => $question,
            'question_type_data' => $question_type_data
        ];
    

        return view('questions-types/show', $data);
    }

    public function loadQuestionType(Request $request)
    {
        $type = $request->input('type');
        $itemCount = (int) $request->input('item_count', 4);
        $isEdit = filter_var($request->input('edit'), FILTER_VALIDATE_BOOLEAN);
        $question = null;
        $validTypes = ['multiple_choice', 'true_or_false', 'identification', 'ranking', 'matching', 'coding'];
        
        if (!in_array($type, $validTypes)) {
            abort(400, 'Invalid question type');
        }

        if ($isEdit) {
            $questionId = $request->input('question_id');
            if (!$questionId) {
                abort(400, 'Missing question_id');
            }
            $question = Question::findOrFail($questionId);
            $question_type_data = $this->questionService->getQuestionTypeShow($question);
            if ($type == 'coding'){
                $course_id = $question->topic->subject->courses->pluck('id');
                $subjects = Subject::whereHas('courses', function ($query) use ($course_id) {
                    $query->whereIn('courses.id', $course_id);
                })->pluck('name', 'id');            
                $programming_languages = [
                        'java' => "Java",
                        'c++' => "C++",
                        'python' => "Python",
                    ];
                $level = $question->questionLevel()->first()->name ?? 'none';
                $optional_tags = $question->getOptionalTagsArray();
            }
            
        }

        return match ($type) {
            'multiple_choice' => view('questions-types/multiple-choice', $isEdit 
                ? compact('question_type_data', 'isEdit') 
                : compact('isEdit')),
            
            'true_or_false'   => view('questions-types/true-false', $isEdit 
                ? compact('question_type_data', 'isEdit') 
                : compact('isEdit')),

            'identification'  => view('questions-types/identification', $isEdit 
                ? compact('question_type_data', 'isEdit') 
                : compact('isEdit')),

            'ranking' => view('questions-types/rank-order-process', $isEdit 
                ? compact('question_type_data', 'isEdit') 
                : compact('itemCount', 'question', 'isEdit')),

            'matching' => view('questions-types/matching-items', $isEdit 
                ? compact('question_type_data', 'isEdit') 
                : compact('itemCount','isEdit')),

            'coding' => view('questions-types/coding', $isEdit 
                ? compact('question_type_data', 'isEdit', 'subjects', 'question', 'programming_languages', 'level', 'optional_tags') 
                : compact('isEdit')),
        };

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
        $code_settings['action'] = $request->post('action');
        $code_settings['syntax_coding_question_only'] = $request->post('syntax_only_checkbox') ? true : false;
        $language = $request->post('language-to-validate');

        if ($code_settings['action'] == 'test_student_code') {
            $code_settings['action'] = 'compile';
            $code = $request->post('student-code-test');

            $question_id = $request->post('test_coding_question_id');
            $question = Question::find($question_id);
            $question_type = $question->getTypeModel();

            $test_case = $question_type->getSpecificLanguage($language)->getTestCase();
            
            $code_settings['syntax_points'] = 0;
            $code_settings['runtime_points'] = 0;
            $code_settings['test_case_points'] = 0;
            $code_settings['syntax_points_deduction'] =  1;
            $code_settings['runtime_points_deduction'] = 1;
            $code_settings['test_case_points_deduction'] = 1;

        } else {
            if (!$code_settings['syntax_coding_question_only']){
                $test_case = $request->post('validate-test-case');
            } else {
                $test_case = '';
            };

            $code = $request->post('validate-complete-solution');
            $code_settings['syntax_points'] = $request->post('validate_syntax_points') ?? 0;
            $code_settings['runtime_points'] = $request->post('validate_runtime_points') ?? 0;
            $code_settings['test_case_points'] = $request->post('validate_test_case_points') ?? 0;
            $code_settings['syntax_points_deduction'] = $request->post('validate_syntax_points_deduction') ?? 1;
            $code_settings['runtime_points_deduction'] = $request->post('validate_runtime_points_deduction') ?? 1;
            $code_settings['test_case_points_deduction'] = $request->post('validate_test_case_points_deduction') ?? 1;
        }
        if (empty($code) && $code_settings['syntax_coding_question_only'] == true) {
            $api_data = ['error' => 'Complete solution is required.'];
        } else if (empty($code) || empty($test_case) && $code_settings['syntax_coding_question_only'] == false){
            $api_data = ['error' => 'Complete solution and Test Cases are both required.'];
        } else {
            $api_data = $this->questionService::validate($language, $code, $test_case, $code_settings);
        }


        

        $data = [
            'post_data' => $request->post(),
            'api_data' => $api_data
        ];
        
        return view('questions-types/validate-complete-solution', ['data'=> $data]);
    }

    public function testCodingQuestion(Question $question){
        $question_type_data = $this->questionService->getQuestionTypeShow($question);
        $languages = [
                        'java' => "Java",
                        'c++' => "C++",
                        'python' => "Python",
                    ];
        $isEdit = true;

        return view('questions-types/coding-test', compact('question_type_data', 'languages', 'isEdit', 'question'));
    }



}
