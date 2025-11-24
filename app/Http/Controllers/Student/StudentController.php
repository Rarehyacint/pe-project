<?php

namespace App\Http\Controllers\Student;

use App\Models\ExamRecord;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StudentController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(){
        $user = auth()->user();
        $enrolled_exams = $user->exams ?? [];
        $exam_records = $user->examRecords()
            ->with(['studentPaper:id,exam_id,id', 'studentPaper.exam:id,name,max_score,id']) // adjust fields
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();
        $enrolled_exams->load('courses');

        $data = [
            'enrolled_exams' => $enrolled_exams,
            'exam_records' => $exam_records,
            'user' => $user
        ];

        return view('students/student-home', $data);
    }

    public function emailReviewer(Request $request)
    {
        $request->validate([
            'reviewer_id' => 'required',
            'reviewer_path' => 'required',
            'student_email' => 'required'
        ]);

        try {
            $user = auth()->user();
            $email = $request->input('student_email') === 'auto' ? $user->email : $request->input('student_email');
            
            // TODO: Implement your mailing logic here
            // Example: Mail::to($email)->queue(new SendReviewerEmail($request->input('reviewer_path')));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function emailExamRecord(Request $request)
    {
        $request->validate([
            'exam_record_id' => 'required|exists:exam_records,id',
            'student_email' => 'required'
        ]);

        try {
            $user = auth()->user();
            $email = $request->input('student_email') === 'auto' ? $user->email : $request->input('student_email');
            $examRecord = ExamRecord::findOrFail($request->input('exam_record_id'));

            // Verify the exam record belongs to the authenticated user
            if ($examRecord->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
            
            // TODO: Implement your mailing logic here
            // Example: Mail::to($email)->queue(new SendExamRecordEmail($examRecord));
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}