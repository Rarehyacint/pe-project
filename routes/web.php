<?php

use App\Http\Controllers\AccessControlController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\RegisteredUserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\Student\ExamRecordController;
use App\Http\Controllers\Student\StudentAnswerController;
use App\Http\Controllers\Student\StudentPaperController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\Student\ExamController as StudentExamController;
use App\Http\Controllers\Student\StudentController;
use App\Http\Controllers\GoogleAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = Auth::user();

    if ($user && $user->can('view faculty')) {
        return redirect('/faculty');
    } else if ($user && $user->can('view student')) {
        return redirect('/student');
    }

    return view('landing-page');
});

Route::get('auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

Route::post('/login', [SessionController::class, 'authenticate']);
Route::post('/logout', [SessionController::class, 'logout'])->middleware(['auth']);
Route::post('/questions/create/validate-complete-solution', [QuestionController::class, 'validateCompleteSolution'])->name('validate.coding.question');

Route::prefix('student')->middleware(['can:view student'])->group(function() {
    Route::get('/', [StudentController::class, 'index'])->name('students.index');
    
    Route::get('/exams', [StudentExamController::class, 'index']);
    Route::post('/exams', [StudentExamController::class, 'store'])->name('exams.student.store');
    Route::get('/exams/{exam}', [StudentExamController::class, 'show'])->name('exams.student.show');
    
    Route::middleware('htmx.request:students.index')->group(function () {
        Route::get('/exams/{exam}/show-overview', [StudentExamController::class, 'showExamOverview'])->name('exams.student.overview');
        Route::get('/exams/{exam}/records', [ExamRecordController::class, 'index'])->name('exam_records.index');
        Route::get('/exams/{exam}/question-links/{student_paper}', [StudentPaperController::class, 'loadQuestionLinks'])->name('exam_papers.questions');
        Route::get('/expired_student_paper_redirect/{student_paper}', [StudentPaperController::class, 'pollToAutoCompletedExamRecord'])->name('exam_papers.auto_completed_redirect');
        Route::get('/get-coding-results/{coding_answer}', [ExamRecordController::class, 'showCodingResult'])->name('exam_records.coding_answer_result');
        Route::get('/get-updated-score/{exam_record}', [ExamRecordController::class, 'showUpdatedScore'])->name('exam_records.show_updated_score');
    });

    Route::get('/exams/{exam}/records/{exam_record}', [ExamRecordController::class, 'show'])->name('exam_records.show');
    Route::patch('/exams/{student_paper}/evaluate', [ExamRecordController::class, 'store'])->name('exam_records.store');

    Route::get('/exams/{exam}/take', [StudentPaperController::class, 'takeExam'])->name('exam_papers.take');
    Route::get('/student_papers/{student_paper}/question', [StudentPaperController::class, 'show'])->name('exam_papers.show');
    Route::patch('/student_papers/{student_paper}/{question}', [StudentAnswerController::class, 'update'])->name('student_answer.update');

// Add these routes inside the student middleware group (after line where you have Route::patch('/student_papers/{student_paper}/{question}', ...))

    Route::post('/download/exam-record/{exam_record}', [ExamRecordController::class, 'downloadExamRecord'])->name('student.download.exam.record');
    Route::post('/email/reviewer', [StudentController::class, 'emailReviewer'])->name('student.email.reviewer');
    Route::post('/email/exam-record', [StudentController::class, 'emailExamRecord'])->name('student.email.exam.record');

    
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
    Route::get('/faculty', [LandingPageController::class, 'facultyShow'])->name('faculty.index');

    Route::group(['middleware' => ['can:view access control']], function () { 
        Route::get('/admins', [AccessControlController::class, 'redirect'])->name('admin.redirect');
        
        Route::get('/admins/users', [AccessControlController::class, 'indexUsers'])->name('admin.users.index');
        Route::post('/admins/users', [RegisteredUserController::class, 'store']);
        Route::patch('/admins/users/{user}', [RegisteredUserController::class, 'update']);
        Route::delete('/admins/users/{user}', [RegisteredUserController::class, 'destroy']);
        
        Route::get('/admins/roles', [AccessControlController::class, 'indexRoles'])->name('admin.roles.index');
        Route::post('/admins/roles', [AccessControlController::class, 'storeRole']);
        Route::patch('/admins/roles/{role}', [AccessControlController::class, 'updateRole']);
        Route::delete('/admins/roles/{role}', [AccessControlController::class, 'destroyRole']);
        
        Route::get('/admins/academic-year', [AccessControlController::class, 'indexAcademicYear'])->name('admin.academic-year.index');
        Route::get('/admins/academic-year/create', [AccessControlController::class, 'createAcademicYear'])->name('admin.academic-year.create');
        Route::post('/admins/academic-year', [AccessControlController::class, 'storeAcademicYear'])->name('admin.academic-year.store');
        
        Route::patch('/admins/academic-year/{academic_year}', [AccessControlController::class, 'updateAcademicYear'])->name('admin.academic-year.update');
        Route::delete('/admins/academic-year/{academic_year}', [AccessControlController::class, 'destroyAcademicYear'])->name('admin.academic-year.destroy');

        Route::middleware('htmx.request:admin.redirect')->group(function () {
            Route::get('/admins/users/create', [RegisteredUserController::class, 'create']);    
            Route::get('/admins/users/{user}/edit', [RegisteredUserController::class, 'edit'])->name('admin.users.edit');
            Route::get('/admins/users/{user}', [RegisteredUserController::class, 'show'])->name('admin.users.show');

            Route::post('/admins/roles/load-role-checkbox', [AccessControlController::class, 'loadRoleCheckbox']);
            Route::get('/admins/roles/create', [AccessControlController::class, 'createRole']);
            Route::get('/admins/roles/{role}', [AccessControlController::class, 'showRole'])->name('admin.roles.show');
            Route::get('/admins/roles/{role}/edit', [AccessControlController::class, 'editRole']);
            
            Route::get('/admins/academic-year/{academic_year}/edit', [AccessControlController::class, 'editAcademicYear'])->name('admin.academic-year.edit');
            Route::get('/admins/academic-year/{academic_year}/destroy', [AccessControlController::class, 'destroyFormAcademicYear'])->name('admin.academic-year.destroy-form');
        });

    });

    Route::get('/exams', [ExamController::class, 'index'])->name('exams.index');
    Route::get('/exams/create', [ExamController::class, 'create'])->name('exams.create');
    Route::post('/exams', [ExamController::class, 'store'])->name('exams.store');
    Route::patch('/exams/{exam}/publishExam', [ExamController::class, 'publishExam'])
        ->whereNumber('exam')
        ->name('exams.publish');

    
    Route::get('/exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
    Route::get('/exams/{exam}/edit', [ExamController::class, 'edit'])->name('exams.edit');
    Route::patch('/exams/{exam}', [ExamController::class, 'update'])->name('exams.update');
    Route::delete('/exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');

    Route::get('/exams/{exam}/builder', [ExamController::class, 'exam_builder_show']);
    Route::post('/exams/{exam}/builder/toggle-question',[ExamController::class, 'toggle_question'])->name('exam.toggleQuestion');
    Route::get('/exams/{exam}/builder/swap-algorithm',[ExamController::class, 'swap_partial_algorithm']);
    Route::get('/exams/{exam}/builder/build', [ExamController::class, 'build_exam']);

    Route::get('/exams/builder/tabs', [ExamController::class, 'swap_tabs']);

    Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::get('/questions/create', [QuestionController::class, 'create'])->name('questions.create');
    Route::get('/questions/create/courses', [QuestionController::class, 'getSubjectsForCourses']);
    Route::get('/questions/create/subjects', [QuestionController::class, 'getTopicsForSubjects']);
    Route::get('/questions/create/coding-question', [QuestionController::class, 'createCodingQuestion']);
    Route::get('/questions/create/preview-markdown', [QuestionController::class, 'togglePreviewButton']);
    Route::post('/questions/create/preview-markdown', [QuestionController::class, 'previewMarkdown']);
    Route::post('/questions', [QuestionController::class, 'store'])->name('questions.store');
    Route::get('/question_type_show/{question}', [QuestionController::class, 'question_type_show'])->name('question_type.show');
    Route::get('/questions/{question}', [QuestionController::class, 'show'])->name('questions.show');
    Route::get('/questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
    Route::patch('/questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
    Route::match(['get', 'post', 'patch'], '/questions/load/question-type', [QuestionController::class, 'loadQuestionType'])->name('question.types');
    Route::get('/questions/create/add-item', function () {
        $counter = request('item_count', 4);
        $is_matching = request('is_matching', false);
        $item_count = session('counter', $counter);
        $item_count++;

        session()->flash('counter', $item_count);

        return view('questions-types/new-text-item', ['counter' => $item_count, 'is_matching' => $is_matching]);
    });
    Route::get('/questions/{question}/coding-question-test', [QuestionController::class, 'testCodingQuestion'])->name('questions.coding.test');

    Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
    Route::get('/topics/create', [TopicController::class, 'create'])->name('topics.create');
    Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
    Route::get('/topics/{topic}', [TopicController::class, 'show'])->name('topics.show');
    Route::get('/topics/{topic}/edit', [TopicController::class, 'edit'])->name('topics.edit');
    Route::patch('/topics/{topic}', [TopicController::class, 'update'])->name('topics.update');
    Route::delete('/topics/{topic}', [TopicController::class, 'destroy'])->name('topics.destroy');

    Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');
    Route::get('/subjects/create', [SubjectController::class, 'create'])->name('subjects.create');
    Route::post('/subjects', [SubjectController::class, 'store'])->name('subjects.store');
    Route::get('/subjects/{subject}', [SubjectController::class, 'show'])->name('subjects.show');
    Route::get('/subjects/{subject}/edit', [SubjectController::class, 'edit'])->name('subjects.edit');
    Route::patch('/subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
    Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');

    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
    Route::patch('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

    // Reviewers routes
    Route::get('/reviewers', [MailController::class, 'reviewersIndex'])->name('reviewers.index');
    Route::get('/reviewers/create', [MailController::class, 'create'])->name('reviewers.create');
    Route::post('/reviewers', [MailController::class, 'index'])->name('reviewers.store');
    Route::delete('/reviewers/{id}', [MailController::class, 'destroy'])->name('reviewers.destroy');
    // Route::get('/reviewers/{reviewer}/download', [MailController::class, 'downloadFacultyReviewer'])->name('reviewers.download');

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/info', [ReportController::class, 'info'])->name('reports.info');
    Route::get('/reports/{exam}', [ReportController::class, 'index_exam'])->name('reports.index_exam');
    Route::post('/reports/{exam}', [ReportController::class, 'store'])->name('reports.store');
    Route::get('/reports/{exam}/create', [ReportController::class, 'create'])->name('reports.create');
    Route::get('/reports/{exam}/{report}', [ReportController::class, 'show'])->name('reports.show');
    Route::delete('/reports/{exam}/{report}', [ReportController::class, 'destroy'])->name('reports.destroy');

    Route::middleware('htmx.request:faculty.index')->group(function () {
        Route::get('/homepage/report/exam', [LandingPageController::class, 'examReportShow'])->name('graphs.homepage.exam');
        Route::get('/homepage/report/course', [LandingPageController::class, 'courseReportShow'])->name('graphs.homepage.course');
        Route::get('/homepage/report/specific-course', [LandingPageController::class, 'specificCourseReportShow'])->name('graphs.homepage.specific.course');
        Route::get('/homepage/report/system', [LandingPageController::class, 'systemReportShow'])->name('graphs.homepage.system');
        Route::get('/homepage/report/refresh', [LandingPageController::class, 'refreshDashboard'])->name('graphs.homepage.refresh');
        Route::get('/homepage/report/timer', [LandingPageController::class, 'getTimer'])->name('graphs.homepage.timer');

        Route::get('/exams/{exam}/edit/generate_access_code', [ExamController::class, 'generateAccessCode'])->name('accesscodes.generate');
        Route::post('/exams/{exam}/edit/save_access_code', [ExamController::class, 'saveAccessCode'])->name('accesscodes.save');
        Route::get('/exams/{exam}/edit/get_access_codes', [ExamController::class, 'getAccessCode'])->name('accesscodes.get');
        Route::delete('/exams/{exam}/edit/destroy_access_code', [ExamController::class, 'destroyAccessCode'])->name('accesscodes.destroy');
    });

    Route::get('/notifications', [NotificationController::class, 'index']);

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