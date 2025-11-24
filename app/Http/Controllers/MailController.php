<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;
use Dompdf\Dompdf;
use Dompdf\Options;
use App\Models\Reviewer;
use App\Jobs\SendReviewerEmailJob;
use App\Jobs\SendExamRecordEmailJob;

class MailController extends Controller
{
    // Helper method to get user's full name safely
    private function getUserFullName($user)
    {
        if (isset($user->first_name) && isset($user->last_name)) {
            return $user->first_name . ' ' . $user->last_name;
        } elseif (isset($user->profile->first_name) && isset($user->profile->last_name)) {
            return $user->profile->first_name . ' ' . $user->profile->last_name;
        } elseif (isset($user->student->first_name) && isset($user->student->last_name)) {
            return $user->student->first_name . ' ' . $user->student->last_name;
        } elseif (isset($user->name)) {
            return $user->name;
        } else {
            return $user->email ?? 'User';
        }
    }

    // Helper method to get user's first name safely
    private function getUserFirstName($user)
    {
        if (isset($user->first_name)) {
            return $user->first_name;
        } elseif (isset($user->profile->first_name)) {
            return $user->profile->first_name;
        } elseif (isset($user->student->first_name)) {
            return $user->student->first_name;
        } elseif (isset($user->name)) {
            return explode(' ', $user->name)[0];
        } else {
            return 'Student';
        }
    }

    // Helper method to get user's last name safely
    private function getUserLastName($user)
    {
        if (isset($user->last_name)) {
            return $user->last_name;
        } elseif (isset($user->profile->last_name)) {
            return $user->profile->last_name;
        } elseif (isset($user->student->last_name)) {
            return $user->student->last_name;
        } elseif (isset($user->name)) {
            $parts = explode(' ', $user->name);
            return count($parts) > 1 ? end($parts) : '';
        } else {
            return '';
        }
    }

    // Display reviewers index page
    public function reviewersIndex()
    {
        $reviewers = Reviewer::join('topics as t', 't.id', '=', 'reviewers.topic')
        ->select('reviewers.*', 't.name as topic_name')
        ->orderBy('reviewers.created_at', 'desc')
        ->get();

        return view('reviewers.index', compact('reviewers'));
    }

    // Show form for creating reviewers (Faculty)
    public function create()
    {
        $subjects = [];
        try {
            if (class_exists('\App\\Models\\Subject')) {
                $subjects = \App\Models\Subject::with('topics')->orderBy('name')->get();
            }
        } catch (\Exception $e) {
            \Log::warning('Could not load subjects for reviewer create form: ' . $e->getMessage());
        }

        $sessionUser = null;
        try {
            $sessionUser = Auth::user();
        } catch (\Exception $e) {
            // ignore
        }

        $oldInput = [];
        try {
            $oldInput = session()->getOldInput();
        } catch (\Exception $e) {
            // ignore
        }

        return view('reviewers.create', [
            'subjects' => $subjects,
            'sessionUser' => $sessionUser,
            'oldInput' => $oldInput,
        ]);
    }

    // Main method for faculty creating reviewers - UPLOAD ONLY, NO EMAIL
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject_id' => 'required|integer|exists:subjects,id',
            'topic_id' => 'required|integer',
            'reviewerFile.*' => 'required|file|mimes:pdf|max:10240'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $permanentDir = storage_path('app/public/reviewers');
        if (!is_dir($permanentDir)) {
            mkdir($permanentDir, 0755, true);
        }

        $attachmentPaths = [];
        foreach ($request->file('reviewerFile') as $file) {
            $randomName = uniqid() . '_' . $file->getClientOriginalName();
            $path = $permanentDir . '/' . $randomName;
            $file->move($permanentDir, $randomName);
            $attachmentPaths[] = $path;
        }

        try {
            $reviewerData = [];
            $subjectName = $request->input('subject_id');
            try {
                if (class_exists('\App\\Models\\Subject')) {
                    $subjectModel = \App\Models\Subject::find($request->input('subject_id'));
                    if ($subjectModel) {
                        $subjectName = $subjectModel->name;
                    }
                }
            } catch (\Exception $e) {
                // leave subjectName as provided id if lookup fails
            }

            $user = Auth::user();
            $authorName = $this->getUserFullName($user);

            foreach ($attachmentPaths as $path) {
                $reviewerData[] = [
                    'topic' => $request->input('topic_id'),
                    'name' => $subjectName,
                    'author' => $authorName,
                    'path' => basename($path),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            Reviewer::insert($reviewerData);

            $fileCount = count($attachmentPaths);
            $successMessage = $fileCount > 1 
                ? "Successfully uploaded {$fileCount} reviewer files!"
                : 'Reviewer uploaded successfully!';
            
            if ($request->has('add_another')) {
                return redirect('/reviewers/create')->with('success', $successMessage . ' You can add another.');
            }

            return redirect('/reviewers')->with('success', $successMessage);

        } catch (\Exception $e) {
            \Log::error('Reviewer upload failed: ' . $e->getMessage());
            
            foreach ($attachmentPaths as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            return back()->with('error', 'Failed to upload reviewer. Please try again.')->withInput();
        }
    }

    // Delete reviewer method
    public function destroy($id)
    {
        try {
            $reviewer = Reviewer::findOrFail($id);
            
            $filePath = storage_path('app/public/reviewers/' . $reviewer->path);
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            
            $reviewer->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Reviewer deleted successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Reviewer deletion failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete reviewer: ' . $e->getMessage()
            ], 500);
        }
    }

    // Method for students to email themselves a reviewer
    public function emailReviewer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reviewer_path' => 'required|string',
            'student_email' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid data provided.'], 422);
        }

        try {
            $student = Auth::user();
            
            $emailAddress = ($request->student_email === 'auto' || empty($request->student_email)) 
                ? $student->email 
                : $request->student_email;

            if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['success' => false, 'message' => 'Invalid email address.'], 422);
            }
            
            $reviewer = Reviewer::where('path', $request->reviewer_path)->first();
            
            if (!$reviewer) {
                return response()->json(['success' => false, 'message' => 'Reviewer not found.'], 404);
            }

            $filePath = storage_path('app/public/reviewers/' . $reviewer->path);
            
            if (!file_exists($filePath)) {
                return response()->json(['success' => false, 'message' => 'Reviewer file not found.'], 404);
            }

            dispatch(new SendReviewerEmailJob(
                [$emailAddress],
                $reviewer->name,
                $filePath,
                [
                    "student" => [
                        "first_name" => $student->first_name,
                        "last_name" => $student->last_name,
                    ]
                ]
            ));

            return response()->json(['success' => true, 'message' => 'Reviewer email queued! You will receive it shortly.']);

        } catch (\Exception $e) {
            \Log::error('Student reviewer email failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to queue reviewer email.'], 500);
        }
    }

    // Method for students to email themselves an exam record
    public function emailExamRecord(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_record_id' => 'required|integer|exists:exam_records,id',
            'student_email' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Invalid data provided.'], 422);
        }

        try {
            $student = Auth::user();
            
            $emailAddress = ($request->student_email === 'auto' || empty($request->student_email)) 
                ? $student->email 
                : $request->student_email;

            if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                return response()->json(['success' => false, 'message' => 'Invalid email address.'], 422);
            }
            
            $examRecord = \App\Models\ExamRecord::with(['exam', 'subjects'])->find($request->exam_record_id);
            
            if (!$examRecord) {
                return response()->json(['success' => false, 'message' => 'Exam record not found.'], 404);
            }

            $studentData = [
                'first_name' => $this->getUserFirstName($student),
                'last_name' => $this->getUserLastName($student),
                'email' => $student->email,
                'id' => $student->id
            ];

            dispatch(new SendExamRecordEmailJob(
                [$emailAddress],
                'Your Exam Record - ' . $examRecord->exam->name,
                $examRecord->toArray(),
                $studentData
            ));

            return response()->json(['success' => true, 'message' => 'Exam record email queued! You will receive it shortly.']);

        } catch (\Exception $e) {
            \Log::error('Student exam record email failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to queue exam record email.'], 500);
        }
    }

    // Download exam record as PDF
    public function downloadExamRecord($examRecordId)
    {
        try {
            $student = Auth::user();
            
            $examRecord = \App\Models\ExamRecord::with(['exam', 'subjects'])->find($examRecordId);
            
            if (!$examRecord) {
                abort(404, 'Exam record not found.');
            }

            $tempDir = storage_path('app/temp_downloads');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $fileName = 'exam_record_' . $examRecord->id . '_' . uniqid() . '.pdf';
            $pdfPath = $tempDir . '/' . $fileName;
            
            $this->createExamRecordPDF($examRecord, $pdfPath);

            $downloadName = 'Exam_Record_' . str_replace(' ', '_', $examRecord->exam->name) . '_Attempt_' . $examRecord->attempt . '.pdf';
            
            return response()->download($pdfPath, $downloadName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            \Log::error('Exam record download failed: ' . $e->getMessage());
            abort(500, 'Failed to generate exam record PDF.');
        }
    }

    // Download reviewer PDF by ID
    public function downloadReviewer($id)
    {
        try {
            $reviewer = Reviewer::findOrFail($id);
            
            $filePath = storage_path('app/public/reviewers/' . $reviewer->path);
            
            if (!file_exists($filePath)) {
                abort(404, 'Reviewer file not found.');
            }

            $topicName = 'Reviewer';
            try {
                $topic = \App\Models\Topic::find($reviewer->topic);
                if ($topic) {
                    $topicName = $topic->name;
                }
            } catch (\Exception $e) {
                // Use default if topic not found
            }

            $downloadName = 'Reviewer_' . str_replace(' ', '_', $reviewer->name) . '_' . str_replace(' ', '_', $topicName) . '.pdf';
            
            return response()->download($filePath, $downloadName);

        } catch (\Exception $e) {
            \Log::error('Reviewer download failed: ' . $e->getMessage());
            abort(404, 'Reviewer not found or file is missing.');
        }
    }

    // Create exam record PDF using Dompdf
    private function createExamRecordPDF($examRecord, $filePath)
    {
        $student = Auth::user();
        $percentage = ($examRecord->total_score / $examRecord->exam->max_score) * 100;
        
        $performanceData = $this->getPerformanceAnalysis($percentage);
        
        $html = $this->generateExamRecordHTML($examRecord, $student, $percentage, $performanceData);
        
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        file_put_contents($filePath, $dompdf->output());
    }

    // Get performance analysis data
    private function getPerformanceAnalysis($percentage)
    {
        if ($percentage >= 90) {
            return [
                'level' => 'Excellent',
                'recommendation' => 'Keep up the outstanding work!'
            ];
        } elseif ($percentage >= 80) {
            return [
                'level' => 'Very Good',
                'recommendation' => 'Continue your good study habits.'
            ];
        } elseif ($percentage >= 70) {
            return [
                'level' => 'Good',
                'recommendation' => 'Focus on areas that need improvement.'
            ];
        } elseif ($percentage >= 60) {
            return [
                'level' => 'Satisfactory',
                'recommendation' => 'Review study materials and seek help if needed.'
            ];
        } else {
            return [
                'level' => 'Needs Improvement',
                'recommendation' => 'Consider additional study time and tutoring.'
            ];
        }
    }

    // Generate HTML for exam record PDF
    private function generateExamRecordHTML($examRecord, $student, $percentage, $performanceData)
    {
        $firstName = $this->getUserFirstName($student);
        $lastName = $this->getUserLastName($student);
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .header { text-align: center; border-bottom: 3px solid #1e3a8a; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #1e3a8a; margin: 0; font-size: 28px; }
        .info-box { background: #f3f4f6; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .info-item { margin: 8px 0; }
        .info-label { font-weight: bold; color: #1e3a8a; }
        .section { margin: 25px 0; }
        .section-title { color: #1e3a8a; font-size: 18px; font-weight: bold; border-bottom: 2px solid #1e3a8a; padding-bottom: 5px; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #d1d5db; padding: 10px; text-align: left; }
        th { background: #1e3a8a; color: white; font-weight: bold; }
        tr:nth-child(even) { background: #f9fafb; }
        .score-highlight { background: #dbeafe; font-weight: bold; font-size: 16px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #e5e7eb; text-align: center; font-size: 12px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <h1>EXAM RECORD</h1>
    </div>
    
    <div class="info-box">
        <div class="info-item"><span class="info-label">Student:</span> ' . htmlspecialchars($firstName) . ' ' . htmlspecialchars($lastName) . '</div>
        <div class="info-item"><span class="info-label">Exam:</span> ' . htmlspecialchars($examRecord->exam->name) . '</div>
        <div class="info-item"><span class="info-label">Attempt:</span> #' . htmlspecialchars($examRecord->attempt) . '</div>
        <div class="info-item score-highlight"><span class="info-label">Score:</span> ' . htmlspecialchars($examRecord->total_score) . '/' . htmlspecialchars($examRecord->exam->max_score) . ' (' . number_format($percentage, 1) . '%)</div>
        <div class="info-item"><span class="info-label">Status:</span> ' . htmlspecialchars(ucfirst($examRecord->status)) . '</div>
        <div class="info-item"><span class="info-label">Date Taken:</span> ' . date('m/d/Y', strtotime($examRecord->date_taken)) . '</div>
        <div class="info-item"><span class="info-label">Time Taken:</span> ' . htmlspecialchars($examRecord->time_taken) . ' minutes</div>
    </div>';
    
        if ($examRecord->subjects && count($examRecord->subjects) > 0) {
            $html .= '
    <div class="section">
        <div class="section-title">SUBJECT BREAKDOWN</div>
        <table>
            <thead>
                <tr>
                    <th>Subject Name</th>
                    <th>Score Obtained</th>
                    <th>Maximum Score</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>';
        
            foreach ($examRecord->subjects as $subject) {
                $subjectPercentage = $subject->score > 0 ? ($subject->score_obtained / $subject->score) * 100 : 0;
                $html .= '
                <tr>
                    <td>' . htmlspecialchars($subject->subject_name) . '</td>
                    <td>' . htmlspecialchars($subject->score_obtained) . '</td>
                    <td>' . htmlspecialchars($subject->score) . '</td>
                    <td>' . number_format($subjectPercentage, 1) . '%</td>
                </tr>';
            }
        
            $html .= '
            </tbody>
        </table>
    </div>';
        }
    
        $html .= '
    <div class="section">
        <div class="section-title">PERFORMANCE ANALYSIS</div>
        <div class="info-box">
            <div class="info-item"><span class="info-label">Overall Score Percentage:</span> ' . number_format($percentage, 1) . '%</div>
            <div class="info-item"><span class="info-label">Performance Level:</span> ' . htmlspecialchars($performanceData['level']) . '</div>
            <div class="info-item"><span class="info-label">Recommendation:</span> ' . htmlspecialchars($performanceData['recommendation']) . '</div>
        </div>
    </div>
    
    <div class="footer">
        <p>Generated on: ' . date('F j, Y \a\t g:i A') . '</p>
        <p>Academic System</p>
    </div>
</body>
</html>';
        
        return $html;
    }
}