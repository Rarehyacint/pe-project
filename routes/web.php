<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TopicController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

Route::get('/', function () {

    $user = Auth::user();

    if ($user && $user->can('view faculty')) {
        return redirect('/faculty');
    } else if ($user && $user->can('view student')) {
        return redirect('/student');
    }

    return view('landing-page');
});

Route::get('/test', function () {
    return view('test-page');
});

Route::post('/login', [SessionController::class, 'authenticate']);
Route::post('/logout', [SessionController::class, 'logout'])->middleware(['auth']);;
Route::post('/questions/create/validate-complete-solution', [QuestionController::class, 'validateCompleteSolution']);

Route::group(['middleware' => ['can:view student']], function () { 
});

Route::prefix('student')->middleware(['can:view student'])->group(function() {
    Route::get('/', function () {
        return view('students/student-home');
    });
    Route::get('/exams', function () {
        return view('students/exams/index');
    });
    Route::get('/exams/exam.id', function () {
        return view('students/exams/show');
    });
    Route::get('/exams/exam.id/mcq-example', function () {
        return view('students/exams/mcq-example');
    });
    Route::get('/exams/exam.id/torf-example', function () {
        return view('students/exams/TorF-example');
    });
    Route::get('/exams/exam.id/iden-example', function () {
        return view('students/exams/iden-example');
    });
    Route::get('/exams/exam.id/rank-example', function () {
        $items = [
            0 => 'Code writing',
            1 => 'Syntax checking',
            2 => 'Compiling',
            3 => 'Execution'
        ];

        return view('students/exams/rank-example',['items' => $items]);
    });
    Route::get('/exams/exam.id/match-example', function () {
        return view('students/exams/match-example');
    });
    Route::get('/exams/exam.id/coding-example', function () {
        $programming_languages = [
            'c++' => "C++",
            'java' => "Java",
            'sql' => "SQL",
            'python' => "Python",
        ];
        return view('students/exams/coding-example', ['programming_languages' => $programming_languages]);
    });
    Route::get('/exams/exam.id/result', function () {
        return view('students/exams/result-example');
    });
});


Route::prefix('')->middleware(['can:view faculty'])->group(function () { 
    Route::get('/faculty', [LandingPageController::class, 'facultyShow']);

    Route::group(['middleware' => ['can:view access control']], function () { 
        Route::get('/admins', [AccessControlController::class, 'redirect']);
        Route::get('/admins/access-control', [AccessControlController::class, 'index']);
    
        Route::get('/admins/load-users', [AccessControlController::class, 'viewUsers']);
        Route::get('/admins/users/create', [RegisteredUserController::class, 'create']);    
        Route::post('/admins/users', [RegisteredUserController::class, 'store']);
        Route::get('/admins/users/{user}/edit', [RegisteredUserController::class, 'edit'])->name('admin.users.edit');
        Route::get('/admins/users/{user}', [RegisteredUserController::class, 'show'])->name('admin.users.show');
        Route::patch('/admins/users/{user}', [RegisteredUserController::class, 'update']);
        Route::delete('/admins/users/{user}', [RegisteredUserController::class, 'destroy']);
    
        Route::get('/admins/roles', function () {
            return view('admins/roles');
        });
        Route::get('/admins/load-roles', [AccessControlController::class, 'viewRoles']);
        Route::post('/admins/roles/load-role-checkbox', [AccessControlController::class, 'loadRoleCheckbox']);
        Route::get('/admins/roles/create', [AccessControlController::class, 'createRole']);
        Route::post('/admins/roles', [AccessControlController::class, 'storeRole']);
        Route::get('/admins/roles/{role}', [AccessControlController::class, 'showRole'])->name('admin.roles.show');
        Route::get('/admins/roles/{role}/edit', [AccessControlController::class, 'editRole']);
        Route::patch('/admins/roles/{role}', [AccessControlController::class, 'updateRole']);
        Route::delete('/admins/roles/{role}', [AccessControlController::class, 'destroyRole']);
    
        Route::get('/admins/permissions', function () {
            return view('admins/permissions');
        });
        Route::get('/admins/load-permissions', [AccessControlController::class, 'viewPermissions']);
        Route::get('/admins/permissions/create', [AccessControlController::class, 'createPermission']);
        Route::post('/admins/permissions', [AccessControlController::class, 'storePermission']);
        Route::get('/admins/permissions/{permission}', [AccessControlController::class, 'showPermission'])->name('admin.permissions.show');
        Route::get('/admins/permissions/{permission}/edit', [AccessControlController::class, 'editPermission']);
        Route::patch('/admins/permissions/{permission}', [AccessControlController::class, 'updatePermission']);
        Route::delete('/admins/permissions/{permission}', [AccessControlController::class, 'destroyPermission']);
    
    });

    Route::get('/exams', [ExamController::class, 'index']);
    Route::get('/exams/create', [ExamController::class, 'create'])->name('exams.create');
    Route::post('/exams', [ExamController::class, 'store']);
    Route::get('/exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
    Route::get('/exams/{exam}/edit', [ExamController::class, 'edit']);
    Route::patch('/exams/{exam}', [ExamController::class, 'update']);
    Route::delete('/exams/{exam}', [ExamController::class, 'destroy']);
    Route::get('/exams/{exam}/builder', [ExamController::class, 'exam_builder_show']);
    Route::post('/exams/{exam}/builder/add-question/{question}',[ExamController::class, 'toggle_question'])->name('exam.toggleQuestion');
    Route::get('/exams/{exam}/builder/swap-algorithm',[ExamController::class, 'swap_partial_algorithm']);
    Route::get('/exams/{exam}/builder/build', [ExamController::class, 'build_exam']);
    Route::get('/exams/{exam}/edit/generate_access_code', [ExamController::class, 'generateAccessCode']);
    Route::get('/exams/builder/tabs', [ExamController::class, 'swap_tabs']);



    Route::get('/questions', [QuestionController::class, 'index']);
    Route::get('/questions/create', [QuestionController::class, 'create'])->name('questions.create');
    Route::get('/questions/create/courses', [QuestionController::class, 'getSubjectsForCourses']);
    Route::get('/questions/create/subjects', [QuestionController::class, 'getTopicsForSubjects']);
    Route::get('/questions/create/coding-question', [QuestionController::class, 'createCodingQuestion']);
    Route::get('/questions/create/preview-markdown', [QuestionController::class, 'togglePreviewButton']);
    Route::post('/questions/create/preview-markdown', [QuestionController::class, 'previewMarkdown']);
    Route::post('/questions', [QuestionController::class, 'store']);
    Route::get('/question_type_show/{question}', [QuestionController::class, 'question_type_show'])->name('question_type.show');
    Route::get('/questions/{question}', [QuestionController::class, 'show'])->name(name: 'questions.show');
    Route::get('/questions/{question}/edit', [QuestionController::class, 'edit']);
    Route::patch('/questions/{question}', [QuestionController::class, 'update']);
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy']);

    Route::get('/questions/create/question-type', function (Request $request) {
        $item_count = (int) $request->input('item_count', 4);
        $type = $request->query('type'); 
        switch ($type) {
            case 'multiple_choice':
                return view('questions-types/multiple-choice');
            
            case 'true_or_false':
                return view('questions-types/true-false');
            
            case 'identification':
                return view('questions-types/identification');

            case 'ranking':
                return view('questions-types/rank-order-process', compact('item_count'));
            
            case 'matching':
                return view('questions-types/matching-items');

            case 'coding':
                return view('questions-types/coding');

            default:
                return '';
            }
        })->name('question.types');

    Route::get('/questions/create/add-item', function () {
        $counter = request('item_count', 4);
        $item_count = session('counter', $counter);
        $item_count++;

        session()->flash('counter', $item_count);

        return view('questions-types/new-text-item', ['counter' => $item_count]);
     });

    Route::get('/topics', [TopicController::class, 'index']);
    Route::get('/topics/create', [TopicController::class, 'create']);
    Route::post('/topics', [TopicController::class, 'store']);
    Route::get('/topics/{topic}', [TopicController::class, 'show'])->name(name: 'topics.show');
    Route::get('/topics/{topic}/edit', [TopicController::class, 'edit']);
    Route::patch('/topics/{topic}', [TopicController::class, 'update']);
    Route::delete('/topics/{topic}', [TopicController::class, 'destroy']);
    Route::get('/topics/{topic}/questions', [TopicController::class, 'showQuestions']);

    Route::get('/subjects', [SubjectController::class, 'index']);
    Route::get('/subjects/create', [SubjectController::class, 'create']);
    Route::post('/subjects', [SubjectController::class, 'store']);
    Route::get('/subjects/{subject}', [SubjectController::class, 'show'])->name('subjects.show');
    Route::get('/subjects/{subject}/edit', [SubjectController::class, 'edit']);
    Route::patch('/subjects/{subject}', [SubjectController::class, 'update']);
    Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy']);
    Route::get('/subjects/{subject}/questions', [SubjectController::class, 'showQuestions']);

    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/create', [CourseController::class, 'create']);
    Route::post('/courses', [CourseController::class, 'store']);
    Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('/courses/{course}/edit', [CourseController::class, 'edit']);
    Route::patch('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
    Route::get('/courses/{course}/subjects', [CourseController::class, 'showSubjects']);


    Route::get('/reviewers', function(){
        return view('reviewers/index');
    });
    Route::get('/reviewers/create', function(){
        return view('reviewers/create');
    });
    Route::post('/reviewers', function(Request $request){
        dd($request->post());
    });
    Route::get('/reviewers/show', function(){
        return view('reviewers/show');
    });
    Route::get('/reviewers/edit', function(){
        return view('reviewers/edit');
    });
    Route::get('/reviewers/questions', function(){
        return view('reviewers/questions');
    });

    Route::get('/reports', function(){
        return view('reports');
    });

    Route::get('/notifications', function(){
        return view('notifications');
    });

    Route::get('/settings', function(){
        return view('settings');
    });

    Route::get('/profiles/show', function(){
        return view('profiles/show');
    });
    Route::get('/profiles/subjects', function(){
        return view('profiles/subjects');
    });
    Route::get('/profiles/courses', function(){
        return view('profiles/courses');
    });

 });

Route::post('/test/send-data', function(Request $request) {
    $data = $request->post();
    $markdown = Str::of($request->post('instruction'))->markdown([
        'html_input' => 'strip',
    ]);
    return view('test-sent-data-page', ['data'=> $data, 'markdown' => $markdown]);
});