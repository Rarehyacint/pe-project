<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\UserService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TopicController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }


    public function index(){
        $topics = $this->userService->gettopicsForUser(auth()->user())->paginate(10);
        $topics->load('subject', 'questions');
        $header = ['Subject', 'Name', 'Question Count'];
        $rows = $topics->map(function ($topic) {
            return [
                'id' => $topic->id,
                'subject' => $topic->subject->code,
                'name' => $topic->name,
                'question count' => $topic->questions->count()
            ];
        });

        $data = [
            'headers' => $header,
            'rows' => $rows,
            'models' => $topics,
            'url' => 'topics'
        ];

        if (request()->hasHeader('HX-Request') && !request()->hasHeader('HX-History-Restore-Request')) {
            // Return only the partial view for HTMX
            return view('components/core/index-table', $data);
        }

        return view('topics/index', $data);
    }

    public function show(Topic $topic){
        $questions_are_in_exams = $topic->questions->contains(function ($question) {
            return $question->exams()->exists();
        });    
        $header = ['Name', 'Type', 'Date Created'];
        $questions = Question::with(['topic'])
            ->where('topic_id', $topic->id)
            ->Paginate(5);

        $rows = $questions->map(fn($question) => [
                'id' => $question->id,
                'name' => $question->name,
                'type' => $question->question_type->name,
                'Date Created' => Carbon::parse($question->created_at)->format('m/d/Y')
        ]);
        $data = [
            'headers' => $header,
            'rows' => $rows,
            'topic'=>$topic,
            'models' => $questions,
            'url' => 'questions',
            'questions_are_in_exams' => $questions_are_in_exams
        ];

        if (request()->hasHeader('HX-Request') && !request()->hasHeader('HX-History-Restore-Request')) {
            // Return only the partial view for HTMX
            return view('components/core/index-table', $data);
        }

        return view('topics/show', $data);
    }
    public function create(){
        $subjects = $this->userService->getSubjectsForUser(auth()->user())->pluck('name', 'id');

        return view('topics/create', ['subjects' => $subjects]);
    }

    public function store(){
        $subject_id = request()->input('subject');
        $validator = Validator::make(request()->post(), [
            'name'    => ['required', 'unique:topics,name,NULL,id,subject_id,' . $subject_id],
            'subject' => ['required', 'integer', 'exists:subjects,id'],
        ]);

        if ($validator->fails()) {
            $subjects = $this->userService->getSubjectsForUser(auth()->user())->pluck('name', 'id');
            return response()->view('topics.create', [
                'errors' => $validator->errors(),
                'subjects' => $subjects,
                'old' => request()->all()]);
        }

        $topic = Topic::create([
            'name' => request('name'),
            'subject_id' => request('subject'),
        ]);

        session()->flash('toast', json_encode([
            'status' => 'Created!',
            'message' => 'Topic: ' . $topic->name,
            'type' => 'success'
        ]));

        return response('', 200)->header('HX-Redirect', route('topics.index'));
    }
    public function edit(Topic $topic)
    {
        $courseIds = $topic->subject->courses->pluck('id');
        $questions_are_in_exams = $topic->questions->contains(function ($question) {
            return $question->exams()->exists();
        });        

        $subjects = Subject::whereHas('courses', function ($query) use ($courseIds) {
            $query->whereIn('courses.id', $courseIds);
        })->pluck('name', 'id');

        return view('topics.edit', [
            'topic' => $topic,
            'subjects' => $subjects,
            'questions_are_in_exams' => $questions_are_in_exams
        ]);
    }


    public function update(Request $request, Topic $topic){
        $this->authorize('update', $topic);


        $request->validate([
                'name' => [
                    'required',
                    Rule::unique('topics')->where(function ($query) use ($request) {
                        return $query->where('subject_id', $request->subject);
                    })->ignore($topic->id),
                ],
                'subject' => ['required', 'integer', 'exists:subjects,id'],
            ]);


        $topic->update($request->all());
    
        session()->flash('toast', json_encode([
            'status' => 'Updated!',
            'message' => 'Topic: ' . $topic->name,
            'type' => 'info'
        ]));

        return redirect()->route('topics.show', $topic);
    }
    public function destroy(Topic $topic){

        $this->authorize('delete', $topic);

        if ($topic->questions()->exists()) {
            return back()->with('error', 'You cannot delete a topic that has questions.');
        }
        session()->flash('toast', json_encode([
            'status' => 'Destroyed!',
            'message' => 'Topic: ' . $topic->name,
            'type' => 'warning'
        ]));

        $topic->delete();

        return redirect('/topics');

    }
}
